<?php
declare(strict_types=1);

use CB\Notes\Organization\Catalog;
use CB\Notes\Plugin;
use CB\Notes\Sharing\Controller;

/**
 * R10 partial: demonstrate the WordPress usermeta/postmeta dependency and
 * sharing revocation on a genuine WordPress Trash/Restore lifecycle.
 *
 * This does NOT replace a full-site SQL dump/restore into a second installation.
 * It never operates outside the disposable core_blueprint_notes_test database.
 */
final class NotesRuntimeRecoveryIntegrationTest extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();
        self::assertSame( 'core_blueprint_notes_test', (string) DB_NAME );
        self::assertSame( '127.0.0.1:3307', (string) DB_HOST );
        Plugin::grant_roles();
        self::assertTrue( post_type_exists( Plugin::TYPE ) );
        self::assertTrue( post_type_supports( Plugin::TYPE, 'custom-fields' ),
            'Without CPT custom-fields support, native Gutenberg REST meta writes are omitted.' );
    }

    /**
     * @return array{id:string,kind:string,name:string}
     */
    private function create_classification( string $kind, string $name ): array {
        $request = new WP_REST_Request( 'POST', '/core-blueprint-notes/v1/organization' );
        $request->set_body_params( [ 'kind' => $kind, 'action' => 'create', 'name' => $name ] );
        $response = rest_do_request( $request );
        self::assertSame( 200, $response->get_status(), $kind . ' catalog creation must work over REST.' );
        $result = $response->get_data();
        self::assertIsArray( $result );
        self::assertCount( 1, $result[$kind] );
        $item = $result[$kind][0];
        self::assertTrue( Catalog::valid_id( $item['id'] ?? null ) );
        self::assertSame( $name, $item['name'] );
        return [ 'id' => $item['id'], 'kind' => $kind, 'name' => $name ];
    }

    public function test_owner_catalog_postmeta_graph_can_be_restored_without_cross_account_access(): void {
        $a = self::factory()->user->create( [ 'role' => 'editor' ] );
        $b = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $a );

        $notebook = $this->create_classification( 'notebook', 'Company handbook' );
        $category = $this->create_classification( 'category', 'Governance' );
        $tag = $this->create_classification( 'tag', 'Continuity' );
        $note = self::factory()->post->create( [
            'post_type' => Plugin::TYPE,
            'post_status' => 'private',
            'post_author' => $a,
            'post_title' => 'Recovery test note',
            'post_content' => '<!-- wp:paragraph --><p>Private recovery evidence</p><!-- /wp:paragraph -->',
        ] );
        self::assertGreaterThan( 0, $note );

        // Verify the actual native REST contract before submitting a
        // Gutenberg-style metadata update. A 200 without persisted meta is a
        // product defect, not a passing UI contract.
        $type = get_post_type_object( Plugin::TYPE );
        $controller = $type->get_rest_controller();
        self::assertNotNull( $controller );
        $schema = $controller->get_item_schema();
        self::assertArrayHasKey( 'meta', $schema['properties'],
            'WordPress did not expose the meta property for Notes CPT.' );
        foreach ( [ Catalog::NOTEBOOK, Catalog::CATEGORY, Catalog::TAGS ] as $key ) {
            self::assertArrayHasKey( $key, $schema['properties']['meta']['properties'],
                'Registered Notes meta key is missing from the REST schema: ' . $key );
            self::assertTrue( current_user_can( 'edit_post_meta', $note, $key ),
                'Notes owner lacks effective metadata write permission for ' . $key );
        }

        // Gutenberg's canonical native REST post-meta write, not direct
        // mutation of the catalog bypassing its owner/ID validator.
        $assignment = new WP_REST_Request( 'POST', '/wp/v2/cb-notes/' . $note );
        $assignment->set_param( 'meta', [
            Catalog::NOTEBOOK => $notebook['id'],
            Catalog::CATEGORY => $category['id'],
            Catalog::TAGS => [ $tag['id'] ],
        ] );
        $assigned = rest_do_request( $assignment );
        self::assertSame( 200, $assigned->get_status(), 'Owner must be able to assign their own catalog IDs.' );
        $assigned_body = $assigned->get_data();
        self::assertIsArray( $assigned_body );
        self::assertArrayHasKey( 'meta', $assigned_body,
            'WordPress response omitted registered Notes classification meta entirely.' );
        self::assertSame( $notebook['id'], get_post_meta( $note, Catalog::NOTEBOOK, true ),
            'REST write returned 200 but notebook postmeta is empty; REST response meta: ' .
            wp_json_encode( $assigned_body['meta'] ) );
        self::assertSame( $category['id'], get_post_meta( $note, Catalog::CATEGORY, true ) );
        self::assertSame( [ $tag['id'] ], get_post_meta( $note, Catalog::TAGS, true ) );

        $expected = [
            'notebook' => 'Company handbook',
            'category' => 'Governance',
            'tags' => [ 'Continuity' ],
        ];
        self::assertSame( $expected, Catalog::labels_for_post( $note ) );
        $owner_meta = get_user_meta( $a, Catalog::USER_META, true );
        self::assertIsArray( $owner_meta );
        self::assertCount( 1, $owner_meta['notebook'] );
        self::assertCount( 1, $owner_meta['category'] );
        self::assertCount( 1, $owner_meta['tag'] );

        // Models an incomplete note-only restore: post content and opaque
        // references remain but catalog labels cannot be reconstructed.
        self::assertTrue( delete_user_meta( $a, Catalog::USER_META ) );
        self::assertSame( [ 'notebook' => '', 'category' => '', 'tags' => [] ],
            Catalog::labels_for_post( $note ) );
        self::assertSame( $notebook['id'], get_post_meta( $note, Catalog::NOTEBOOK, true ) );
        self::assertStringContainsString( 'Private recovery evidence', get_post( $note )->post_content );

        // Models restoration of the matching wp_usermeta record alongside
        // posts/postmeta. Real full-site SQL export/import remains a separate gate.
        self::assertNotFalse( update_user_meta( $a, Catalog::USER_META, $owner_meta ) );
        self::assertSame( $expected, Catalog::labels_for_post( $note ) );

        wp_set_current_user( $b );
        self::assertSame( [ 'notebook' => '', 'category' => '', 'tags' => [] ],
            Catalog::labels_for_post( $note ), 'Other editor may not see owner classifications.' );
        self::assertSame( [ 'notebook' => [], 'category' => [], 'tag' => [] ],
            Catalog::catalog( $b ) );
        $foreign_note = self::factory()->post->create( [
            'post_type' => Plugin::TYPE, 'post_status' => 'private',
            'post_author' => $b, 'post_title' => 'B own note',
        ] );
        $forbidden_ref = new WP_REST_Request( 'POST', '/wp/v2/cb-notes/' . $foreign_note );
        $forbidden_ref->set_param( 'meta', [ Catalog::NOTEBOOK => $notebook['id'] ] );
        self::assertGreaterThanOrEqual( 400, rest_do_request( $forbidden_ref )->get_status(),
            'Opaque ID from another owner must not authorize a label assignment.' );
        self::assertSame( '', get_post_meta( $foreign_note, Catalog::NOTEBOOK, true ) );
    }

    public function test_real_trash_and_restore_disarm_old_public_share_even_if_stale_meta_returns(): void {
        $author = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $author );
        $note = self::factory()->post->create( [
            'post_type' => Plugin::TYPE,
            'post_status' => 'private',
            'post_author' => $author,
            'post_title' => 'Lifecycle test',
            'post_content' => '<!-- wp:paragraph --><p>Frozen public sample</p><!-- /wp:paragraph -->',
        ] );
        self::assertGreaterThan( 0, $note );
        $route = '/core-blueprint-notes/v1/sharing/' . $note;
        $state = rest_do_request( new WP_REST_Request( 'GET', $route ) );
        self::assertSame( 200, $state->get_status() );
        $initial = $state->get_data();
        $share = new WP_REST_Request( 'POST', $route );
        $share->set_body_params( [
            'mode' => 'public',
            'expected_mode' => $initial['mode'],
            'expected_modified_gmt' => $initial['modified_gmt'],
            'expected_content_version' => $initial['content_version'],
            'confirmation' => 'PUBLIC',
        ] );
        $created = rest_do_request( $share );
        self::assertSame( 200, $created->get_status() );
        $snapshot = get_post_meta( $note, Controller::PUBLIC_META, true );
        $key = (string) get_post_meta( $note, Controller::PUBLIC_KEY, true );
        self::assertIsArray( $snapshot );
        self::assertTrue( Controller::public_snapshot_allowed( get_post( $note ), $key, $snapshot ) );

        $trashed = wp_trash_post( $note );
        self::assertInstanceOf( WP_Post::class, $trashed );
        self::assertSame( 'trash', get_post_status( $note ) );
        self::assertSame( '', (string) get_post_meta( $note, Controller::PUBLIC_KEY, true ) );
        self::assertFalse( Controller::public_snapshot_allowed( get_post( $note ), $key, $snapshot ) );

        // If a backup/reconciliation unexpectedly reintroduces stale share
        // metadata while trashed, the real untrash lifecycle must disarm it.
        self::assertNotFalse( update_post_meta( $note, Controller::MODE_META, 'public' ) );
        self::assertNotFalse( update_post_meta( $note, Controller::PUBLIC_META, $snapshot ) );
        self::assertNotFalse( update_post_meta( $note, Controller::PUBLIC_KEY, $key ) );
        self::assertNotFalse( wp_untrash_post( $note ) );
        // WordPress intentionally restores trashed posts as drafts (WP 5.6+).
        // Draft Notes remain non-public; stale sharing state must still be gone.
        self::assertSame( 'draft', get_post_status( $note ) );
        self::assertFalse( is_post_publicly_viewable( get_post( $note ) ) );
        self::assertSame( 'private', get_post_meta( $note, Controller::MODE_META, true ) );
        self::assertSame( '', (string) get_post_meta( $note, Controller::PUBLIC_KEY, true ) );
        self::assertSame( '', (string) get_post_meta( $note, Controller::PUBLIC_META, true ) );
        self::assertFalse( Controller::public_snapshot_allowed( get_post( $note ), $key, $snapshot ) );
    }
}

<?php
declare(strict_types=1);

use CB\Notes\Plugin;
use CB\Notes\Sharing\Controller;

/**
 * Notes RC1: real WordPress REST write, revision/autosave and public sharing gates.
 *
 * The WP_UnitTestCase runner uses a generated, disposable Notes-only test database
 * database only. It does not exercise the HTTP stack, CDN or Secure Share provider.
 */
final class NotesRuntimeMutationSharingIntegrationTest extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();
        self::assertSame( 1, preg_match( '/^core_blueprint_notes_int_[a-f0-9]{12}_test$/D', (string) DB_NAME ) );
        self::assertSame( '127.0.0.1:3307', (string) DB_HOST );
        Plugin::grant_roles();
        self::assertTrue( post_type_exists( Plugin::TYPE ) );
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    private function notes_fixture(): array {
        $author = self::factory()->user->create( [ 'role' => 'editor' ] );
        $other = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $author );
        $mine = self::factory()->post->create( [
            'post_type' => Plugin::TYPE,
            'post_status' => 'private',
            'post_author' => $author,
            'post_title' => 'My protected note',
            'post_content' => '<!-- wp:paragraph --><p>Original secret alpha</p><!-- /wp:paragraph -->',
        ] );
        wp_set_current_user( $other );
        $foreign = self::factory()->post->create( [
            'post_type' => Plugin::TYPE,
            'post_status' => 'private',
            'post_author' => $other,
            'post_title' => 'Other protected note',
            'post_content' => '<!-- wp:paragraph --><p>Foreign secret beta</p><!-- /wp:paragraph -->',
        ] );
        self::assertGreaterThan( 0, $mine );
        self::assertGreaterThan( 0, $foreign );
        return [ $author, $other, $mine, $foreign ];
    }

    public function test_rest_update_allowed_for_owner_but_foreign_write_and_delete_are_denied(): void {
        [ $author, $other, $mine, $foreign ] = $this->notes_fixture();

        wp_set_current_user( $other );
        $unauthorized_update = new WP_REST_Request( 'POST', '/wp/v2/cb-notes/' . $mine );
        $unauthorized_update->set_param( 'content', '<!-- wp:paragraph --><p>Injected content</p><!-- /wp:paragraph -->' );
        $denied = rest_do_request( $unauthorized_update );
        self::assertGreaterThanOrEqual( 400, $denied->get_status() );
        self::assertStringNotContainsString( 'Original secret alpha', wp_json_encode( $denied->get_data() ) );

        $unauthorized_delete = rest_do_request(
            new WP_REST_Request( 'DELETE', '/wp/v2/cb-notes/' . $mine )
        );
        self::assertGreaterThanOrEqual( 400, $unauthorized_delete->get_status() );
        self::assertSame( 'private', get_post_status( $mine ) );
        self::assertStringContainsString( 'Original secret alpha', get_post( $mine )->post_content );

        wp_set_current_user( 0 );
        $anonymous_update = new WP_REST_Request( 'POST', '/wp/v2/cb-notes/' . $mine );
        $anonymous_update->set_param( 'title', 'Anonymous overwrite' );
        self::assertGreaterThanOrEqual( 400, rest_do_request( $anonymous_update )->get_status() );

        wp_set_current_user( $author );
        $owner_update = new WP_REST_Request( 'POST', '/wp/v2/cb-notes/' . $mine );
        $owner_update->set_param( 'title', 'Owner legitimate edit' );
        $owner_update->set_param( 'content', '<!-- wp:paragraph --><p>Authorized edit gamma</p><!-- /wp:paragraph -->' );
        $updated = rest_do_request( $owner_update );
        self::assertSame( 200, $updated->get_status(), 'The note owner must retain Gutenberg REST editing.' );
        self::assertSame( 'Owner legitimate edit', get_post( $mine )->post_title );
        self::assertStringContainsString( 'Authorized edit gamma', get_post( $mine )->post_content );
        self::assertSame( 'private', get_post_status( $mine ) );
        self::assertSame( $author, (int) get_post( $mine )->post_author );
        self::assertSame( 'private', get_post_status( $foreign ) );
    }

    public function test_revisions_and_autosaves_use_parent_note_permissions(): void {
        [ $author, $other, $mine ] = $this->notes_fixture();
        wp_set_current_user( $author );
        self::assertTrue( post_type_supports( Plugin::TYPE, 'revisions' ) );
        $revision_id = wp_save_post_revision( $mine );
        self::assertIsInt( $revision_id, 'A Notes revision must be saved in the real WordPress schema.' );
        self::assertGreaterThan( 0, $revision_id );

        $own_revisions = rest_do_request(
            new WP_REST_Request( 'GET', '/wp/v2/cb-notes/' . $mine . '/revisions' )
        );
        self::assertSame( 200, $own_revisions->get_status() );
        $revisions_json = wp_json_encode( $own_revisions->get_data() );
        self::assertStringContainsString( 'Original secret alpha', $revisions_json );

        $own_autosaves = rest_do_request(
            new WP_REST_Request( 'GET', '/wp/v2/cb-notes/' . $mine . '/autosaves' )
        );
        self::assertSame( 200, $own_autosaves->get_status() );

        wp_set_current_user( $other );
        foreach ( [
            '/wp/v2/cb-notes/' . $mine . '/revisions',
            '/wp/v2/cb-notes/' . $mine . '/revisions/' . $revision_id,
            '/wp/v2/cb-notes/' . $mine . '/autosaves',
        ] as $route ) {
            $denied = rest_do_request( new WP_REST_Request( 'GET', $route ) );
            self::assertGreaterThanOrEqual( 400, $denied->get_status(), $route );
            self::assertStringNotContainsString( 'Original secret alpha', wp_json_encode( $denied->get_data() ) );
        }
        $foreign_autosave = new WP_REST_Request( 'POST', '/wp/v2/cb-notes/' . $mine . '/autosaves' );
        $foreign_autosave->set_param( 'content', '<!-- wp:paragraph --><p>Forbidden overwrite</p><!-- /wp:paragraph -->' );
        self::assertGreaterThanOrEqual( 400, rest_do_request( $foreign_autosave )->get_status() );

        wp_set_current_user( 0 );
        self::assertGreaterThanOrEqual(
            400, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/cb-notes/' . $mine . '/autosaves' ) )->get_status()
        );
        self::assertStringContainsString( 'Original secret alpha', get_post( $mine )->post_content );
    }

    public function test_public_snapshot_is_frozen_and_revoked_by_owner_only(): void {
        [ $author, $other, $mine ] = $this->notes_fixture();
        $route = '/core-blueprint-notes/v1/sharing/' . $mine;

        wp_set_current_user( $other );
        self::assertGreaterThanOrEqual(
            400, rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_status()
        );
        $foreign_revoke = new WP_REST_Request( 'POST', $route );
        $foreign_revoke->set_param( 'mode', 'private' );
        self::assertGreaterThanOrEqual( 400, rest_do_request( $foreign_revoke )->get_status() );

        wp_set_current_user( $author );
        $status = rest_do_request( new WP_REST_Request( 'GET', $route ) );
        self::assertSame( 200, $status->get_status() );
        $initial = $status->get_data();
        self::assertSame( 'private', $initial['mode'] );

        $params = [
            'mode' => 'public',
            'expected_mode' => 'private',
            'expected_content_version' => $initial['content_version'],
            'expected_modified_gmt' => $initial['modified_gmt'],
        ];
        $missing_confirmation = new WP_REST_Request( 'POST', $route );
        $missing_confirmation->set_body_params( $params );
        self::assertGreaterThanOrEqual( 400, rest_do_request( $missing_confirmation )->get_status() );
        self::assertSame( '', (string) get_post_meta( $mine, Controller::PUBLIC_KEY, true ) );

        $confirmed = new WP_REST_Request( 'POST', $route );
        $confirmed->set_body_params( $params + [ 'confirmation' => 'PUBLIC' ] );
        $shared = rest_do_request( $confirmed );
        self::assertSame( 200, $shared->get_status(), 'Explicitly confirmed owner share must succeed.' );
        self::assertSame( 'public', $shared->get_data()['mode'] );
        $snapshot = get_post_meta( $mine, Controller::PUBLIC_META, true );
        $key = (string) get_post_meta( $mine, Controller::PUBLIC_KEY, true );
        self::assertIsArray( $snapshot );
        self::assertMatchesRegularExpression( '/^[a-f0-9]{32}$/D', $key );
        self::assertTrue( Controller::public_snapshot_allowed( get_post( $mine ), $key, $snapshot ) );
        self::assertStringContainsString( 'Original secret alpha', $snapshot['html'] );

        // The original continues to evolve, while the published copy is frozen.
        $saved_html = (string) $snapshot['html'];
        wp_update_post( [
            'ID' => $mine,
            'post_content' => '<!-- wp:paragraph --><p>New secret, not yet shared</p><!-- /wp:paragraph -->',
        ] );
        self::assertSame( $saved_html, get_post_meta( $mine, Controller::PUBLIC_META, true )['html'] );

        wp_set_current_user( $other );
        $foreign_revoke_again = new WP_REST_Request( 'POST', $route );
        $foreign_revoke_again->set_param( 'mode', 'private' );
        self::assertGreaterThanOrEqual( 400, rest_do_request( $foreign_revoke_again )->get_status() );
        self::assertTrue( Controller::public_snapshot_allowed( get_post( $mine ), $key, $snapshot ) );

        wp_set_current_user( $author );
        $revoke = new WP_REST_Request( 'POST', $route );
        $revoke->set_param( 'mode', 'private' );
        $revoked = rest_do_request( $revoke );
        self::assertSame( 200, $revoked->get_status(), 'Owner revocation must always be available despite private edits.' );
        self::assertSame( 'private', $revoked->get_data()['mode'] );
        self::assertSame( '', (string) get_post_meta( $mine, Controller::PUBLIC_KEY, true ) );
        self::assertFalse( Controller::public_snapshot_allowed( get_post( $mine ), $key, $snapshot ) );
        self::assertSame( 'private', get_post_status( $mine ) );
    }
}

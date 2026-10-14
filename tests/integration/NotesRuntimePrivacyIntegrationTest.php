<?php
declare(strict_types=1);

use CB\Notes\Abilities;
use CB\Notes\Access;
use CB\Notes\Plugin;

/**
 * Real WP/PHPUnit regression coverage for RC1 R02/R03/R09 essentials.
 * This is not an HTTP/MCP transport or browser/cache acceptance substitute.
 */
final class NotesRuntimePrivacyIntegrationTest extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();
        if ( 'core_blueprint_notes_test' !== (string) DB_NAME ||
             '127.0.0.1:3307' !== (string) DB_HOST ) {
            self::fail( 'Notes integration must never run against a noncanonical database.' );
        }
        Plugin::grant_roles();
        self::assertSame( '1.2', (string) CB_CORE_API_VERSION );
        self::assertTrue( post_type_exists( Plugin::TYPE ), 'Notes CPT must be registered.' );
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    private function create_two_private_notes(): array {
        $a = self::factory()->user->create( [ 'role' => 'editor' ] );
        $b = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $a );
        $mine = self::factory()->post->create( [
            'post_type' => Plugin::TYPE,
            'post_author' => $a,
            'post_status' => 'private',
            'post_title' => 'A private title',
            'post_content' => '<!-- wp:paragraph --><p>Only A may read violet-marker</p><!-- /wp:paragraph -->',
        ] );
        wp_set_current_user( $b );
        $other = self::factory()->post->create( [
            'post_type' => Plugin::TYPE,
            'post_author' => $b,
            'post_status' => 'private',
            'post_title' => 'B hidden title',
            'post_content' => '<!-- wp:paragraph --><p>Only B may read amber-marker</p><!-- /wp:paragraph -->',
        ] );
        self::assertGreaterThan( 0, $mine );
        self::assertGreaterThan( 0, $other );
        return [ $a, $b, $mine, $other ];
    }

    public function test_owner_and_foreign_exact_rest_and_anonymous_have_separate_permissions(): void {
        [ $a, $b, $mine, $other ] = $this->create_two_private_notes();
        wp_set_current_user( $a );
        self::assertTrue( Access::can_read( get_post( $mine ) ) );
        self::assertFalse( Access::can_read( get_post( $other ) ) );

        $self = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/cb-notes/' . $mine ) );
        self::assertSame( 200, $self->get_status(), 'Owner should retrieve own note through real REST.' );
        self::assertSame( $mine, (int) ( $self->get_data()['id'] ?? 0 ) );

        $foreign = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/cb-notes/' . $other ) );
        self::assertGreaterThanOrEqual( 400, $foreign->get_status(), 'Foreign private note must be denied.' );
        self::assertStringNotContainsString( 'amber-marker', wp_json_encode( $foreign->get_data() ) );
        self::assertStringNotContainsString( 'B hidden title', wp_json_encode( $foreign->get_data() ) );

        wp_set_current_user( 0 );
        $anonymous = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/cb-notes/' . $mine ) );
        self::assertGreaterThanOrEqual( 400, $anonymous->get_status(), 'Anonymous exact Notes read must fail.' );
        self::assertStringNotContainsString( 'violet-marker', wp_json_encode( $anonymous->get_data() ) );

        wp_set_current_user( $b );
        self::assertFalse( current_user_can( 'read_post', $mine ) );
        self::assertFalse( current_user_can( 'edit_post', $mine ) );
        self::assertFalse( current_user_can( 'delete_post', $mine ) );
    }

    public function test_publish_action_stays_private_and_native_blocks_remain_intact(): void {
        $author = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $author );
        $blocks = '<!-- wp:paragraph --><p>Private Gutenberg content</p><!-- /wp:paragraph -->';
        $id = wp_insert_post( [
            'post_type' => Plugin::TYPE,
            'post_author' => $author,
            'post_status' => 'publish',
            'post_title' => 'Unlisted note',
            'post_content' => $blocks,
        ], true );
        self::assertIsInt( $id );
        self::assertSame( 'private', get_post_status( $id ) );
        self::assertSame( $blocks, get_post( $id )->post_content );
        wp_set_current_user( 0 );
        self::assertFalse( Access::can_read( get_post( $id ) ) );
    }

    public function test_abilities_default_off_and_enabled_reads_only_owned_notes(): void {
        [ $a, $b, $mine, $other ] = $this->create_two_private_notes();
        wp_set_current_user( $a );
        ( new WP_User( $a ) )->add_cap( 'cb_notes_use_ai' );
        ( new WP_User( $b ) )->add_cap( 'cb_notes_use_ai' );

        delete_option( Plugin::AI_OPTION );
        self::assertFalse( Abilities::can_execute(), 'Remote Notes access is disabled by default.' );
        self::assertWPError( Abilities::read_note( [ 'id' => $mine ] ) );

        update_option( Plugin::AI_OPTION, true );
        self::assertTrue( Abilities::can_execute() );
        $own = Abilities::read_note( [ 'id' => $mine ] );
        self::assertIsArray( $own );
        self::assertSame( $mine, (int) $own['id'] );
        self::assertStringContainsString( 'violet-marker', $own['content'] );
        self::assertWPError( Abilities::read_note( [ 'id' => $other ] ) );
        $listed = Abilities::list_notes( [ 'limit' => 20 ] );
        self::assertIsArray( $listed );
        self::assertCount( 1, $listed['items'] );
        self::assertSame( $mine, (int) $listed['items'][0]['id'] );
        self::assertArrayNotHasKey( 'content', $listed['items'][0] );
        self::assertSame( [], Abilities::search_notes( [ 'query' => 'B hidden' ] )['items'] );

        wp_set_current_user( $b );
        self::assertWPError( Abilities::read_note( [ 'id' => $mine ] ) );

        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );
        self::assertTrue( Access::can_read( get_post( $mine ) ), 'Admin override is deliberate.' );
        self::assertWPError( Abilities::read_note( [ 'id' => $mine ] ),
            'Admin override may not silently expand AI access beyond the author scope.' );

        update_option( Plugin::AI_OPTION, false );
        self::assertFalse( Abilities::can_execute() );
        self::assertWPError( Abilities::read_note( [ 'id' => $mine ] ) );
    }

    public function test_notes_rest_list_does_not_contain_another_owners_content(): void {
        [ $a, $b, $mine, $other ] = $this->create_two_private_notes();
        wp_set_current_user( $a );
        $response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/cb-notes' ) );
        self::assertSame( 200, $response->get_status() );
        $rows = $response->get_data();
        self::assertIsArray( $rows );
        $found_ids = array_map( static fn( array $row ): int => (int) ( $row['id'] ?? 0 ), $rows );
        self::assertContains( $mine, $found_ids );
        self::assertNotContains( $other, $found_ids );
        self::assertStringNotContainsString( 'amber-marker', wp_json_encode( $rows ) );
        self::assertStringNotContainsString( 'B hidden title', wp_json_encode( $rows ) );
    }
}

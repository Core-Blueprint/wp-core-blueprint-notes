<?php
declare(strict_types=1);

use CB\Notes\Library\Markers;
use CB\Notes\Organization\Catalog;
use CB\Notes\Plugin;
use CoreBlueprint\Core\DataExchange\Engine;
use CoreBlueprint\Core\DataExchange\Foundation;
use CoreBlueprint\Core\Interoperability\Registry;

/**
 * Portability P1: real Base Engine + WordPress actor/ownership integration.
 *
 * Runs only through the existing isolated Notes PHPUnit harness. This is an
 * internal PHP export contract test, not a browser/download/import acceptance.
 */
final class NotesRuntimePortabilityIntegrationTest extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();
        self::assertSame( 1, preg_match( '/^core_blueprint_notes_int_[a-f0-9]{12}_test$/D', (string) DB_NAME ) );
        self::assertSame( '127.0.0.1:3307', (string) DB_HOST );
        Plugin::grant_roles();
        self::assertTrue( post_type_exists( Plugin::TYPE ) );
    }

    /** @return array{owner:int,other:int,note:int,foreign:int,notebook:string,category:string,tag:string,blocks:string} */
    private function fixture(): array {
        $owner = self::factory()->user->create( [ 'role' => 'editor' ] );
        $other = self::factory()->user->create( [ 'role' => 'editor' ] );
        $notebook = str_repeat( 'a', 32 );
        $category = str_repeat( 'b', 32 );
        $tag = str_repeat( 'c', 32 );
        $blocks = '<!-- wp:paragraph --><p>NOTES-P1-OWNER-ONLY-CONTENT</p><!-- /wp:paragraph -->';

        wp_set_current_user( $owner );
        self::assertNotFalse( update_user_meta( $owner, Catalog::USER_META, [
            'notebook' => [ [ 'id' => $notebook, 'name' => 'Plans' ] ],
            'category' => [ [ 'id' => $category, 'name' => 'Governance' ] ],
            'tag' => [ [ 'id' => $tag, 'name' => 'Internal' ] ],
        ] ) );
        $note = self::factory()->post->create( [
            'post_type' => Plugin::TYPE,
            'post_author' => $owner,
            'post_status' => 'private',
            'post_title' => 'Only my notes',
            'post_content' => $blocks,
        ] );
        self::assertGreaterThan( 0, $note );
        self::assertNotFalse( update_post_meta( $note, Catalog::NOTEBOOK, $notebook ) );
        self::assertNotFalse( update_post_meta( $note, Catalog::CATEGORY, $category ) );
        self::assertNotFalse( update_post_meta( $note, Catalog::TAGS, [ $tag ] ) );
        self::assertNotFalse( update_post_meta( $note, Markers::FAVORITE, '1' ) );
        self::assertNotFalse( update_post_meta( $note, Markers::PINNED, '1' ) );

        wp_set_current_user( $other );
        $foreign = self::factory()->post->create( [
            'post_type' => Plugin::TYPE,
            'post_author' => $other,
            'post_status' => 'private',
            'post_title' => 'Foreign title must not export',
            'post_content' => '<!-- wp:paragraph --><p>NOTES-P1-FOREIGN-SECRET</p><!-- /wp:paragraph -->',
        ] );
        self::assertGreaterThan( 0, $foreign );

        return compact( 'owner', 'other', 'note', 'foreign', 'notebook', 'category', 'tag', 'blocks' );
    }

    public function test_real_base_registry_and_json_export_enforce_owner_scope(): void {
        $fixture = $this->fixture();
        $owner = $fixture['owner'];
        $other = $fixture['other'];

        $matches = array_values( array_filter(
            Engine::entities( [ Foundation::SUPPORT_EXPORT, Foundation::SUPPORT_JSON ] ),
            static fn( array $definition ): bool =>
                ( $definition['provider'] ?? '' ) === 'core-blueprint-notes'
                && ( $definition['id'] ?? '' ) === 'personal-notes'
        ) );
        self::assertCount( 1, $matches, 'Notes must actually register through the Base interoperability lifecycle.' );
        self::assertSame( [ Foundation::SUPPORT_EXPORT, Foundation::SUPPORT_IMPORT, Foundation::SUPPORT_JSON ], $matches[0]['supports'] );
        $resolved = Registry::resolve(
            Foundation::CONTRACT_OWNER, Foundation::CONTRACT_ID, Foundation::CONTRACT_VERSION,
            'core-blueprint-notes', 'personal-notes'
        );
        self::assertInstanceOf( \CB\Notes\Portability\NotesEntity::class, $resolved );

        wp_set_current_user( $owner );
        $json = Engine::export_json( 'core-blueprint-notes', 'personal-notes' );
        self::assertIsString( $json, 'The real Base Engine must produce an authorized JSON envelope.' );
        $data = json_decode( $json, true, 32, JSON_THROW_ON_ERROR );
        self::assertSame( Foundation::FORMAT_ID, $data['format'] );
        self::assertSame( Foundation::FORMAT_VERSION, $data['format_version'] );
        self::assertSame( 'core-blueprint-notes', $data['extension_id'] );
        self::assertSame( 'personal-notes', $data['entity'] );
        self::assertSame( 1, $data['schema_version'] );
        self::assertCount( 2, $data['records'], 'Only the owner catalog and the owner note belong in export.' );
        $catalog = $data['records'][0];
        $note = $data['records'][1];
        self::assertSame( 'catalog', $catalog['record_type'] );
        self::assertSame( $fixture['notebook'], $catalog['catalog']['notebook'][0]['id'] );
        self::assertSame( 'Governance', $catalog['catalog']['category'][0]['name'] );
        self::assertSame( 'note', $note['record_type'] );
        self::assertSame( 'note:' . $fixture['note'], $note['reference'] );
        self::assertSame( $fixture['blocks'], $note['content'] );
        self::assertSame( $fixture['notebook'], $note['notebook_id'] );
        self::assertSame( $fixture['category'], $note['category_id'] );
        self::assertSame( [ $fixture['tag'] ], $note['tag_ids'] );
        self::assertTrue( $note['favorite'] );
        self::assertTrue( $note['pinned'] );
        self::assertStringNotContainsString( 'NOTES-P1-FOREIGN-SECRET', $json );
        self::assertStringNotContainsString( 'Foreign title must not export', $json );
        self::assertArrayNotHasKey( 'share_mode', $note );
        self::assertArrayNotHasKey( 'secure_grant', $note );

        foreach ( [ 'owner_id' => $other, 'user_id' => $other, 'author' => $other ] as $key => $id ) {
            $denied = Engine::export_json( 'core-blueprint-notes', 'personal-notes', [ $key => $id ] );
            self::assertWPError( $denied, 'Caller-supplied identity overrides must never authorize export.' );
        }
        $not_importable = Engine::preview_json( $json, Foundation::MODE_CREATE_ONLY );
        self::assertWPError( $not_importable, 'The JSON export must not imply import authority.' );

        wp_set_current_user( $other );
        $other_json = Engine::export_json( 'core-blueprint-notes', 'personal-notes' );
        self::assertIsString( $other_json );
        self::assertStringNotContainsString( 'NOTES-P1-OWNER-ONLY-CONTENT', $other_json );
        self::assertStringContainsString( 'NOTES-P1-FOREIGN-SECRET', $other_json );

        wp_set_current_user( 0 );
        self::assertWPError( Engine::export_json( 'core-blueprint-notes', 'personal-notes' ),
            'Anonymous actors must not export any Notes records.' );
        self::assertSame( $fixture['blocks'], get_post( $fixture['note'] )->post_content,
            'Export must be read-only.' );
    }

    public function test_real_base_engine_refuses_broken_catalog_relations_without_partial_json(): void {
        $fixture = $this->fixture();
        wp_set_current_user( $fixture['owner'] );
        self::assertNotFalse( update_post_meta( $fixture['note'], Catalog::NOTEBOOK, str_repeat( 'f', 32 ) ) );
        $denied = Engine::export_json( 'core-blueprint-notes', 'personal-notes' );
        self::assertWPError( $denied, 'A broken catalog relationship must not produce a partial Base envelope.' );
        self::assertSame( 'cb_notes_export_invalid_relation', $denied->get_error_code() );
    }
}

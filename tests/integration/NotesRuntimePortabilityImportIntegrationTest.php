<?php
declare(strict_types=1);

use CB\Notes\Library\Markers;
use CB\Notes\Organization\Catalog;
use CB\Notes\Plugin;
use CB\Notes\Portability\ArchivePreflight;
use CB\Notes\Portability\ImportJournal;
use CB\Notes\Portability\ImportSession;
use CB\Notes\Portability\NotesEntity;
use CoreBlueprint\Core\DataExchange\Engine;
use CoreBlueprint\Core\DataExchange\Foundation;

/**
 * P2-B real WordPress / MariaDB acceptance for the DORMANT Notes import path.
 *
 * Deliberately exercises private session-gated entity callbacks, never
 * advertises Base SUPPORT_IMPORT or registers any owner-visible apply action.
 * This is a two-OWNER test inside ONE disposable WP installation. A separate
 * source/destination WordPress-site round-trip remains required for Golden.
 */
final class NotesRuntimePortabilityImportIntegrationTest extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();
        self::assertSame(
            1,
            preg_match('/^core_blueprint_notes_int_[a-f0-9]{12}_test$/D', (string) DB_NAME),
            'Do not run portability mutations outside a runner-owned Notes schema.'
        );
        self::assertSame('127.0.0.1:3307', (string) DB_HOST);
        Plugin::grant_roles();
        Catalog::register_meta();
        self::assertTrue(post_type_exists(Plugin::TYPE));
    }

    /** @return array{source:int,target:int,json:string,records:array,digest:string,note_ref:string,blocks:string} */
    private function fixture(): array {
        $source = self::factory()->user->create(['role' => 'editor']);
        $target = self::factory()->user->create(['role' => 'editor']);
        wp_set_current_user($source);
        $notebook = str_repeat('a', 32);
        $category = str_repeat('b', 32);
        $tag = str_repeat('c', 32);
        $catalog = [
            'notebook' => [['id' => $notebook, 'name' => 'Notes migration']],
            'category' => [['id' => $category, 'name' => 'Governance']],
            'tag' => [['id' => $tag, 'name' => 'Privacy']],
        ];
        self::assertNotFalse(update_user_meta($source, Catalog::USER_META, $catalog));
        $blocks = '<!-- wp:paragraph --><p>Privacy &amp; eigen regie</p><!-- /wp:paragraph -->';
        $note_id = self::factory()->post->create([
            'post_type' => Plugin::TYPE,
            'post_author' => $source,
            'post_status' => 'private',
            'post_title' => 'Migratie \u{00E9}n governance',
            'post_content' => $blocks,
        ]);
        self::assertGreaterThan(0, $note_id);
        self::assertNotFalse(update_post_meta($note_id, Catalog::NOTEBOOK, $notebook));
        self::assertNotFalse(update_post_meta($note_id, Catalog::CATEGORY, $category));
        self::assertNotFalse(update_post_meta($note_id, Catalog::TAGS, [$tag]));
        self::assertNotFalse(update_post_meta($note_id, Markers::FAVORITE, '1'));
        self::assertNotFalse(update_post_meta($note_id, Markers::PINNED, '1'));
        $json = Engine::export_json('core-blueprint-notes', 'personal-notes');
        self::assertIsString($json, 'Real Base must export the complete Notes envelope.');
        $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        self::assertCount(2, $decoded['records']);
        $preflight = ArchivePreflight::inspect($decoded['records'], 1);
        self::assertIsArray($preflight);
        return [
            'source' => $source,
            'target' => $target,
            'json' => $json,
            'records' => $decoded['records'],
            'digest' => $preflight['digest'],
            'note_ref' => $decoded['records'][1]['reference'],
            'blocks' => $blocks,
        ];
    }

    private function target_posts(int $owner): array {
        return get_posts([
            'post_type' => Plugin::TYPE,
            'post_status' => ['draft', 'private', 'pending', 'future', 'publish'],
            'author' => $owner,
            'posts_per_page' => 100,
            'fields' => 'ids',
            'suppress_filters' => true,
        ]);
    }

    public function test_real_owner_import_creates_remapped_private_note_once(): void {
        $f = $this->fixture();
        wp_set_current_user($f['target']);
        self::assertSame([], $this->target_posts($f['target']));
        $entity = new NotesEntity();

        $result = ImportSession::with_validated_json(
            $f['json'],
            function (array $context) use ($f, $entity): bool {
                self::assertTrue($entity->can_import($context));
                self::assertFalse($entity->can_import(['cb_notes_import_ticket' => 'forged']));
                self::assertFalse($entity->can_import());
                $catalog_plan = $entity->plan_import(
                    $f['records'][0], Foundation::MODE_CREATE_ONLY, 1, $context
                );
                $note_plan = $entity->plan_import(
                    $f['records'][1], Foundation::MODE_CREATE_ONLY, 1, $context
                );
                self::assertIsArray($catalog_plan);
                self::assertIsArray($note_plan);
                self::assertSame(Foundation::OP_CREATE, $catalog_plan['operation']);
                self::assertSame(Foundation::OP_CREATE, $note_plan['operation']);
                self::assertSame('private', $note_plan['payload']['mapped']['status']);
                self::assertNotSame(
                    $f['records'][1]['notebook_id'],
                    $note_plan['payload']['mapped']['notebook_id'],
                    'Destination must not reuse the foreign source catalog ID.'
                );
                self::assertWPError($entity->apply_import($note_plan, []));
                self::assertSame(
                    ['reference' => 'catalog:personal'],
                    $entity->apply_import($catalog_plan, $context)
                );
                self::assertSame(
                    ['reference' => $f['note_ref']],
                    $entity->apply_import($note_plan, $context)
                );
                return true;
            }
        );
        self::assertTrue($result);
        self::assertFalse($entity->can_import(), 'Request capability must disappear after callback.');

        $posts = $this->target_posts($f['target']);
        self::assertCount(1, $posts);
        $id = (int) $posts[0];
        $saved = get_post($id);
        self::assertInstanceOf(WP_Post::class, $saved);
        self::assertSame($f['target'], (int) $saved->post_author);
        self::assertSame('private', $saved->post_status);
        self::assertSame('Migratie \u{00E9}n governance', $saved->post_title);
        self::assertSame($f['blocks'], $saved->post_content);
        $catalog = Catalog::catalog($f['target']);
        self::assertSame('Notes migration', $catalog['notebook'][0]['name']);
        self::assertSame('Governance', $catalog['category'][0]['name']);
        self::assertSame('Privacy', $catalog['tag'][0]['name']);
        self::assertSame($catalog['notebook'][0]['id'], get_post_meta($id, Catalog::NOTEBOOK, true));
        self::assertSame($catalog['category'][0]['id'], get_post_meta($id, Catalog::CATEGORY, true));
        self::assertSame([$catalog['tag'][0]['id']], get_post_meta($id, Catalog::TAGS, true));
        self::assertSame('1', get_post_meta($id, Markers::FAVORITE, true));
        self::assertSame('1', get_post_meta($id, Markers::PINNED, true));
        self::assertSame(
            ['state' => 'complete', 'post_id' => $id],
            ImportJournal::receipt($f['target'], $f['digest'], $f['note_ref'])
        );
        $all_meta = get_post_custom($id);
        self::assertArrayNotHasKey('_cb_notes_public_snapshot', $all_meta);
        self::assertArrayNotHasKey('_cb_notes_secure_grant', $all_meta);

        $repeat = ImportSession::with_validated_json(
            $f['json'],
            static function (array $context) use ($f, $entity): bool {
                $catalog = $entity->plan_import($f['records'][0], Foundation::MODE_CREATE_ONLY, 1, $context);
                $note = $entity->plan_import($f['records'][1], Foundation::MODE_CREATE_ONLY, 1, $context);
                self::assertSame(Foundation::OP_SKIP, $catalog['operation']);
                self::assertSame(Foundation::OP_SKIP, $note['operation']);
                self::assertWPError($entity->apply_import($note, $context));
                return true;
            }
        );
        self::assertTrue($repeat, 'Verified import replay should be an explicit no-op plan.');
        self::assertCount(1, $this->target_posts($f['target']));

        $target_json = Engine::export_json('core-blueprint-notes', 'personal-notes');
        self::assertIsString($target_json);
        $destination = json_decode($target_json, true, 32, JSON_THROW_ON_ERROR);
        self::assertCount(2, $destination['records']);
        self::assertSame($f['blocks'], $destination['records'][1]['content']);
        self::assertSame('private', $destination['records'][1]['status']);
        self::assertSame(['Privacy'], array_column($destination['records'][0]['catalog']['tag'], 'name'));
        self::assertNotSame(
            $f['records'][1]['notebook_id'],
            $destination['records'][1]['notebook_id']
        );
    }

    public function test_invalid_relations_and_other_owner_cannot_begin_import(): void {
        $f = $this->fixture();
        $other = self::factory()->user->create(['role' => 'editor']);
        wp_set_current_user(0);
        self::assertWPError(ImportSession::with_validated_json($f['json'], static fn(): bool => true));
        wp_set_current_user($f['target']);
        $malformed = json_decode($f['json'], true, 32, JSON_THROW_ON_ERROR);
        $malformed['records'][1]['tag_ids'] = [str_repeat('e', 32)];
        $bad_json = json_encode($malformed, JSON_THROW_ON_ERROR);
        $called = false;
        $refused = ImportSession::with_validated_json($bad_json, static function () use (&$called): bool {
            $called = true;
            return true;
        });
        self::assertWPError($refused);
        self::assertFalse($called, 'No callback may run when any catalog relationship is unresolved.');
        self::assertCount(0, $this->target_posts($f['target']));

        // A target owner with a same-name, unrelated catalog MUST NOT merge.
        wp_set_current_user($other);
        self::assertNotFalse(update_user_meta($other, Catalog::USER_META, [
            'notebook' => [['id' => str_repeat('f', 32), 'name' => 'Notes migration']],
            'category' => [], 'tag' => [],
        ]));
        self::assertWPError(ImportSession::with_validated_json($f['json'], static fn(): bool => true));
        self::assertCount(0, $this->target_posts($other));
        self::assertSame(str_repeat('f', 32), Catalog::catalog($other)['notebook'][0]['id']);
    }

    public function test_real_pending_journal_blocks_retry_without_creating_duplicate(): void {
        $f = $this->fixture();
        wp_set_current_user($f['target']);
        $key = ImportJournal::reserve($f['target'], $f['digest'], $f['note_ref']);
        self::assertIsString($key);
        self::assertSame('pending', get_option($key)['state']);
        self::assertWPError(ImportJournal::reserve($f['target'], $f['digest'], $f['note_ref']));
        for ($i = 0; $i < 2; ++$i) {
            $called = false;
            $denied = ImportSession::with_validated_json(
                $f['json'],
                static function () use (&$called): bool {
                    $called = true;
                    return true;
                }
            );
            self::assertWPError($denied);
            self::assertSame('cb_notes_import_journal_needs_review', $denied->get_error_code());
            self::assertFalse($called);
        }
        self::assertCount(0, $this->target_posts($f['target']));
        self::assertSame('pending', get_option($key)['state'], 'Do not auto-delete ambiguous intent.');
    }

    public function test_base_engine_rejects_public_import_even_during_private_preview(): void {
        $f = $this->fixture();
        wp_set_current_user($f['target']);
        self::assertWPError(Engine::preview_json($f['json'], Foundation::MODE_CREATE_ONLY));
        $result = ImportSession::with_validated_json(
            $f['json'],
            static function (array $context) use ($f): bool {
                self::assertWPError(
                    Engine::preview_json($f['json'], Foundation::MODE_CREATE_ONLY, $context),
                    'The public registry must not advertise import while the writer is under review.'
                );
                self::assertWPError(
                    Engine::apply_json($f['json'], Foundation::MODE_CREATE_ONLY, str_repeat('f', 64), $context)
                );
                return true;
            }
        );
        self::assertTrue($result);
        self::assertCount(0, $this->target_posts($f['target']));
    }

    public function test_real_wordpress_insert_failure_leaves_reviewable_pending_intent(): void {
        $f = $this->fixture();
        wp_set_current_user($f['target']);
        $entity = new NotesEntity();
        $result = ImportSession::with_validated_json(
            $f['json'],
            function (array $context) use ($f, $entity): bool {
                $catalog = $entity->plan_import($f['records'][0], Foundation::MODE_CREATE_ONLY, 1, $context);
                $note = $entity->plan_import($f['records'][1], Foundation::MODE_CREATE_ONLY, 1, $context);
                self::assertSame(
                    ['reference' => 'catalog:personal'],
                    $entity->apply_import($catalog, $context)
                );
                // Force the genuine wp_insert_post() API to reject an insert
                // after the durable journal intent has already been written.
                $refuse = static fn(): bool => true;
                add_filter('wp_insert_post_empty_content', $refuse, 999, 2);
                try {
                    $failed = $entity->apply_import($note, $context);
                    self::assertWPError($failed);
                } finally {
                    remove_filter('wp_insert_post_empty_content', $refuse, 999);
                }
                return true;
            }
        );
        self::assertTrue($result);
        $receipt = ImportJournal::receipt($f['target'], $f['digest'], $f['note_ref']);
        self::assertWPError($receipt);
        self::assertSame('cb_notes_import_journal_needs_review', $receipt->get_error_code());
        self::assertCount(0, $this->target_posts($f['target']));
        self::assertWPError(ImportSession::with_validated_json($f['json'], static fn(): bool => true));
    }
}

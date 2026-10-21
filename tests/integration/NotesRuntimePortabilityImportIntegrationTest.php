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
            'post_title' => 'Migratie én governance',
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
                wp_set_current_user($f['source']);
                self::assertFalse($entity->can_import($context), 'Changing actors must invalidate an import ticket.');
                wp_set_current_user($f['target']);
                self::assertTrue($entity->can_import($context));
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
                $this->assertWPError($entity->apply_import($note_plan, []));
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
        self::assertSame('Migratie én governance', $saved->post_title);
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
            function (array $context) use ($f, $entity): bool {
                $catalog = $entity->plan_import($f['records'][0], Foundation::MODE_CREATE_ONLY, 1, $context);
                $note = $entity->plan_import($f['records'][1], Foundation::MODE_CREATE_ONLY, 1, $context);
                self::assertSame(Foundation::OP_SKIP, $catalog['operation']);
                self::assertSame(Foundation::OP_SKIP, $note['operation']);
                $this->assertWPError($entity->apply_import($note, $context));
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
        self::assertSame(
            $f['records'][1]['created_gmt'],
            $destination['records'][1]['created_gmt'],
            'Archive round-trip must preserve note creation time.'
        );
        self::assertSame(
            $f['records'][1]['modified_gmt'],
            $destination['records'][1]['modified_gmt'],
            'Archive round-trip must preserve last modification time or explicitly fail acceptance.'
        );
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
        $this->assertWPError(ImportSession::with_validated_json($f['json'], static fn(): bool => true));
        wp_set_current_user($f['target']);
        $malformed = json_decode($f['json'], true, 32, JSON_THROW_ON_ERROR);
        $malformed['records'][1]['tag_ids'] = [str_repeat('e', 32)];
        $bad_json = json_encode($malformed, JSON_THROW_ON_ERROR);
        $called = false;
        $refused = ImportSession::with_validated_json($bad_json, static function () use (&$called): bool {
            $called = true;
            return true;
        });
        $this->assertWPError($refused);
        self::assertFalse($called, 'No callback may run when any catalog relationship is unresolved.');
        self::assertCount(0, $this->target_posts($f['target']));

        // A target owner with a same-name, unrelated catalog MUST NOT merge.
        wp_set_current_user($other);
        self::assertNotFalse(update_user_meta($other, Catalog::USER_META, [
            'notebook' => [['id' => str_repeat('f', 32), 'name' => 'Notes migration']],
            'category' => [], 'tag' => [],
        ]));
        $this->assertWPError(ImportSession::with_validated_json($f['json'], static fn(): bool => true));
        self::assertCount(0, $this->target_posts($other));
        self::assertSame(str_repeat('f', 32), Catalog::catalog($other)['notebook'][0]['id']);
    }

    public function test_real_pending_journal_blocks_retry_without_creating_duplicate(): void {
        $f = $this->fixture();
        wp_set_current_user($f['target']);
        $key = ImportJournal::reserve($f['target'], $f['digest'], $f['note_ref']);
        self::assertIsString($key);
        self::assertSame('pending', get_option($key)['state']);
        $this->assertWPError(ImportJournal::reserve($f['target'], $f['digest'], $f['note_ref']));
        for ($i = 0; $i < 2; ++$i) {
            $called = false;
            $denied = ImportSession::with_validated_json(
                $f['json'],
                static function () use (&$called): bool {
                    $called = true;
                    return true;
                }
            );
            $this->assertWPError($denied);
            self::assertSame('cb_notes_import_journal_needs_review', $denied->get_error_code());
            self::assertFalse($called);
        }
        self::assertCount(0, $this->target_posts($f['target']));
        self::assertSame('pending', get_option($key)['state'], 'Do not auto-delete ambiguous intent.');
    }

    public function test_real_catalog_write_failure_stops_before_first_note_or_journal(): void {
        $f = $this->fixture();
        wp_set_current_user($f['target']);
        $entity = new NotesEntity();
        $result = ImportSession::with_validated_json(
            $f['json'],
            function (array $context) use ($f, $entity): bool {
                $catalog = $entity->plan_import($f['records'][0], Foundation::MODE_CREATE_ONLY, 1, $context);
                $note = $entity->plan_import($f['records'][1], Foundation::MODE_CREATE_ONLY, 1, $context);
                self::assertIsArray($catalog);
                self::assertIsArray($note);
                $deny = static fn(): bool => false;
                add_filter('add_user_metadata', $deny, 999, 5);
                try {
                    $failed = $entity->apply_import($catalog, $context);
                    $this->assertWPError($failed);
                    self::assertSame('cb_notes_import_catalog_write_failed', $failed->get_error_code());
                } finally {
                    remove_filter('add_user_metadata', $deny, 999);
                }
                $blocked = $entity->apply_import($note, $context);
                $this->assertWPError($blocked, 'No note may be written without its destination labels.');
                self::assertSame('cb_notes_import_catalog_stale', $blocked->get_error_code());
                return true;
            }
        );
        self::assertTrue($result);
        self::assertSame('', get_user_meta($f['target'], Catalog::USER_META, true));
        self::assertCount(0, $this->target_posts($f['target']));
        self::assertSame(
            ['state' => 'new'],
            ImportJournal::receipt($f['target'], $f['digest'], $f['note_ref']),
            'A catalog failure must not reserve a note that was never attempted.'
        );
    }

    public function test_missing_receipt_meta_after_post_insert_blocks_automatic_retry(): void {
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
                // Suppress just the private import receipt. WordPress may still
                // insert the post and other meta; returning "complete" would
                // be incorrect because a recovery lookup cannot prove ownership.
                $drop = static function ($short_circuit, $object_id, $key) {
                    return $key === ImportJournal::META ? false : $short_circuit;
                };
                add_filter('add_post_metadata', $drop, 999, 5);
                try {
                    $failed = $entity->apply_import($note, $context);
                    $this->assertWPError($failed);
                    self::assertSame('cb_notes_import_create_uncertain', $failed->get_error_code());
                } finally {
                    remove_filter('add_post_metadata', $drop, 999);
                }
                return true;
            }
        );
        self::assertTrue($result);
        $posts = $this->target_posts($f['target']);
        self::assertCount(1, $posts, 'WordPress inserted a note despite metadata failure.');
        $post_id = (int)$posts[0];
        self::assertSame('', get_post_meta($post_id, ImportJournal::META, true));
        $pending = ImportJournal::receipt($f['target'], $f['digest'], $f['note_ref']);
        $this->assertWPError($pending);
        self::assertSame('cb_notes_import_journal_needs_review', $pending->get_error_code());
        $this->assertWPError(ImportSession::with_validated_json($f['json'], static fn(): bool => true));
        self::assertCount(1, $this->target_posts($f['target']), 'Never duplicate an unverified import.');
    }

    public function test_owner_markdown_zip_keeps_gutenberg_source_and_excludes_foreign_notes(): void {
        $f = $this->fixture();
        self::assertTrue(
            class_exists(\ZipArchive::class),
            'Markdown ZIP is a required product feature; a missing PHP zip extension blocks acceptance.'
        );
        wp_set_current_user($f['source']);
        $bytes = \CB\Notes\Portability\OwnerExport::markdown_zip();
        self::assertIsString($bytes, 'Owner must be able to generate a complete private ZIP.');
        self::assertGreaterThan(100, strlen($bytes));
        $temp = tempnam(sys_get_temp_dir(), 'cb_notes_test_zip_');
        self::assertIsString($temp);
        try {
            self::assertSame(strlen($bytes), file_put_contents($temp, $bytes));
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($temp) === true);
            try {
                $name = substr($f['note_ref'], strlen('note:'));
                $note = $zip->getFromName('notes/note-' . $name . '.md');
                $raw = $zip->getFromName('sources/note-' . $name . '.gutenberg.txt');
                $manifest = $zip->getFromName('manifest.json');
                self::assertIsString($note);
                self::assertSame($f['blocks'], $raw);
                self::assertIsString($manifest);
                self::assertStringContainsString('notebook: "Notes migration"', $note);
                self::assertStringContainsString('  - "Privacy"', $note);
                self::assertStringNotContainsString('cb_notes_secure_grant', $manifest);
                $metadata = json_decode($manifest, true, 32, JSON_THROW_ON_ERROR);
                self::assertSame(1, $metadata['note_count']);
                self::assertSame('core-blueprint-notes-markdown', $metadata['format']);
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($temp);
        }

        // A second authenticated owner cannot export the source owner's Note.
        wp_set_current_user($f['target']);
        $other = \CB\Notes\Portability\OwnerExport::markdown_zip();
        self::assertIsString($other);
        self::assertNotSame($bytes, $other, 'Independent owners must not share the same archive.');
        wp_set_current_user(0);
        $this->assertWPError(\CB\Notes\Portability\OwnerExport::markdown_zip());
    }

    public function test_base_engine_rejects_public_import_even_during_private_preview(): void {
        $f = $this->fixture();
        wp_set_current_user($f['target']);
        $this->assertWPError(Engine::preview_json($f['json'], Foundation::MODE_CREATE_ONLY));
        $result = ImportSession::with_validated_json(
            $f['json'],
            function (array $context) use ($f): bool {
                $this->assertWPError(
                    Engine::preview_json($f['json'], Foundation::MODE_CREATE_ONLY, $context),
                    'The public registry must not advertise import while the writer is under review.'
                );
                $this->assertWPError(
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
                    $this->assertWPError($failed);
                } finally {
                    remove_filter('wp_insert_post_empty_content', $refuse, 999);
                }
                return true;
            }
        );
        self::assertTrue($result);
        $receipt = ImportJournal::receipt($f['target'], $f['digest'], $f['note_ref']);
        $this->assertWPError($receipt);
        self::assertSame('cb_notes_import_journal_needs_review', $receipt->get_error_code());
        self::assertCount(0, $this->target_posts($f['target']));
        $this->assertWPError(ImportSession::with_validated_json($f['json'], static fn(): bool => true));
    }
}

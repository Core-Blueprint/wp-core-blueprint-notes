<?php
declare(strict_types=1);

namespace CB\Notes\Organization {
    final class Catalog {
        public static function valid_id(mixed $id): bool {
            return is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) === 1;
        }
        public static function catalog(int $owner): array {
            return $GLOBALS['target_catalog'][$owner] ?? ['notebook' => [], 'category' => [], 'tag' => []];
        }
    }
}
namespace CB\Notes\Portability {
    final class MappingSeed {
        public static function read(): string { return str_repeat('S', 32); }
    }
    final class ImportJournal {
        public static function receipt(int $owner, string $digest, string $reference): array|\WP_Error {
            if (($GLOBALS['mock_receipt_state'] ?? 'new') === 'pending') {
                return new \WP_Error('cb_notes_import_journal_needs_review', 'Review required.');
            }
            return ($GLOBALS['mock_receipt_state'] ?? 'new') === 'complete'
                ? ['state' => 'complete', 'post_id' => 101]
                : ['state' => 'new'];
        }
    }
}
namespace {
    final class WP_Error {
        public function __construct(public string $code, public string $message) {}
    }
    $GLOBALS['actor'] = 7;
    $GLOBALS['allowed'] = true;
    $GLOBALS['target_catalog'] = [];
    function get_current_user_id(): int { return $GLOBALS['actor']; }
    function current_user_can(string $cap): bool { return $cap === 'cb_notes_edit' && $GLOBALS['allowed']; }
    function wp_salt(string $scheme): string { return str_repeat('S', 64); }
    function verify(bool $pass, string $desc): void {
        if (!$pass) { fwrite(STDERR, "FAIL: $desc\n"); exit(1); }
    }
    require dirname(__DIR__) . '/src/Portability/ArchivePreflight.php';
    require dirname(__DIR__) . '/src/Portability/ImportPreview.php';
    require dirname(__DIR__) . '/src/Portability/ImportSession.php';
    use CB\Notes\Portability\ImportSession;
    $catalog = ['record_type' => 'catalog', 'reference' => 'catalog:personal',
        'catalog' => ['notebook' => [], 'category' => [], 'tag' => []]];
    $note = ['record_type' => 'note', 'reference' => 'note:11', 'title' => 'Owner',
        'content' => '<!-- wp:paragraph --><p>Safe</p><!-- /wp:paragraph -->',
        'status' => 'private', 'created_gmt' => '2026-10-19 11:00:00',
        'modified_gmt' => '2026-10-19 11:00:00', 'notebook_id' => '',
        'category_id' => '', 'tag_ids' => [], 'favorite' => false, 'pinned' => false];
    $envelope = ['format' => 'core-blueprint-data-exchange', 'format_version' => 1,
        'extension_id' => 'core-blueprint-notes', 'entity' => 'personal-notes',
        'schema_version' => 1, 'exported_at' => '2026-10-19T12:00:00Z',
        'records' => [$catalog, $note]];
    $json = json_encode($envelope, JSON_THROW_ON_ERROR);
    verify(!ImportSession::authorized(['cb_notes_import_ticket' => 'forged']), 'no ambient capability');
    $observed = ImportSession::with_validated_json($json, static function (array $context) use ($note): bool {
        verify(ImportSession::authorized($context), 'server-side context authenticated');
        verify(!ImportSession::authorized(['cb_notes_import_ticket' => 'forged']), 'forged token denied');
        $record = ImportSession::record($note, $context);
        verify(is_array($record) && $record['source'] === $note
            && $record['preview']['create_count'] === 1, 'full graph bound to record');
        $altered = $note;
        $altered['title'] = 'Wrong';
        verify(ImportSession::record($altered, $context) instanceof WP_Error,
            'per-record modification denied');
        verify(ImportSession::with_validated_json('{}', static fn(): bool => true)
            instanceof WP_Error, 'nested session denied');
        $GLOBALS['actor'] = 9;
        verify(!ImportSession::authorized($context), 'actor switch denied');
        $GLOBALS['actor'] = 7;
        $GLOBALS['allowed'] = false;
        verify(!ImportSession::authorized($context), 'capability loss denied');
        $GLOBALS['allowed'] = true;
        return true;
    });
    verify($observed === true, 'validated session executes once');
    $GLOBALS['mock_receipt_state'] = 'complete';
    $skip = ImportSession::with_validated_json($json,
        static fn(array $context): array|\WP_Error =>
            ImportSession::record($note, $context));
    verify(is_array($skip) && $skip['preview']['skip_count'] === 1,
        'owner-verified completed receipt creates idempotent skip');
    $GLOBALS['mock_receipt_state'] = 'pending';
    verify(ImportSession::with_validated_json($json, static fn(): bool => true)
        instanceof WP_Error, 'interrupted earlier import fails before planning');
    unset($GLOBALS['mock_receipt_state']);

    verify(!ImportSession::authorized(['cb_notes_import_ticket' => 'forged']), 'session cleared');
    $bad = $envelope;
    $bad['records'][1]['notebook_id'] = str_repeat('f', 32);
    verify(ImportSession::with_validated_json(json_encode($bad), static fn(): bool => true)
        instanceof WP_Error, 'full archive invalid relation refuses before callback');
    $bad = $envelope;
    $bad['extension_id'] = 'foreign';
    verify(ImportSession::with_validated_json(json_encode($bad), static fn(): bool => true)
        instanceof WP_Error, 'unexpected provider refused');
    $bad = $envelope;
    $bad['records'][1]['share_key'] = 'private-key';
    verify(ImportSession::with_validated_json(json_encode($bad), static fn(): bool => true)
        instanceof WP_Error, 'share authorization rejected');
    $GLOBALS['actor'] = 0;
    verify(ImportSession::with_validated_json($json, static fn(): bool => true)
        instanceof WP_Error, 'anonymous denied');
    echo "Notes P2-B whole-import session authority: PASS (read-only)\n";
}

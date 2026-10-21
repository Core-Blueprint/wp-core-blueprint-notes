<?php
declare(strict_types=1);

namespace CoreBlueprint\Core\DataExchange {
    final class Foundation {
        public const MODE_CREATE_ONLY = 'create_only';
        public const OP_CREATE = 'create';
        public const OP_SKIP = 'skip';
        public const MAX_INPUT_BYTES = 10485760;
    }
    final class Engine {
        public static function preview_json(string $bytes, string $mode, array $context): array|\WP_Error {
            if ($context !== ['cb_notes_import_ticket' => 'server-only']
                || $mode !== Foundation::MODE_CREATE_ONLY) {
                return new \WP_Error('forbidden', 'No Notes session');
            }
            return [
                'valid' => true,
                'fingerprint' => hash('sha256', $bytes . ($GLOBALS['target_revision'] ?? 'v1')),
            ];
        }
        public static function apply_json(string $bytes, string $mode, string $fingerprint, array $context): array|\WP_Error {
            $plan = self::preview_json($bytes, $mode, $context);
            if ($plan instanceof \WP_Error || !hash_equals($plan['fingerprint'], $fingerprint)) {
                return new \WP_Error('stale', 'Target plan changed');
            }
            if ($GLOBALS['replay'] ?? false) {
                return ['status' => 'complete', 'record_count' => 2,
                    'applied_count' => 0, 'skipped_count' => 2,
                    'items' => [
                        ['index'=>0,'operation'=>'skip','reference'=>'catalog:personal'],
                        ['index'=>1,'operation'=>'skip','reference'=>'note:9'],
                    ]];
            }
            ++$GLOBALS['mutations'];
            return ['status' => 'complete', 'record_count' => 2,
                'applied_count' => 2, 'skipped_count' => 0,
                'items' => ($GLOBALS['truncate_result'] ?? false)
                    ? [['index'=>0,'operation'=>'create','reference'=>'catalog:personal']]
                    : [
                        ['index'=>0,'operation'=>'create','reference'=>'catalog:personal'],
                        ['index'=>1,'operation'=>'create','reference'=>'note:9'],
                    ]];
        }
    }
}
namespace CB\Notes\Portability {
    final class ImportSession {
        public static function with_validated_json(string $bytes, callable $callback): mixed {
            if ($bytes === '{}' || ($GLOBALS['actor'] ?? 0) !== 7) {
                return new \WP_Error('denied', 'Invalid whole file or user');
            }
            return $callback(['cb_notes_import_ticket' => 'server-only']);
        }
        public static function target_plan(): array {
            return [
                'create_count' => 1, 'skip_count' => 0, 'record_count' => 1,
                'catalog' => ['notebook' => [['id'=>'ab']]],
                'previous_catalog' => ($GLOBALS['replay'] ?? false)
                    ? ['notebook' => [['id'=>'ab']]] : ['notebook' => []],
                'plans' => [['source_reference' => 'note:9', 'operation' =>
                    ($GLOBALS['replay'] ?? false) ? 'skip' : 'create', 'warnings' => []]],
            ];
        }
    }
}
namespace {
    final class WP_Error {
        public function __construct(public string $code, public string $message) {}
    }
    $GLOBALS['actor'] = 7;
    $GLOBALS['mutations'] = 0;
    function get_current_user_id(): int { return $GLOBALS['actor']; }
    function current_user_can(string $cap): bool { return $cap === 'cb_notes_edit'; }
    function assert_flow(bool $yes, string $name): void {
        if (!$yes) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    }
    require dirname(__DIR__) . '/src/Portability/ImportFlow.php';
    use CB\Notes\Portability\ImportFlow;
    $archive = '{"records":"mock"}';
    $preview = ImportFlow::preview_json($archive);
    assert_flow(is_array($preview) && strlen($preview['base_fingerprint']) === 64
        && $preview['create_count'] === 1 && $GLOBALS['mutations'] === 0,
        'preview uses Base and never applies');
    assert_flow(ImportFlow::apply_json('{}', $preview['file_sha256'],
        $preview['base_fingerprint']) instanceof WP_Error && $GLOBALS['mutations'] === 0,
        'changed bytes denied before write');
    assert_flow(ImportFlow::apply_json($archive, str_repeat('f', 64),
        $preview['base_fingerprint']) instanceof WP_Error && $GLOBALS['mutations'] === 0,
        'wrong uploaded file hash rejected');
    assert_flow(ImportFlow::apply_json($archive, $preview['file_sha256'],
        str_repeat('a', 64)) instanceof WP_Error && $GLOBALS['mutations'] === 0,
        'forged Base fingerprint rejected');
    $GLOBALS['target_revision'] = 'changed';
    assert_flow(ImportFlow::apply_json($archive, $preview['file_sha256'],
        $preview['base_fingerprint']) instanceof WP_Error && $GLOBALS['mutations'] === 0,
        'changed destination invalidates preview');
    $GLOBALS['target_revision'] = 'v1';
    $result = ImportFlow::apply_json($archive, $preview['file_sha256'], $preview['base_fingerprint']);
    assert_flow(is_array($result) && $result['status'] === 'complete'
        && $result['applied_count'] === 1 && $result['skipped_count'] === 0
        && $GLOBALS['mutations'] === 1, 'only actually applied Notes counted, catalog excluded');
    $GLOBALS['replay'] = true;
    $skipped = ImportFlow::apply_json($archive, $preview['file_sha256'],
        $preview['base_fingerprint']);
    assert_flow(is_array($skipped) && $skipped['status'] === 'complete'
        && $skipped['applied_count'] === 0 && $skipped['skipped_count'] === 1
        && $GLOBALS['mutations'] === 1,
        'verified catalog plus Note SKIP must not cause false recovery or duplication');
    unset($GLOBALS['replay']);
    $GLOBALS['truncate_result'] = true;
    $ambiguous = ImportFlow::apply_json($archive, $preview['file_sha256'],
        $preview['base_fingerprint']);
    assert_flow(is_array($ambiguous) && $ambiguous['status'] === 'recovery'
        && $ambiguous['needs_review'] === true && $ambiguous['applied_count'] === 0,
        'truncated Base success must never become false green after writes');
    unset($GLOBALS['truncate_result']);
    $GLOBALS['actor'] = 0;
    assert_flow(ImportFlow::apply_json($archive, $preview['file_sha256'],
        $preview['base_fingerprint']) instanceof WP_Error && $GLOBALS['mutations'] === 1,
        'anonymous caller refused');
    echo "Notes PI-1B Base fingerprint flow smoke: PASS (mocked Base)\n";
}

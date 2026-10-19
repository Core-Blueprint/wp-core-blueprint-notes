<?php
declare(strict_types=1);

namespace CoreBlueprint\Core\DataExchange {
    final class Foundation {
        public const MODE_CREATE_ONLY = 'create_only';
        public const OP_CREATE = 'create';
        public const OP_SKIP = 'skip';
    }
}
namespace CB\Notes\Portability {
    final class ArchivePreflight { public const SCHEMA_VERSION = 1; }
    final class ImportSession {
        public static function record(array $record, array $context): array|\WP_Error {
            if (($context['ticket'] ?? '') !== 'trusted'
                || ($record['reference'] ?? '') !== 'note:11'
                    && ($record['reference'] ?? '') !== 'catalog:personal') {
                return new \WP_Error('cb_notes_import_untrusted', 'Unauthorized');
            }
            return [
                'preview' => [
                    'owner' => 3, 'archive_digest' => str_repeat('a', 64),
                    'catalog' => ['notebook' => [
                        ['id' => str_repeat('a', 32), 'name' => 'Work']
                    ], 'category' => [], 'tag' => []],
                    'previous_catalog' => ['notebook' => [], 'category' => [], 'tag' => []],
                    'plans' => [
                        ['source_reference' => 'note:11', 'operation' => 'create',
                            'title' => 'A', 'status' => 'private',
                            'notebook_id' => '', 'category_id' => '', 'tag_ids' => [],
                            'warnings' => []],
                    ],
                ],
            ];
        }
    }
}
namespace {
    final class WP_Error {
        public function __construct(public string $code, public string $message) {}
    }
    require dirname(__DIR__) . '/src/Portability/ImportPlanner.php';
    use CB\Notes\Portability\ImportPlanner;
    function expect_plan(bool $okay, string $message): void {
        if (!$okay) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    }
    $catalog = ['record_type' => 'catalog', 'reference' => 'catalog:personal'];
    $note = ['record_type' => 'note', 'reference' => 'note:11', 'title' => 'A'];
    $ctx = ['ticket' => 'trusted'];
    $c = ImportPlanner::plan_record($catalog, 'create_only', 1, $ctx);
    expect_plan(is_array($c) && $c['operation'] === 'create'
        && $c['reference'] === 'catalog:personal' && $c['payload']['owner'] === 3,
        'Base canonical catalog create plan');
    $n = ImportPlanner::plan_record($note, 'create_only', 1, $ctx);
    expect_plan(is_array($n) && $n['operation'] === 'create'
        && $n['payload']['source'] === $note && $n['payload']['mapped']['status'] === 'private',
        'Base canonical note create plan');
    expect_plan(ImportPlanner::plan_record($note, 'create_update', 1, $ctx) instanceof WP_Error,
        'update mode never allowed');
    expect_plan(ImportPlanner::plan_record($note, 'create_only', 2, $ctx) instanceof WP_Error,
        'foreign source schema denied');
    expect_plan(ImportPlanner::plan_record($note, 'create_only', 1, ['ticket' => 'forged'])
        instanceof WP_Error, 'cannot plan outside authorized full-graph session');
    $evil = $note;
    $evil['reference'] = 'note:12';
    expect_plan(ImportPlanner::plan_record($evil, 'create_only', 1, $ctx)
        instanceof WP_Error, 'no unbound record');
    echo "Notes P2-B Base per-record planning bridge: PASS (no writes)\n";
}

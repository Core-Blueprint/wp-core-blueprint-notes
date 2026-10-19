<?php
declare(strict_types=1);

namespace CB\Notes\Organization {
    final class Catalog {
        public static function valid_id(mixed $id): bool {
            return is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) === 1;
        }
    }
}
namespace {
    final class WP_Error {
        public function __construct(public string $code, public string $message) {}
    }
    require dirname(__DIR__) . '/src/Portability/ArchivePreflight.php';
    require dirname(__DIR__) . '/src/Portability/ImportPreview.php';

    use CB\Notes\Portability\ArchivePreflight;
    use CB\Notes\Portability\ImportPreview;

    function check_preview(bool $v, string $label): void {
        if (!$v) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    }
    $id = str_repeat('a', 32);
    $note = [
        'record_type' => 'note', 'reference' => 'note:42', 'title' => 'Migration',
        'content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->',
        'status' => 'publish', 'created_gmt' => '2026-10-19 12:00:00',
        'modified_gmt' => '2026-10-19 12:00:00',
        'notebook_id' => $id, 'category_id' => '', 'tag_ids' => [],
        'favorite' => true, 'pinned' => false,
    ];
    $checked = ArchivePreflight::inspect([[
        'record_type' => 'catalog', 'reference' => 'catalog:personal',
        'catalog' => ['notebook' => [['id' => $id, 'name' => 'Work']],
            'category' => [], 'tag' => []],
    ], $note]);
    check_preview(is_array($checked), 'full graph preflight first');
    $empty = ['notebook' => [], 'category' => [], 'tag' => []];
    $secret = str_repeat('s', 32);
    $preview = ImportPreview::plan($checked, $empty, 31, $secret);
    check_preview(is_array($preview) && $preview['create_count'] === 1
        && $preview['skip_count'] === 0 && $preview['plans'][0]['status'] === 'private'
        && count($preview['plans'][0]['warnings']) === 1, 'create-only and status downgrade visible');
    check_preview($preview['source_id_map']['notebook'][$id] !== $id
        && strlen($preview['source_id_map']['notebook'][$id]) === 32, 'source catalog ID remapped');
    check_preview($preview['fingerprint'] === ImportPreview::plan($checked, $empty, 31, $secret)['fingerprint'],
        'repeated preview stable with same target and owner');
    check_preview($preview['fingerprint'] !== ImportPreview::plan($checked, $empty, 32, $secret)['fingerprint'],
        'independent owner and identity');
    $tampered = $checked;
    $tampered['notes'][0]['title'] = 'Changed after preflight';
    check_preview(ImportPreview::plan($tampered, $empty, 31, $secret) instanceof WP_Error,
        'tampered inspection content rejected before mapping');
    $tampered = $checked;
    $tampered['digest'] = str_repeat('1', 64);
    check_preview(ImportPreview::plan($tampered, $empty, 31, $secret) instanceof WP_Error,
        'unbound caller digest rejected');
    $collision = $empty;
    $collision['notebook'][] = ['id' => str_repeat('c', 32), 'name' => 'Work'];
    $err = ImportPreview::plan($checked, $collision, 31, $secret);
    check_preview($err instanceof WP_Error && $err->code === 'cb_notes_import_catalog_collision',
        'same name never silently merges');
    $owned = ['notebook' => $preview['catalog']['notebook'], 'category' => [], 'tag' => []];
    $receipt = [$checked['digest'] . ':note:42' => 4001];
    $repeat = ImportPreview::plan($checked, $owned, 31, $secret, $receipt);
    check_preview(is_array($repeat) && $repeat['create_count'] === 0
        && $repeat['skip_count'] === 1 && $repeat['plans'][0]['target_id'] === 4001,
        'caller-verified receipt enables no-op idempotency');
    $invalidReceipt = ImportPreview::plan($checked, $owned, 31, $secret, ['x' => -1]);
    check_preview(is_array($invalidReceipt), 'unused external state cannot change source plan');
    $receipt[$checked['digest'] . ':note:42'] = '4001';
    check_preview(ImportPreview::plan($checked, $owned, 31, $secret, $receipt) instanceof WP_Error,
        'receipt type must be exact');
    check_preview(ImportPreview::plan($checked, $empty, 0, $secret) instanceof WP_Error,
        'unauthenticated principal denied');
    check_preview(ImportPreview::plan($checked, $empty, 31, 'weak') instanceof WP_Error,
        'keyed destination identity required');
    echo "Notes P2-A owner-bound import plan: PASS (dry-run only)\n";
}

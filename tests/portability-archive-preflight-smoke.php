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

    use CB\Notes\Portability\ArchivePreflight;

    function check(bool $yes, string $message): void {
        if (!$yes) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    }
    function expect_bad(array $records, string $code): void {
        $result = ArchivePreflight::inspect($records);
        check($result instanceof WP_Error && $result->code === $code, $code);
    }

    $notebook = str_repeat('a', 32);
    $category = str_repeat('b', 32);
    $tag = str_repeat('c', 32);
    $catalog = [
        'record_type' => 'catalog', 'reference' => 'catalog:personal',
        'catalog' => [
            'notebook' => [['id' => $notebook, 'name' => 'Work']],
            'category' => [['id' => $category, 'name' => 'Research']],
            'tag' => [['id' => $tag, 'name' => 'Governance']],
        ],
    ];
    $note = [
        'record_type' => 'note', 'reference' => 'note:11',
        'title' => 'Evidence & review',
        'content' => '<!-- wp:paragraph --><p>Prüfung</p><!-- /wp:paragraph -->',
        'status' => 'private', 'created_gmt' => '2026-10-16 10:00:00',
        'modified_gmt' => '2026-10-17 14:45:00',
        'notebook_id' => $notebook, 'category_id' => $category,
        'tag_ids' => [$tag], 'favorite' => true, 'pinned' => false,
    ];
    $records = [$catalog, $note];
    $valid = ArchivePreflight::inspect($records);
    check(is_array($valid) && $valid['count'] === 1
        && $valid['catalog']['notebook'][0]['name'] === 'Work'
        && count($valid['notes']) === 1
        && preg_match('/^[a-f0-9]{64}$/', $valid['digest']) === 1,
        'valid full graph accepted');
    check(ArchivePreflight::inspect([$catalog])['count'] === 0, 'empty notes catalog supported');
    check(ArchivePreflight::inspect($records, 2) instanceof WP_Error, 'unknown schema denied');

    $broken = $records;
    $broken[1]['notebook_id'] = str_repeat('d', 32);
    expect_bad($broken, 'cb_notes_portability_record');
    $broken = $records;
    $broken[1]['tag_ids'] = [$tag, $tag];
    expect_bad($broken, 'cb_notes_portability_relation');
    $broken = $records;
    $broken[] = $note;
    expect_bad($broken, 'cb_notes_portability_record');
    $broken = $records;
    $broken[1]['share_key'] = 'evil';
    expect_bad($broken, 'cb_notes_portability_record');
    $broken = $records;
    $broken[1]['favorite'] = '1';
    expect_bad($broken, 'cb_notes_portability_record');
    $broken = $records;
    $broken[1]['status'] = 'trash';
    expect_bad($broken, 'cb_notes_portability_record');
    $broken = $records;
    $broken[0]['catalog']['notebook'][] = ['id' => str_repeat('f', 32), 'name' => 'work'];
    expect_bad($broken, 'cb_notes_portability_catalog_collision');
    $broken = $records;
    $broken[0]['catalog']['notebook'][0]['name'] = ' Work ';
    expect_bad($broken, 'cb_notes_portability_catalog');
    $broken = $records;
    $broken[0]['catalog']['notebook'][0]['name'] = 'Café';
    check(is_array(ArchivePreflight::inspect($broken)),
        'UTF-8 names accepted even when optional mbstring is absent');
    $broken = $records;
    $broken[0]['catalog']['notebook'][0]['name'] = str_repeat('é', 81);
    expect_bad($broken, 'cb_notes_portability_catalog');
    $draft = $records;
    $draft[1]['status'] = 'draft';
    $draft[1]['created_gmt'] = '0000-00-00 00:00:00';
    check(is_array(ArchivePreflight::inspect($draft)), 'WordPress unset draft GMT date preserved');
    $broken = $records;
    $broken[1]['created_gmt'] = '2026-02-31 12:00:00';
    expect_bad($broken, 'cb_notes_portability_record');
    $broken = $records;
    $broken[1]['reference'] = 'note:0';
    expect_bad($broken, 'cb_notes_portability_record');
    $broken = $records;
    unset($broken[0]['catalog']['category']);
    expect_bad($broken, 'cb_notes_portability_catalog');
    check(ArchivePreflight::inspect(array_fill(0, 5001, $catalog)) instanceof WP_Error,
        'over-limit refused without truncation');

    echo "Notes complete archive P2-A preflight: PASS (no mutations)\n";
}

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
    require dirname(__DIR__) . '/src/Portability/MarkdownArchive.php';

    use CB\Notes\Portability\MarkdownArchive;
    function test_markdown(bool $pass, string $desc): void {
        if (!$pass) { fwrite(STDERR, "FAIL: $desc\n"); exit(1); }
    }
    $notebook = str_repeat('a', 32);
    $tag = str_repeat('b', 32);
    $catalog = [
        'record_type' => 'catalog', 'reference' => 'catalog:personal',
        'catalog' => [
            'notebook' => [['id' => $notebook, 'name' => 'Strategy']],
            'category' => [], 'tag' => [['id' => $tag, 'name' => 'AI literacy']],
        ],
    ];
    $content = '<!-- wp:heading --><h2>Roadmap &amp; ethics</h2><!-- /wp:heading -->'
        . '<!-- wp:paragraph --><p>Respect &amp; trust</p><!-- /wp:paragraph -->';
    $note = [
        'record_type' => 'note', 'reference' => 'note:11', 'title' => 'Roadmap',
        'content' => $content, 'status' => 'private', 'created_gmt' => '2026-10-19 11:00:00',
        'modified_gmt' => '2026-10-19 12:00:00', 'notebook_id' => $notebook,
        'category_id' => '', 'tag_ids' => [$tag], 'favorite' => false, 'pinned' => true,
    ];
    $files = MarkdownArchive::files([$catalog, $note]);
    test_markdown(is_array($files) && count($files) === 3, 'manifest/Markdown/Gutenberg source files produced');
    test_markdown(isset($files['notes/note-11.md'], $files['sources/note-11.gutenberg.txt']),
        'only safe fixed archive paths');
    test_markdown($files['sources/note-11.gutenberg.txt'] === $content,
        'raw Gutenberg serialization preserved exactly');
    test_markdown(str_contains($files['notes/note-11.md'], 'notebook: "Strategy"')
        && str_contains($files['notes/note-11.md'], '  - "AI literacy"')
        && str_contains($files['notes/note-11.md'], 'Respect &amp; trust'),
        'metadata and readable content present');
    test_markdown(!str_contains(implode('', $files), 'share_grant'),
        'no authority invented in Markdown archive');
    $manifest = json_decode($files['manifest.json'], true, 32, JSON_THROW_ON_ERROR);
    test_markdown($manifest['note_count'] === 1 && count($manifest['excluded']) > 0,
        'manifest shows excluded fields and nonlossless limitation');
    $bad = $note;
    $bad['notebook_id'] = str_repeat('f', 32);
    test_markdown(MarkdownArchive::files([$catalog, $bad]) instanceof WP_Error,
        'foreign catalog relationships refuse export');
    if (!class_exists('ZipArchive')) {
        $result = MarkdownArchive::zip([$catalog, $note]);
        test_markdown($result instanceof WP_Error && $result->code === 'cb_notes_markdown_zip_unavailable',
            'absent ZIP extension fails clearly without filesystem side effects');
    } else {
        $bytes = MarkdownArchive::zip([$catalog, $note]);
        test_markdown(is_string($bytes) && strlen($bytes) > 100, 'private ZIP assembled');
    }
    echo "Notes Markdown Archive P3-B pure exporter: PASS\n";
}

<?php
declare(strict_types=1);

// Isolated view-logic test; not a WordPress runtime privacy test.
namespace CB\Notes {
    final class Plugin { public const TYPE = 'cb_note'; }
}

namespace {
    define('ABSPATH', __DIR__ . '/');

    function __(string $message, string $domain): string { return $message; }
    function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
    function esc_url(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
    function admin_url(string $path = ''): string { return '/wp-admin/' . $path; }
    function add_query_arg(array $query, string $url): string {
        return $url . '?' . http_build_query($query);
    }

    require_once dirname(__DIR__) . '/src/Library/Page.php';
    use CB\Notes\Library\Page;

    function verify(bool $condition, string $name): void {
        if (!$condition) {
            fwrite(STDERR, "FAIL Library empty state: {$name}\n");
            exit(1);
        }
    }

    function render_state(array $changes, int $found = 0): string {
        $state = array_merge([
            'search' => '', 'view' => '', 'notebook' => '', 'category' => '', 'tag' => '',
            'sort' => 'modified', 'paged' => 1, 'invalid' => false,
        ], $changes);
        ob_start();
        $reflection = new \ReflectionMethod(Page::class, 'render_empty_state');
        $reflection->invoke(null, $state, $found, '/wp-admin/post-new.php?post_type=cb_note');
        return (string)ob_get_clean();
    }

    $html = render_state([]);
    verify(str_contains($html, 'Your Library is empty') && str_contains($html, 'New note'), 'first note guidance');
    verify(str_contains($html, 'post-new.php'), 'new note route');

    $html = render_state(['notebook' => str_repeat('a', 32)]);
    verify(str_contains($html, 'This notebook is empty') && str_contains($html, 'select this notebook'), 'empty notebook guidance');

    $html = render_state(['view' => 'favorites']);
    verify(str_contains($html, 'No favorites yet'), 'favorite empty state');

    $html = render_state(['view' => 'pinned']);
    verify(str_contains($html, 'No pinned notes yet'), 'pinned empty state');

    $html = render_state(['notebook' => 'unfiled']);
    verify(str_contains($html, 'No unfiled notes') && str_contains($html, 'Show all notes'), 'unfiled guidance');

    $html = render_state(['search' => 'missing']);
    verify(str_contains($html, 'No matching notes') && str_contains($html, 'Clear filters'), 'search guidance');

    $html = render_state(['notebook' => str_repeat('b', 32), 'invalid' => true]);
    verify(str_contains($html, 'No matching notes') && !str_contains($html, str_repeat('b', 32)), 'foreign filters fail closed in view');

    $html = render_state(['notebook' => str_repeat('a', 32), 'search' => 'missing']);
    verify(str_contains($html, 'No matching notes'), 'filters override notebook empty state');

    $html = render_state(['paged' => 5, 'category' => str_repeat('c', 32)], 50);
    verify(str_contains($html, 'No notes on this page') && str_contains($html, 'First page'), 'out-of-range pagination guidance');
    verify(str_contains($html, 'cbn_category'), 'first page preserves filtering');

    echo "PASS PHP: tests/library-empty-states-smoke.php\n";
}

<?php
declare(strict_types=1);

// Isolated display-preference smoke, not a live WordPress security check.
namespace CB\Notes\Library {
    final class Page {
        public static function parameters(array $state): array {
            return ['post_type' => 'cb_note', 'page' => 'cb-notes-library',
                'cbn_search' => (string)($state['search'] ?? '')];
        }
    }
}
namespace {
    define('ABSPATH', __DIR__);
    function get_user_meta(int $id, string $key, bool $single): string {
        return $GLOBALS['user_preferences'][$id][$key] ?? '';
    }
    function admin_url(string $path): string { return '/wp-admin/' . $path; }
    function add_query_arg(array $args, string $url): string {
        return $url . '?' . http_build_query($args);
    }
    function wp_nonce_url(string $url, string $action): string {
        return $url . '&_wpnonce=' . rawurlencode($action);
    }
    require_once dirname(__DIR__) . '/src/Library/Display.php';

    use CB\Notes\Library\Display;
    function expect_display(bool $condition, string $label): void {
        if (!$condition) {
            fwrite(STDERR, "FAIL Table preference: {$label}\n");
            exit(1);
        }
        echo "PASS Table preference: {$label}\n";
    }
    $GLOBALS['user_preferences'][42]['cb_notes_library_display'] = 'table';
    $GLOBALS['user_preferences'][99]['cb_notes_library_display'] = 'invalid';
    expect_display(Display::current(42) === 'table', 'Owner preference restored');
    expect_display(Display::current(99) === 'list', 'Unknown preference falls back to List');
    expect_display(Display::current(0) === 'list', 'Anonymous user receives no private preference');
    $url = Display::switch_url('table', ['search' => 'Planning']);
    expect_display(str_contains($url, 'action=cb_notes_library_display') &&
        str_contains($url, 'layout=table') &&
        str_contains($url, 'cbn_search=Planning') &&
        str_contains($url, '_wpnonce=cb_notes_library_display_table'),
        'Link preserves canonical filter and requires a nonce');
    expect_display(str_contains(Display::switch_url('fake', []), 'layout=list'),
        'Unexpected display mode is normalized to List');
    $source = file_get_contents(dirname(__DIR__) . '/src/Library/Display.php');
    expect_display(is_string($source) &&
        str_contains($source, "current_user_can('cb_notes_edit')") &&
        str_contains($source, 'check_admin_referer(') &&
        str_contains($source, 'update_user_meta($owner, self::META, $layout)') &&
        str_contains($source, 'Query::state($_GET, Catalog::catalog($owner))') &&
        str_contains($source, 'wp_safe_redirect(Page::url($state))') &&
        !str_contains($source, 'wp_redirect($_GET'),
        'Saving requires owner capability and CSRF nonce, and redirects only canonically');
    echo "PASS PHP: tests/library-display-smoke.php\n";
}

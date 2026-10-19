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
    $GLOBALS['user_preferences'][17]['cb_notes_library_display'] = 'cards';
    $GLOBALS['user_preferences'][17]['cb_notes_library_sidebar'] = 'collapsed';
    $GLOBALS['user_preferences'][42]['cb_notes_library_sidebar'] = 'expanded';
    expect_display(Display::current(42) === 'table', 'Owner preference restored');
    expect_display(Display::current(99) === 'list', 'Unknown preference falls back to List');
    expect_display(Display::current(17) === 'cards', 'Owner Cards preference restored');
    expect_display(Display::sidebar(17) === 'collapsed' &&
        Display::sidebar(42) === 'expanded', 'Owner sidebar preference restored');
    expect_display(Display::sidebar(99) === 'auto' &&
        Display::sidebar(0) === 'auto', 'Unconfigured sidebar uses responsive auto default');
    expect_display(Display::current(0) === 'list', 'Anonymous user receives no private preference');
    $url = Display::switch_url('table', ['search' => 'Planning']);
    expect_display(str_contains($url, 'action=cb_notes_library_display') &&
        str_contains($url, 'layout=table') &&
        str_contains($url, 'cbn_search=Planning') &&
        str_contains($url, '_wpnonce=cb_notes_library_display_table'),
        'Link preserves canonical filter and requires a nonce');
    $cards = Display::switch_url('cards', ['search' => 'Planning']);
    expect_display(str_contains($cards, 'layout=cards') &&
        str_contains($cards, '_wpnonce=cb_notes_library_display_cards') &&
        str_contains($cards, 'cbn_search=Planning'), 'Cards link keeps search and nonce');
    $sidebar = Display::sidebar_url('collapsed', ['search' => 'Planning']);
    expect_display(str_contains($sidebar, 'sidebar=collapsed') &&
        str_contains($sidebar, '_wpnonce=cb_notes_library_display_sidebar_collapsed') &&
        str_contains($sidebar, 'cbn_search=Planning') &&
        !str_contains($sidebar, 'layout='), 'Sidebar state has separate nonce and preserves current filters');
    expect_display(str_contains(Display::switch_url('fake', []), 'layout=list'),
        'Unexpected display mode is normalized to List');
    $source = file_get_contents(dirname(__DIR__) . '/src/Library/Display.php');
    expect_display(is_string($source) &&
        str_contains($source, "current_user_can('cb_notes_edit')") &&
        str_contains($source, 'check_admin_referer(') &&
        str_contains($source, 'update_user_meta($owner, self::META, $layout)') &&
        str_contains($source, 'update_user_meta($owner, self::SIDEBAR_META, $position)') &&
        str_contains($source, '$sidebar_requested === $layout_requested') &&
        str_contains($source, 'Query::state($_GET, Catalog::catalog($owner))') &&
        str_contains($source, 'wp_safe_redirect(Page::url($state))') &&
        !str_contains($source, 'wp_redirect($_GET'),
        'Saving requires owner capability and CSRF nonce, and redirects only canonically');
    echo "PASS PHP: tests/library-display-smoke.php\n";
}

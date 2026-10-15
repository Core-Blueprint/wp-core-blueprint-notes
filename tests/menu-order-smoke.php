<?php
declare(strict_types=1);

// WordPress admin submenu smoke: test real rows, not only source strings.
namespace CB\Notes {
    final class Plugin { public const TYPE = 'cb_note'; }
}

namespace CoreBlueprint\Core\UI {
    final class AdminTheme {
        public static function register_screen(string $hook, array $config): void {
            $GLOBALS['registered_admin_screens'][] = [$hook, $config];
        }
    }
}

namespace {
    define('ABSPATH', __DIR__);
    $GLOBALS['registered_hooks'] = [];
    $GLOBALS['registered_admin_screens'] = [];
    $GLOBALS['redirect_calls'] = [];
    $GLOBALS['can_edit_notes'] = true;

    function __(string $text, string $domain = ''): string { return $text; }
    function add_submenu_page(string $parent, string $title, string $label, string $cap, string $slug, callable $callback): string {
        $GLOBALS['submenu_registration'] = [$parent, $slug, $cap, $callback];
        return 'cb_note_page_cb-notes-library';
    }
    function current_user_can(string $cap): bool {
        return $cap === 'cb_notes_edit' && $GLOBALS['can_edit_notes'];
    }
    function get_current_user_id(): int { return 12; }
    function get_user_meta(int $user_id, string $key, bool $single): array { return []; }
    function wp_unslash(string $value): string { return $value; }
    function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
    function admin_url(string $path): string { return 'https://example.test/wp-admin/' . $path; }
    function add_query_arg(array $args, string $url): string {
        return $url . '?' . http_build_query($args);
    }
    function wp_safe_redirect(string $url, int $status = 302, string $x_redirect_by = 'WordPress'): bool {
        $GLOBALS['redirect_calls'][] = [$url, $status, $x_redirect_by];
        // Simulate a failed redirect to exercise the handler without terminating PHP.
        return false;
    }

    function add_action(string $hook, callable $callback, int $priority = 10): void {
        $GLOBALS['registered_hooks'][] = [$hook, $callback, $priority];
    }

    require_once dirname(__DIR__) . '/src/Library/Menu.php';
    // Isolated smoke tests do not load the plugin's PSR-style autoloader.
    // Page::init() now registers the personal display controller as well.
    require_once dirname(__DIR__) . '/src/Library/Display.php';
    require_once dirname(__DIR__) . '/src/Organization/Catalog.php';
    require_once dirname(__DIR__) . '/src/Library/Query.php';
    require_once dirname(__DIR__) . '/src/Library/Page.php';

    use CB\Notes\Library\Menu;
    use CB\Notes\Library\Page;
    use CB\Notes\Library\Display;

    function assert_menu(bool $condition, string $message): void {
        if (!$condition) {
            fwrite(STDERR, "FAIL Notes submenu: {$message}\n");
            exit(1);
        }
        echo "PASS Notes submenu: {$message}\n";
    }

    $library = ['Library', 'cb_notes_edit', 'cb-notes-library', 'Library', 'cb_note_page_cb-notes-library'];
    $all = ['All Notes', 'cb_notes_edit', 'edit.php?post_type=cb_note', 'All Notes'];
    $create = ['Add Note', 'cb_notes_edit', 'post-new.php?post_type=cb_note', 'Add Note'];
    $organize = ['Organize', 'cb_notes_edit', 'cb-notes-organize', 'Organize'];
    $ai = ['AI Access', 'manage_options', 'cb-notes-ai', 'AI Access'];
    $thirdParty = ['Third party', 'cb_notes_edit', 'partner-notes-page', 'Third party'];

    $initial = [$all, $create, $organize, $ai, $library, $thirdParty];
    $sorted = Menu::order($initial);
    $slugs = array_map(static fn(array $row): string => (string)$row[2], $sorted);
    assert_menu($slugs === [
        'cb-notes-library', 'post-new.php?post_type=cb_note',
        'cb-notes-organize', 'partner-notes-page', 'cb-notes-ai',
    ], 'Library canonical, duplicate All Notes hidden, other entries retained');
    assert_menu($sorted[0] === $library && $sorted[4] === $ai &&
        $sorted[1] === $create && $sorted[2] === $organize &&
        $sorted[3] === $thirdParty,
        'visible callbacks, capabilities and third-party rows untouched');
    assert_menu(Menu::order($sorted) === $sorted, 'reordering is idempotent');
    $withoutAi = Menu::order([$all, $create, $organize, $library]);
    assert_menu(count($withoutAi) === 3 &&
        $withoutAi[0] === $library && $withoutAi[1] === $create &&
        $withoutAi[2] === $organize, 'editor menu shows Library, Add Note and Organize');
    $withoutLibrary = Menu::order([$all, $create, $organize, $ai]);
    assert_menu($withoutLibrary[0] === $all && end($withoutLibrary) === $ai
        && count($withoutLibrary) === 4, 'native All Notes remains visible when Library absent');

    $GLOBALS['submenu'] = [
        'edit.php?post_type=cb_note' => $initial,
        'edit.php?post_type=post' => [['Posts', 'edit_posts', 'edit.php']],
    ];
    Menu::reorder();
    assert_menu($GLOBALS['submenu']['edit.php?post_type=cb_note'] === $sorted, 'WordPress Notes submenu updated');
    // Hiding a menu entry never changes the WordPress list endpoint itself.
    assert_menu(!in_array('edit.php?post_type=cb_note', array_column($sorted, 2), true),
        'native list entry hidden, without mutating other menu slugs');
    assert_menu($GLOBALS['submenu']['edit.php?post_type=post'][0][2] === 'edit.php', 'other menus not changed');

    $GLOBALS['submenu'] = [];
    Menu::reorder();
    assert_menu($GLOBALS['submenu'] === [], 'missing parent menu is safe');

    Page::init();
    $hooks = $GLOBALS['registered_hooks'];
    assert_menu((bool) array_filter($hooks, static fn(array $r): bool =>
        $r[0] === 'admin_menu' && $r[2] === 999
        && $r[1] === [Menu::class, 'reorder']
    ), 'late submenu reorder hook registered');
    assert_menu((bool) array_filter($hooks, static fn(array $r): bool =>
        $r[0] === 'admin_menu' && $r[2] === 11
        && $r[1] === [Page::class, 'register_menu']
    ), 'Library registration remains prior to ordering');
    assert_menu((bool) array_filter($hooks, static fn(array $r): bool =>
        $r[0] === 'admin_post_cb_notes_library_display'
        && $r[1] === [Display::class, 'save']
    ), 'List/Table preference handler registered alongside Library menu');
    // The registered Library is the first submenu and the CPT parent menu
    // points to its admin.php alias. Verify canonicalization without a loop.
    Page::register_menu();
    assert_menu($GLOBALS['submenu_registration'] === [
        'edit.php?post_type=cb_note', 'cb-notes-library', 'cb_notes_edit', [Page::class, 'render']
    ], 'Library callback remains registered under the Notes post type');
    assert_menu((bool) array_filter($GLOBALS['registered_hooks'], static fn(array $r): bool =>
        $r[0] === 'load-cb_note_page_cb-notes-library' &&
        $r[1] === [Page::class, 'redirect_admin_alias']
    ), 'canonical Notes URL registered on Library load');

    $GLOBALS['pagenow'] = 'admin.php';
    $_GET = ['page' => 'cb-notes-library', 'cbn_search' => 'Draft one', 'cbn_view' => 'favorites',
        'external' => 'ignored'];
    Page::redirect_admin_alias();
    assert_menu(count($GLOBALS['redirect_calls']) === 1, 'top-level Notes menu resolves to canonical Library');
    $redirect = $GLOBALS['redirect_calls'][0];
    assert_menu(str_starts_with($redirect[0], 'https://example.test/wp-admin/edit.php?') &&
        str_contains($redirect[0], 'post_type=cb_note') &&
        str_contains($redirect[0], 'page=cb-notes-library') &&
        str_contains($redirect[0], 'cbn_search=Draft+one') &&
        str_contains($redirect[0], 'cbn_view=favorites') &&
        !str_contains($redirect[0], 'external=') &&
        $redirect[1] === 302,
        'temporary redirect retains safe filters, drops unrelated arguments');

    $GLOBALS['pagenow'] = 'edit.php';
    Page::redirect_admin_alias();
    assert_menu(count($GLOBALS['redirect_calls']) === 1, 'canonical Library URL has no redirect loop');

    $GLOBALS['pagenow'] = 'admin.php';
    $GLOBALS['can_edit_notes'] = false;
    Page::redirect_admin_alias();
    assert_menu(count($GLOBALS['redirect_calls']) === 1, 'unauthorized alias request does not redirect');

    $GLOBALS['can_edit_notes'] = true;
    $_GET['page'] = 'another-plugin-page';
    Page::redirect_admin_alias();
    assert_menu(count($GLOBALS['redirect_calls']) === 1, 'other WordPress admin pages are untouched');
    echo "Notes submenu ordering smoke: PASS\n";
}

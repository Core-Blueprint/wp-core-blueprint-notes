<?php
declare(strict_types=1);

// WordPress admin submenu smoke: test real rows, not only source strings.
namespace CB\Notes {
    final class Plugin { public const TYPE = 'cb_note'; }
}

namespace {
    define('ABSPATH', __DIR__);
    $GLOBALS['registered_hooks'] = [];

    function add_action(string $hook, callable $callback, int $priority = 10): void {
        $GLOBALS['registered_hooks'][] = [$hook, $callback, $priority];
    }

    require_once dirname(__DIR__) . '/src/Library/Menu.php';
    // Isolated smoke tests do not load the plugin's PSR-style autoloader.
    // Page::init() now registers the personal display controller as well.
    require_once dirname(__DIR__) . '/src/Library/Display.php';
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
    echo "Notes submenu ordering smoke: PASS\n";
}

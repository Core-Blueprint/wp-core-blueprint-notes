<?php
declare(strict_types=1);

namespace CoreBlueprint\Core\Admin {
    final class SettingsRegistry {
        public const GROUP_BUSINESS = 'business';
        public static array $registered = [];
        public static function register(string $id, array $definition): bool {
            self::$registered[$id] = $definition;
            return true;
        }
        public static function url(string $id, array $extra = []): string {
            return 'https://example.test/wp-admin/admin.php?page=core-blueprint-settings&extension=' .
                rawurlencode($id) . '&tab=' . rawurlencode((string)($extra['tab'] ?? 'overview'));
        }
    }
}

namespace {
    define('ABSPATH', __DIR__ . '/');
    define('CB_NOTES_BASENAME', 'core-blueprint-notes/core-blueprint-notes.php');

    $GLOBALS['user_id'] = 7;
    $GLOBALS['options'] = ['cb_notes_mcp_read_enabled' => false];
    $GLOBALS['hooks'] = [];
    $GLOBALS['last_query'] = [];

    function __(string $text, string $domain): string { return $text; }
    function esc_html__(string $text, string $domain): string { return $text; }
    function esc_attr__(string $text, string $domain): string { return $text; }
    function esc_html(mixed $text): string { return htmlspecialchars((string)$text, ENT_QUOTES); }
    function esc_attr(mixed $text): string { return esc_html($text); }
    function esc_url(string $url): string { return esc_html($url); }
    function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . $path; }
    function current_user_can(string $cap): bool {
        return $cap === 'manage_options' && $GLOBALS['user_id'] === 7;
    }
    function get_current_user_id(): int { return $GLOBALS['user_id']; }
    function get_option(string $name, mixed $default = false): mixed {
        return $GLOBALS['options'][$name] ?? $default;
    }
    function sanitize_key(string $value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
    function wp_unslash(mixed $value): mixed { return $value; }
    function checked(mixed $current, mixed $expected, bool $echo = true): string {
        return $current === $expected ? 'checked="checked"' : '';
    }
    function wp_nonce_field(string $action): void {
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($action) . '">';
    }
    function settings_fields(string $group): void {
        echo '<input type="hidden" data-settings-group="' . esc_attr($group) . '">';
    }
    function submit_button(string $label): void {
        echo '<button type="submit">' . esc_html($label) . '</button>';
    }
    function add_action(string $hook, callable $callback, int $priority = 10): void {
        $GLOBALS['hooks'][$hook][] = $callback;
    }
    function add_submenu_page(string $parent, string $title, string $menu, string $capability,
        string $slug, callable $callback): string {
        $GLOBALS['submenu'] = compact('parent','title','menu','capability','slug','callback');
        return 'cb_note_page_' . $slug;
    }
    function wp_die(string $message): never { throw new \RuntimeException($message); }
    class WP_Query {
        public int $found_posts = 0;
        public function __construct(array $args) {
            $GLOBALS['last_query'] = $args;
            $this->found_posts = (int)(($args['author'] ?? 0) === 7 ? 2 : 900);
        }
    }

    require dirname(__DIR__) . '/src/Plugin.php';
    require dirname(__DIR__) . '/src/Sharing/SecureRevocationRecovery.php';
    require dirname(__DIR__) . '/src/Admin/SettingsPage.php';

    use CB\Notes\Admin\SettingsPage;
    use CoreBlueprint\Core\Admin\SettingsRegistry;

    function check(bool $condition, string $message): void {
        if (!$condition) {
            fwrite(STDERR, "FAIL: $message\n");
            exit(1);
        }
        echo "PASS: $message\n";
    }

    SettingsPage::init();
    check(isset($GLOBALS['hooks']['core_blueprint_register_settings']), 'provider registration hooked');
    SettingsPage::register();
    $provider = SettingsRegistry::$registered['core-blueprint-notes'] ?? [];
    check(($provider['group'] ?? '') === SettingsRegistry::GROUP_BUSINESS, 'Notes grouped under Business');
    check(($provider['capability'] ?? '') === 'manage_options', 'Settings Hub requires administrator capability');
    check(is_callable($provider['renderer'] ?? null), 'Settings Hub renderer callable');
    check(in_array('nav-tabs', $provider['requirements']['components'] ?? [], true), 'Base navigation assets requested');
    check(in_array('panels', $provider['requirements']['components'] ?? [], true), 'Base panel assets requested');

    $_GET['tab'] = 'invalid';
    ob_start();
    SettingsPage::render();
    $overview = (string)ob_get_clean();
    check(str_contains($overview, 'My notes') && str_contains($overview, '>2</strong>'), 'personal note count rendered');
    check(($GLOBALS['last_query']['author'] ?? 0) === 7, 'count scoped to current author');
    check(($GLOBALS['last_query']['posts_per_page'] ?? 0) === 1, 'count query bounded');
    check(!str_contains($overview, '<h1'), 'Base owns only page heading');
    check(str_contains($overview, 'nav-tab-active'), 'unknown tab defaults to Overview');
    check(str_contains($overview, 'Open Notes Library'), 'primary Library workspace linked');
    check(str_contains($overview, 'page=cb-notes-library'), 'Settings Hub link resolves to Library');
    check(str_contains($overview, 'Disabled'), 'read-only AI status shown disabled');

    // Operator-only recovery status contains a count, never a grant UUID.
    $GLOBALS['wpdb'] = new class {
        public string $options = 'wp_options';
        public function esc_like(string $value): string { return $value; }
        public function prepare(string $query, mixed ...$values): string { return $query; }
        public function get_var(string $query): int { return 2; }
    };
    $_GET['tab'] = 'overview';
    ob_start();
    SettingsPage::render();
    $with_pending = (string)ob_get_clean();
    check(str_contains($with_pending, 'Pending revocations:')
        && str_contains($with_pending, 'cb_notes_retry_revocations'),
        'Administrator sees bounded Secure Share retry action');
    check(str_contains($with_pending, 'name="_wpnonce"')
        && str_contains($with_pending, 'admin-post.php'),
        'Recovery form includes WordPress nonce and admin-post destination');
    check(!str_contains($with_pending, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
        'Recovery UI does not expose provider identifiers');
    unset($GLOBALS['wpdb']);

    $_GET['tab'] = 'ai-access';
    ob_start();
    SettingsPage::render();
    $ai_off = (string)ob_get_clean();
    check(str_contains($ai_off, 'cb_notes_mcp_read_enabled'), 'existing option key reused');
    check(str_contains($ai_off, 'data-settings-group="cb_notes_settings"'), 'existing secure options group reused');
    check(str_contains($ai_off, 'value="0"'), 'disabled state submits explicit false');
    check(!str_contains($ai_off, 'checked="checked"'), 'AI disabled by default');
    check(str_contains($ai_off, 'not create a connection'), 'MCP transport distinguished from switch');
    check(!str_contains($ai_off, '<h1'), 'no duplicate page heading on AI tab');

    $GLOBALS['options']['cb_notes_mcp_read_enabled'] = true;
    ob_start();
    SettingsPage::render();
    $ai_on = (string)ob_get_clean();
    check(str_contains($ai_on, 'checked="checked"'), 'stored true renders checked');
    check(str_contains($ai_on, 'Only users with cb_notes_use_ai'), 'capability boundary explained');

    \CB\Notes\Plugin::admin_menu();
    check(($GLOBALS['submenu']['slug'] ?? '') === 'cb-notes-ai', 'legacy Notes submenu retained');
    check(isset($GLOBALS['hooks']['load-cb_note_page_cb-notes-ai']), 'legacy submenu redirects before admin header');
    check(SettingsPage::url('ai-access') ===
        'https://example.test/wp-admin/admin.php?page=core-blueprint-settings&extension=core-blueprint-notes&tab=ai-access',
        'canonical hub deep link generated');

    $GLOBALS['user_id'] = 0;
    try {
        SettingsPage::render();
        check(false, 'unauthorized rendering must fail');
    } catch (\RuntimeException $e) {
        check(true, 'unauthorized settings access refused');
    }
    echo "Notes Settings Hub smoke: PASS\n";
}

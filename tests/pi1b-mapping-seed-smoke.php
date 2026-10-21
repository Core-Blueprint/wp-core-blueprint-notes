<?php
declare(strict_types=1);

namespace {
    final class WP_Error {
        public function __construct(public string $code, public string $message) {}
    }
    class wpdb {
        public string $options = 'wp_options';
        public string $last_error = '';
        public function esc_like(string $value): string { return $value; }
        public function prepare(string $sql, string $pattern): string { return $sql . ':' . $pattern; }
        public function get_var(string $sql): ?string {
            return ($GLOBALS['has_legacy_receipt'] ?? false)
                ? 'cb_notes_import_receipt_v1_old' : null;
        }
    }
    $GLOBALS['wpdb'] = new wpdb();
    $GLOBALS['options'] = [];
    function get_option(string $key, mixed $default = false): mixed {
        return $GLOBALS['options'][$key] ?? $default;
    }
    function add_option(string $key, mixed $value, string $deprecated = '', bool $autoload = true): bool {
        if (array_key_exists($key, $GLOBALS['options']) || $autoload) { return false; }
        $GLOBALS['options'][$key] = $value;
        return true;
    }
    function current_user_can(string $cap): bool { return $cap === 'manage_options'; }
    function assert_seed(bool $test, string $name): void {
        if (!$test) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    }
    require dirname(__DIR__) . '/src/Portability/MappingSeed.php';
    use CB\Notes\Portability\MappingSeed;
    $first = MappingSeed::provision();
    assert_seed($first === true, 'fresh site seeded during installation');
    $key = MappingSeed::read();
    assert_seed(is_string($key) && strlen($key) === 32, 'stable 256-bit local seed');
    assert_seed(count($GLOBALS['options']) === 1, 'only one non-autoload private option created');
    $GLOBALS['auth_salt'] = str_repeat('x', 64);
    assert_seed(MappingSeed::provision() === true && MappingSeed::read() === $key,
        'repeated install and AUTH_KEY rotation never change catalog mapping seed');
    $GLOBALS['options']['cb_notes_import_mapping_seed_v1'] = 'corrupt';
    assert_seed(MappingSeed::provision() instanceof WP_Error && MappingSeed::read() instanceof WP_Error,
        'corrupt seed cannot be replaced silently');
    unset($GLOBALS['options']['cb_notes_import_mapping_seed_v1']);
    $GLOBALS['has_legacy_receipt'] = true;
    assert_seed(MappingSeed::provision() instanceof WP_Error && $GLOBALS['options'] === [],
        'unversioned earlier receipts block new mapping key');
    echo "Notes PI-1B stable mapping seed smoke: PASS (mocked WordPress)\n";
}

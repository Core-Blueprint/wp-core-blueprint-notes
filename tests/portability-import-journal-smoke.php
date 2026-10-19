<?php
declare(strict_types=1);

namespace CB\Notes {
    final class Plugin { public const TYPE = 'cb_note'; }
}
namespace {
    final class WP_Error {
        public function __construct(public string $code, public string $message) {}
    }
    final class WP_Post {
        public function __construct(
            public int $ID, public int $post_author,
            public string $post_type = 'cb_note',
            public string $post_status = 'private'
        ) {}
    }
    $GLOBALS['actor'] = 10;
    $GLOBALS['can'] = true;
    $GLOBALS['option_data'] = [];
    $GLOBALS['posts'] = [];
    $GLOBALS['meta'] = [];
    $GLOBALS['fails'] = [];
    function get_current_user_id(): int { return $GLOBALS['actor']; }
    function current_user_can(string $cap, int $id = 0): bool {
        return $GLOBALS['can'] && ($cap === 'cb_notes_edit' || $cap === 'edit_post');
    }
    function wp_salt(string $scheme): string { return str_repeat('z', 64); }
    function add_option(string $key, mixed $value, string $unused, bool $autoload): bool {
        if (isset($GLOBALS['option_data'][$key]) || isset($GLOBALS['fails']['add'])) { return false; }
        $GLOBALS['option_data'][$key] = $value;
        return true;
    }
    function update_option(string $key, mixed $value, bool $autoload): bool {
        if (isset($GLOBALS['fails']['update'])) { return false; }
        $GLOBALS['option_data'][$key] = $value;
        return true;
    }
    function get_option(string $key, mixed $fallback): mixed { return $GLOBALS['option_data'][$key] ?? $fallback; }
    function get_post(int $id): ?WP_Post { return $GLOBALS['posts'][$id] ?? null; }
    function get_post_meta(int $id, string $key, bool $single): mixed { return $GLOBALS['meta'][$id][$key] ?? ''; }
    function expect_journal(bool $yes, string $message): void {
        if (!$yes) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    }
    require dirname(__DIR__) . '/src/Portability/ImportJournal.php';
    use CB\Notes\Portability\ImportJournal;
    $digest = str_repeat('a', 64);
    $new = ImportJournal::receipt(10, $digest, 'note:11');
    expect_journal($new === ['state' => 'new'], 'no receipt means no previous attempt');
    $id_before = ImportJournal::key(10, $digest, 'note:11');
    $GLOBALS['rotated_wp_salts'] = true;
    $id_after = ImportJournal::key(10, $digest, 'note:11');
    expect_journal(is_string($id_before) && $id_before === $id_after,
        'journal lookup stable across WordPress authentication-salt changes');
    $reserved = ImportJournal::reserve(10, $digest, 'note:11');
    expect_journal(is_string($reserved), 'create unique write-ahead intent');
    expect_journal(ImportJournal::reserve(10, $digest, 'note:11') instanceof WP_Error,
        'duplicate reservation denied');
    $pending = ImportJournal::receipt(10, $digest, 'note:11');
    expect_journal($pending instanceof WP_Error && $pending->code === 'cb_notes_import_journal_needs_review',
        'ambiguous interruption refuses automatic retry');
    $GLOBALS['posts'][101] = new WP_Post(101, 20);
    $GLOBALS['meta'][101][ImportJournal::META] = $reserved;
    expect_journal(ImportJournal::complete(10, $digest, 'note:11', 101) instanceof WP_Error,
        'foreign owner cannot complete');
    $GLOBALS['posts'][101]->post_author = 10;
    expect_journal(ImportJournal::complete(10, $digest, 'note:11', 101) === true,
        'verified owner receipt completes exactly once');
    expect_journal(ImportJournal::receipt(10, $digest, 'note:11') === ['state' => 'complete', 'post_id' => 101],
        'complete receipt remaps to original owner post');
    expect_journal(ImportJournal::complete(10, $digest, 'note:11', 101) instanceof WP_Error,
        'completed receipt cannot be rewritten');
    $GLOBALS['posts'][101]->post_author = 20;
    expect_journal(ImportJournal::receipt(10, $digest, 'note:11') instanceof WP_Error,
        'tampered owner mapping fails closed');
    $GLOBALS['posts'][101]->post_author = 10;
    $GLOBALS['actor'] = 20;
    expect_journal(ImportJournal::receipt(10, $digest, 'note:11') instanceof WP_Error,
        'foreign actor cannot view receipt');
    $GLOBALS['actor'] = 10;
    $GLOBALS['fails']['add'] = true;
    expect_journal(ImportJournal::reserve(10, $digest, 'note:12') instanceof WP_Error,
        'failed intent prevents any create');
    unset($GLOBALS['fails']['add']);
    $key2 = ImportJournal::reserve(10, $digest, 'note:12');
    $GLOBALS['posts'][102] = new WP_Post(102, 10);
    $GLOBALS['meta'][102][ImportJournal::META] = $key2;
    $GLOBALS['fails']['update'] = true;
    expect_journal(ImportJournal::complete(10, $digest, 'note:12', 102) instanceof WP_Error,
        'failed completion never claims success');
    unset($GLOBALS['fails']['update']);
    expect_journal(ImportJournal::receipt(10, $digest, 'note:12') instanceof WP_Error,
        'failed completion remains pending and requires review');
    echo "Notes P2-B owner-only import journal: PASS (mocked, no live mutations)\n";
}

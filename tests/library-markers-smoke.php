<?php
declare(strict_types=1);

// Isolated P1 contract smoke. Real WordPress permissions require staging tests.
namespace CB\Notes {
    final class Plugin { public const TYPE = 'cb_note'; }
}

namespace {
    define('ABSPATH', __DIR__);

    final class WP_Post {
        public function __construct(
            public int $ID,
            public int $post_author,
            public string $post_type = 'cb_note',
            public string $post_status = 'private',
        ) {}
    }
    final class WP_Query {
        public function __construct(private array $state) {}
        public function get(string $key): mixed { return $this->state[$key] ?? null; }
    }
    final class FakeWpdb {
        public string $posts = 'wp_posts';
        public string $postmeta = 'wp_postmeta';
        public function prepare(string $sql, string ...$values): string {
            foreach ($values as $value) {
                $sql = preg_replace('/%s/', "'" . addslashes($value) . "'", $sql, 1);
            }
            return $sql;
        }
    }

    $GLOBALS['wpdb'] = new FakeWpdb();
    $GLOBALS['note_posts'] = [7 => new WP_Post(7, 1), 8 => new WP_Post(8, 2)];
    $GLOBALS['note_meta'] = [];
    $GLOBALS['user'] = 1;
    $GLOBALS['last_json'] = null;

    function get_current_user_id(): int { return $GLOBALS['user']; }
    function current_user_can(string $cap, int $post_id = 0): bool {
        return in_array($cap, ['cb_notes_edit', 'edit_post'], true) && $GLOBALS['user'] > 0;
    }
    function get_post(int $id): ?WP_Post { return $GLOBALS['note_posts'][$id] ?? null; }
    function get_post_meta(int $id, string $key, bool $single = true): mixed {
        return $GLOBALS['note_meta'][$id][$key] ?? '';
    }
    function update_post_meta(int $id, string $key, string $value): void {
        $GLOBALS['note_meta'][$id][$key] = $value;
    }
    function delete_post_meta(int $id, string $key): void {
        unset($GLOBALS['note_meta'][$id][$key]);
    }
    function absint(mixed $value): int { return abs((int)$value); }
    function wp_verify_nonce(string $nonce, string $action): bool { return $nonce === 'valid:' . $action; }
    function __(string $message, string $domain): string { return $message; }
    function wp_send_json_error(array $data, int $status): void {
        $GLOBALS['last_json'] = ['success' => false, 'data' => $data, 'status' => $status];
    }
    function wp_send_json_success(array $data): void {
        $GLOBALS['last_json'] = ['success' => true, 'data' => $data, 'status' => 200];
    }

    require_once dirname(__DIR__) . '/src/Library/Markers.php';
    use CB\Notes\Library\Markers;

    function ensure_marker(bool $result, string $message): void {
        if (!$result) {
            fwrite(STDERR, "FAIL P1 markers: {$message}\n");
            exit(1);
        }
        echo "PASS P1 markers: {$message}\n";
    }
    function request_mark(int $id, string $kind, string $enabled, string $nonce = ''): array {
        $_POST = [
            'note_id' => (string)$id,
            'kind' => $kind,
            'enabled' => $enabled,
            '_ajax_nonce' => $nonce ?: 'valid:cb_notes_mark_' . $id,
        ];
        $GLOBALS['last_json'] = null;
        Markers::ajax();
        return $GLOBALS['last_json'];
    }

    ensure_marker(Markers::can_mark(7), 'owner may mark private note');
    ensure_marker(!Markers::can_mark(8), 'owner cannot mark another author note');

    $result = request_mark(7, 'favorite', '1');
    ensure_marker($result['success'] && $result['data']['favorite'] && !$result['data']['pinned'],
        'favorite toggles without pinning');

    $result = request_mark(7, 'pinned', '1');
    ensure_marker($result['success'] && $result['data']['favorite'] && $result['data']['pinned'],
        'pinning is independent from favorites');

    $result = request_mark(7, 'favorite', '0');
    ensure_marker($result['success'] && !$result['data']['favorite'] && $result['data']['pinned'],
        'unfavorite does not unpin');

    $result = request_mark(7, 'favorite', '1', 'invalid');
    ensure_marker(!$result['success'] && $result['status'] === 403, 'CSRF nonce failure denied');

    $result = request_mark(7, 'other', '1');
    ensure_marker(!$result['success'] && $result['status'] === 422, 'unrecognized marker denied');

    $result = request_mark(7, 'pinned', 'yes');
    ensure_marker(!$result['success'] && $result['status'] === 422, 'invalid enabled value denied');

    $GLOBALS['user'] = 2;
    $result = request_mark(7, 'favorite', '1');
    ensure_marker(!$result['success'] && $result['status'] === 403, 'cross-user AJAX mutation denied');

    $GLOBALS['user'] = 1;
    $GLOBALS['note_posts'][7]->post_status = 'trash';
    $result = request_mark(7, 'pinned', '0');
    ensure_marker(!$result['success'] && $result['status'] === 403, 'trashed note cannot be marked');

    $q = new WP_Query(['post_type' => 'cb_note', 'author' => 1, 'cb_notes_pinned_first' => true]);
    $orderby = Markers::pinned_first('wp_posts.post_modified DESC', $q);
    ensure_marker(str_contains($orderby, 'CASE WHEN EXISTS') && str_contains($orderby, "'_cb_notes_pinned'")
        && str_contains($orderby, 'wp_posts.post_modified DESC'), 'pinned-first SQL preserves base sort');

    $other = Markers::pinned_first('unchanged', new WP_Query(['post_type' => 'post', 'author' => 1, 'cb_notes_pinned_first' => true]));
    ensure_marker($other === 'unchanged', 'ordinary WordPress posts unaffected');
    $other = Markers::pinned_first('unchanged', new WP_Query(['post_type' => 'cb_note', 'author' => 1, 'cb_notes_pinned_first' => false]));
    ensure_marker($other === 'unchanged', 'explicit sort bypasses pinned-first');
    $other = Markers::pinned_first('unchanged', new WP_Query(['post_type' => 'cb_note', 'author' => 0, 'cb_notes_pinned_first' => true]));
    ensure_marker($other === 'unchanged', 'anonymous queries cannot opt in to pinned-first');
    echo "PASS PHP: tests/library-markers-smoke.php\n";
}

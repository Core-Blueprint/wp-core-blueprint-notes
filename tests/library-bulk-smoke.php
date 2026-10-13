<?php
declare(strict_types=1);

// Isolated bulk authorization and lifecycle test, not live WordPress.
namespace CB\Notes {
    final class Plugin { public const TYPE = 'cb_note'; }
}
namespace CB\Notes\Organization {
    final class Catalog {
        public const NOTEBOOK = 'cb_notes_notebook_id';
        public const CATEGORY = 'cb_notes_category_id';
        public static function valid_id(mixed $id): bool {
            return is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) === 1;
        }
        public static function contains(int $owner, string $kind, mixed $id): bool {
            return $owner === 1 && in_array($kind, ['notebook', 'category'], true)
                && $id === str_repeat($kind === 'notebook' ? 'a' : 'b', 32);
        }
    }
}
namespace CB\Notes\Library {
    final class Markers {
        public static function can_mark(int $id): bool {
            $post = get_post($id);
            return $post instanceof \WP_Post && $post->post_author === get_current_user_id()
                && $post->post_status !== 'trash'
                && current_user_can('edit_post', $id) && current_user_can('cb_notes_edit');
        }
    }
}
namespace {
    define('ABSPATH', __DIR__);
    final class WP_Post {
        public function __construct(
            public int $ID,
            public int $post_author,
            public string $post_status = 'private',
        ) {}
    }

    $GLOBALS['user'] = 1;
    $GLOBALS['can_delete'] = true;
    $GLOBALS['can_edit'] = true;
    $GLOBALS['posts'] = [
        1 => new WP_Post(1, 1),
        2 => new WP_Post(2, 1),
        3 => new WP_Post(3, 2),
        4 => new WP_Post(4, 1),
    ];
    $GLOBALS['meta'] = [];
    $GLOBALS['revoked'] = [];
    $GLOBALS['failure'] = 0;
    $GLOBALS['response'] = null;

    function get_current_user_id(): int { return $GLOBALS['user']; }
    function current_user_can(string $cap, int $id = 0): bool {
        return match ($cap) {
            'cb_notes_edit', 'edit_post' => $GLOBALS['can_edit'],
            'delete_post' => $GLOBALS['can_delete'],
            default => false,
        };
    }
    function get_post(int $id): ?WP_Post { return $GLOBALS['posts'][$id] ?? null; }
    function get_post_status(int $id): string|false { return $GLOBALS['posts'][$id]->post_status ?? false; }
    function get_post_meta(int $id, string $key, bool $single = true): string {
        return $GLOBALS['meta'][$id][$key] ?? '';
    }
    function update_post_meta(int $id, string $key, string $value): bool {
        if ($id === $GLOBALS['failure']) return false;
        $GLOBALS['meta'][$id][$key] = $value;
        return true;
    }
    function wp_trash_post(int $id): WP_Post|false {
        if ($id === $GLOBALS['failure'] || !isset($GLOBALS['posts'][$id])) return false;
        $post = $GLOBALS['posts'][$id];
        $post->post_status = 'trash';
        // Real WordPress fires trashed_post and Notes revokes links.
        $GLOBALS['revoked'][] = $id;
        return $post;
    }
    function wp_verify_nonce(string $nonce, string $action): bool { return $nonce === 'valid:' . $action; }
    function __(string $s, string $domain): string { return $s; }
    function wp_send_json_error(array $data, int $status): void {
        $GLOBALS['response'] = ['success' => false, 'status' => $status, 'data' => $data];
    }
    function wp_send_json_success(array $data): void {
        $GLOBALS['response'] = ['success' => true, 'status' => 200, 'data' => $data];
    }
    require_once dirname(__DIR__) . '/src/Library/Bulk.php';
    use CB\Notes\Library\Bulk;
    use CB\Notes\Organization\Catalog;

    function check_bulk(bool $ok, string $label): void {
        if (!$ok) { fwrite(STDERR, "FAIL P2: {$label}\n"); exit(1); }
        echo "PASS P2: {$label}\n";
    }
    function send_bulk(array $ids, string $operation, string $target = '', ?string $nonce = null): array {
        $_POST = [
            '_ajax_nonce' => $nonce ?? 'valid:' . Bulk::NONCE_ACTION,
            'ids' => $ids, 'operation' => $operation, 'target' => $target,
        ];
        $GLOBALS['response'] = null;
        Bulk::ajax();
        return $GLOBALS['response'];
    }

    check_bulk(Bulk::ids(['1', '2']) === [1, 2], 'two exact IDs accepted');
    check_bulk(Bulk::ids(array_map('strval', range(1, 20))) !== null, '20 selected allowed');
    check_bulk(Bulk::ids(array_map('strval', range(1, 21))) === null, 'more than 20 denied');
    foreach ([[], ['1', '1'], ['01'], [1], ['-1'], ['0'], ['x'], ['99999999999999999999']] as $bad) {
        check_bulk(Bulk::ids($bad) === null, 'malformed/duplicate IDs denied');
    }
    check_bulk(Bulk::target_valid('notebook', str_repeat('a', 32), 1), 'owner notebook is valid');
    check_bulk(!Bulk::target_valid('notebook', str_repeat('c', 32), 1), 'foreign catalog ID denied');
    check_bulk(!Bulk::target_valid('category', str_repeat('b', 32), 2), 'other user catalog denied');
    check_bulk(Bulk::target_valid('notebook', '', 1), 'unfiled destination permitted');
    check_bulk(Bulk::target_valid('category', '', 1), 'clear category permitted');
    check_bulk(!Bulk::target_valid('trash', 'wrong', 1), 'trash takes no destination');

    $denied = send_bulk(['1'], 'notebook', str_repeat('a', 32), 'invalid');
    check_bulk(!$denied['success'] && $denied['status'] === 403, 'invalid nonce fails closed');

    $denied = send_bulk(['1'], 'notebook', str_repeat('c', 32));
    check_bulk(!$denied['success'] && $denied['status'] === 422, 'foreign target fails entire request');
    check_bulk(!isset($GLOBALS['meta'][1]), 'invalid target never writes');

    $denied = send_bulk(['1', '1'], 'notebook', str_repeat('a', 32));
    check_bulk(!$denied['success'] && $denied['status'] === 422, 'duplicate selection fails closed');

    $one = send_bulk(['1', '3'], 'notebook', str_repeat('a', 32));
    check_bulk($one['success'] && $one['data']['updated'] === 1 && $one['data']['failed'] === 1
        && $one['data']['updated_ids'] === [1] && $one['data']['failed_ids'] === [3],
        'partially successful notebook result identifies successful/failed IDs');
    check_bulk(!isset($GLOBALS['meta'][3]), 'cross-owner post stays untouched');

    $GLOBALS['failure'] = 2;
    $part = send_bulk(['1', '2', '4'], 'category', str_repeat('b', 32));
    check_bulk($part['success'] && $part['data']['updated_ids'] === [1, 4]
        && $part['data']['failed_ids'] === [2], 'storage failure does not block other selected notes');
    $GLOBALS['failure'] = 0;

    $clear = send_bulk(['1'], 'notebook', '');
    check_bulk($clear['success'] && get_post_meta(1, Catalog::NOTEBOOK) === '', 'clear notebook without deletion');

    $GLOBALS['can_delete'] = false;
    $denyTrash = send_bulk(['1'], 'trash');
    check_bulk($denyTrash['data']['updated'] === 0 && $denyTrash['data']['failed'] === 1,
        'trash requires delete_post on each item');
    $GLOBALS['can_delete'] = true;

    $trash = send_bulk(['1', '3'], 'trash');
    check_bulk($trash['data']['updated_ids'] === [1] && $trash['data']['failed_ids'] === [3],
        'trash scopes each selected note to owner and caps');
    check_bulk(in_array(1, $GLOBALS['revoked'], true), 'WP trash lifecycle invoked for revocation');
    check_bulk(get_post_status(1) === 'trash', 'trashed note status correct');

    $GLOBALS['can_edit'] = false;
    $deny = send_bulk(['2'], 'category', '');
    check_bulk(!$deny['success'] && $deny['status'] === 403, 'Notes capability required');
    $GLOBALS['can_edit'] = true;

    echo "PASS PHP: tests/library-bulk-smoke.php\n";
}

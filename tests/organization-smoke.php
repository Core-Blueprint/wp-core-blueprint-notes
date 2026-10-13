<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
$GLOBALS['user_id'] = 7;
$GLOBALS['users'] = [];
$GLOBALS['posts'] = [];
$GLOBALS['post_meta'] = [];
$GLOBALS['saved_meta_registration'] = [];

class WP_Post {
    public function __construct(
        public int $ID, public string $post_type = 'cb_note',
        public int $post_author = 7, public string $post_status = 'private'
    ) {}
}
class WP_Error {
    public function __construct(public string $code, public string $message, public array $data = []) {}
}
class WP_REST_Request {
    public function __construct(private array $data = []) {}
    public function get_param(string $key): mixed { return $this->data[$key] ?? null; }
}
class WP_REST_Server { public const READABLE = 'GET'; public const CREATABLE = 'POST'; }
class WP_Query {
    private array $args;
    public function __construct(array $args = []) { $this->args = $args; }
    public function get(string $key): mixed { return $this->args[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->args[$key] = $value; }
    public function is_main_query(): bool { return true; }
}
function __(string $s, string $domain): string { return $s; }
function get_current_user_id(): int { return $GLOBALS['user_id']; }
function current_user_can(string $cap): bool { return $GLOBALS['user_id'] > 0 && $cap === 'cb_notes_edit'; }
function user_can(int $user, string $cap): bool { return $user > 0 && $cap === 'cb_notes_edit'; }
function sanitize_text_field(string $s): string { return trim(strip_tags($s)); }
function absint(mixed $v): int { return abs((int)$v); }
function get_post(int $id): ?WP_Post { return $GLOBALS['posts'][$id] ?? null; }
function get_post_meta(int $id, string $key, bool $single = true): mixed { return $GLOBALS['post_meta'][$id][$key] ?? ''; }
function update_post_meta(int $id, string $key, mixed $value): bool { $GLOBALS['post_meta'][$id][$key] = $value; return true; }
function get_user_meta(int $id, string $key, bool $single = true): mixed { return $GLOBALS['users'][$id][$key] ?? ''; }
function update_user_meta(int $id, string $key, mixed $value): bool { $GLOBALS['users'][$id][$key] = $value; return true; }
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
function is_admin(): bool { return true; }
function wp_unslash(string $value): string { return $value; }
function get_posts(array $args): array {
    $found = [];
    foreach ($GLOBALS['posts'] as $post) {
        if ($post->post_type !== $args['post_type'] || $post->post_author !== $args['author']) continue;
        if (!in_array($post->post_status, $args['post_status'], true)) continue;
        $okay = true;
        foreach ($args['meta_query'] ?? [] as $clause) {
            if (!is_array($clause) || !isset($clause['key'])) continue;
            $value = $GLOBALS['post_meta'][$post->ID][$clause['key']] ?? null;
            if (($clause['compare'] ?? '') === 'EXISTS') $okay = $okay && $value !== null;
            elseif (($clause['compare'] ?? '') === 'NOT EXISTS') $okay = $okay && $value === null;
            elseif (($clause['compare'] ?? '') === 'LIKE') $okay = $okay && str_contains(serialize($value), (string)$clause['value']);
            else $okay = $okay && $value === $clause['value'];
        }
        if ($okay) $found[] = $post->ID;
    }
    sort($found);
    return array_slice($found, 0, $args['posts_per_page'] ?? 50);
}
function register_post_meta(string $posttype, string $key, array $args): void {
    $GLOBALS['saved_meta_registration'][$key] = $args;
}
function check(bool $okay, string $label): void {
    if (!$okay) {fwrite(STDERR, "FAIL: $label\n");exit(1);}
    echo "PASS: $label\n";
}

require dirname(__DIR__) . '/src/Plugin.php';
require dirname(__DIR__) . '/src/Access.php';
require dirname(__DIR__) . '/src/Organization/Catalog.php';

use CB\Notes\Organization\Catalog;

$GLOBALS['posts'][100] = new WP_Post(100, 'cb_note', 7);
$GLOBALS['posts'][101] = new WP_Post(101, 'cb_note', 8);
Catalog::register_meta();
check(isset($GLOBALS['saved_meta_registration'][Catalog::NOTEBOOK], $GLOBALS['saved_meta_registration'][Catalog::CATEGORY], $GLOBALS['saved_meta_registration'][Catalog::TAGS]), 'private organization post meta registered');
check($GLOBALS['saved_meta_registration'][Catalog::TAGS]['show_in_rest']['schema']['maxItems'] === 12, 'tag limit enforced in REST schema');

function change(string $action, string $kind, string $name = '', string $id = ''): array|WP_Error {
    return Catalog::mutate(new WP_REST_Request(compact('action', 'kind', 'name', 'id')));
}

$a = change('create', 'notebook', 'Client A');
check(is_array($a) && count($a['notebook']) === 1, 'personal notebook created');
$book = $a['notebook'][0]['id'];
check(Catalog::valid_id($book), 'opaque notebook ID canonical');
check(is_wp_error(change('create', 'notebook', 'client a')), 'case-insensitive duplicate rejected');
$a = change('rename', 'notebook', 'Client A Projects', $book);
check(is_array($a) && $a['notebook'][0]['id'] === $book && $a['notebook'][0]['name'] === 'Client A Projects', 'rename keeps canonical notebook identity');
$a = change('create', 'category', 'Meetings');
$category = $a['category'][0]['id'];
$a = change('create', 'tag', 'follow-up');
$tag = $a['tag'][0]['id'];
update_post_meta(100, Catalog::NOTEBOOK, $book);
update_post_meta(100, Catalog::CATEGORY, $category);
update_post_meta(100, Catalog::TAGS, [$tag]);

$labels = Catalog::labels_for_post(100);
check($labels['notebook'] === 'Client A Projects' && $labels['category'] === 'Meetings' && $labels['tags'] === ['follow-up'], 'labels resolve for own note only');
$valid = Catalog::validate_note_rest(new stdClass(), new WP_REST_Request([
    'id' => 100, 'meta' => [
        Catalog::NOTEBOOK => $book, Catalog::CATEGORY => $category,
        Catalog::TAGS => [$tag],
    ],
]));
check($valid instanceof stdClass, 'valid Gutenberg metadata permitted');

$GLOBALS['user_id'] = 8;
check(Catalog::catalog(8)['notebook'] === [], 'user B never sees user A notebooks');
check(Catalog::labels_for_post(100)['notebook'] === '', 'user B cannot read A organization labels');
check(!Catalog::contains(8, 'notebook', $book), 'user A catalog IDs invalid for user B');
check(Catalog::validate_note_rest(new stdClass(), new WP_REST_Request([
    'id' => 101, 'meta' => [Catalog::NOTEBOOK => $book],
])) instanceof WP_Error, 'cannot attach another user catalog ID');
check(Catalog::validate_note_rest(new stdClass(), new WP_REST_Request([
    'id' => 100, 'meta' => [Catalog::NOTEBOOK => $book],
])) instanceof WP_Error, 'cannot change other owner metadata');
$b = change('create', 'notebook', 'Client A');
check(is_array($b) && $b['notebook'][0]['id'] !== $book, 'identical names isolated per user');
$GLOBALS['user_id'] = 7;

$_GET['cb_notes_filter_category'] = $category;
$q = new WP_Query(['post_type' => 'cb_note']);
Catalog::filter_list($q);
check(($q->get('meta_query')[0]['value'] ?? '') === $category, 'library category filter uses opaque ID');
unset($_GET['cb_notes_filter_category']);
$_GET['cb_notes_filter_tag'] = $tag;
$q = new WP_Query(['post_type' => 'cb_note']);
Catalog::filter_list($q);
check(str_contains((string)$q->get('meta_query')[0]['value'], $tag), 'tag filter uses serialized exact ID');
unset($_GET['cb_notes_filter_tag']);

$gone = change('delete', 'tag', '', $tag);
check(is_array($gone) && $gone['tag'] === [], 'tag removed from own catalog');
check(get_post_meta(100, Catalog::TAGS) === [], 'removing tag clears own note references');
$gone = change('delete', 'notebook', '', $book);
check(is_array($gone) && get_post_meta(100, Catalog::NOTEBOOK) === '', 'removing notebook unfiles own note without deleting content');

$GLOBALS['posts'][102] = new WP_Post(102, 'cb_note', 7);
update_post_meta(102, CB\Notes\Plugin::META_NOTEBOOK, 'Legacy Projects');
Catalog::migrate_legacy();
$legacy = get_post_meta(102, Catalog::NOTEBOOK);
check(Catalog::contains(7, 'notebook', $legacy), 'alpha2 Notebook text migrated to personal catalog');
check(Catalog::labels_for_post(102)['notebook'] === 'Legacy Projects', 'migrated label remains visible');
check(count(Catalog::catalog(8)['notebook']) === 1, 'legacy migration cannot modify another user catalog');

echo "Notes alpha4 private organization smoke: PASS\n";

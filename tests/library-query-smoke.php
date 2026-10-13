<?php
declare(strict_types=1);

// Isolated query-contract smoke. This is NOT a WordPress runtime security test.
namespace CB\Notes {
    final class Plugin { public const TYPE = 'cb_note'; }
}
namespace CB\Notes\Organization {
    final class Catalog {
        public const NOTEBOOK = 'cb_notes_notebook_id';
        public const CATEGORY = 'cb_notes_category_id';
        public const TAGS = 'cb_notes_tag_ids';
        public static function valid_id(mixed $id): bool {
            return is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) === 1;
        }
    }
}
namespace {
    define('ABSPATH', __DIR__);
    function sanitize_text_field(string $value): string { return strip_tags($value); }
    function wp_unslash(string $value): string { return stripslashes($value); }
    require_once __DIR__ . '/../src/Library/Query.php';

    use CB\Notes\Library\Query;

    function ensure(bool $condition, string $name): void {
        if (!$condition) {
            fwrite(STDERR, "FAIL library query: {$name}\n");
            exit(1);
        }
    }

    $ownerNotebook = str_repeat('a', 32);
    $ownerCategory = str_repeat('b', 32);
    $ownerTag = str_repeat('c', 32);
    $foreignNotebook = str_repeat('d', 32);
    $catalog = [
        'notebook' => [['id' => $ownerNotebook, 'name' => 'Projects']],
        'category' => [['id' => $ownerCategory, 'name' => 'Meetings']],
        'tag' => [['id' => $ownerTag, 'name' => 'Planning']],
    ];

    $state = Query::state([], $catalog);
    $args = Query::args(42, $state);
    ensure(!$state['invalid'] && $state['paged'] === 1, 'default state');
    ensure($args['author'] === 42 && $args['post_type'] === 'cb_note', 'owner scope');
    ensure($args['posts_per_page'] === 20 && $args['no_found_rows'] === false, 'bounded pagination');
    ensure(!in_array('trash', $args['post_status'], true), 'no trashed content');

    $state = Query::state([
        'cbn_notebook' => $ownerNotebook,
        'cbn_category' => $ownerCategory,
        'cbn_tag' => $ownerTag,
        'cbn_search' => '<b>Plan</b>',
        'cbn_sort' => 'title',
        'cbn_paged' => '3',
    ], $catalog);
    $args = Query::args(42, $state);
    ensure(!$state['invalid'] && $args['paged'] === 3, 'combined state');
    ensure($args['s'] === 'Plan' && isset($args['orderby']['title']), 'search and sort');
    ensure(count($args['meta_query']) === 4 && $args['meta_query']['relation'] === 'AND', 'combined filters');
    ensure($args['meta_query'][3]['value'] === 's:32:"' . $ownerTag . '";', 'exact serialized tag');

    $foreign = Query::state(['cbn_notebook' => $foreignNotebook], $catalog);
    ensure($foreign['invalid'] && Query::args(42, $foreign)['post__in'] === [0], 'foreign notebook fail closed');
    $malformed = Query::state(['cbn_category' => 'not-a-valid-id'], $catalog);
    ensure($malformed['invalid'] && Query::args(42, $malformed)['post__in'] === [0], 'invalid category fail closed');
    ensure(Query::args(0, Query::state([], $catalog))['post__in'] === [0], 'anonymous fail closed');

    $unfiled = Query::args(42, Query::state(['cbn_notebook' => 'unfiled'], $catalog));
    ensure($unfiled['meta_query'][1]['relation'] === 'OR', 'unfiled missing-or-empty');

    $clamped = Query::state(['cbn_paged' => '99999999', 'cbn_sort' => 'invalid', 'cbn_search' => str_repeat('a', 200)], $catalog);
    ensure($clamped['paged'] === 9999 && $clamped['sort'] === 'modified' && strlen($clamped['search']) === 120, 'bounded input');
    echo "PASS PHP: tests/library-query-smoke.php\n";
}

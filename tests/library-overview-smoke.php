<?php
declare(strict_types=1);

// Isolated presentation smoke. This is NOT a live WordPress isolation test.
namespace CB\Notes {
    final class Plugin { public const TYPE = 'cb_note'; }
    final class Access {
        public static function can_read(\WP_Post $post): bool {
            return $post->post_author === get_current_user_id();
        }
    }
}
namespace CB\Notes\Organization {
    final class Catalog {
        public static function labels_for_post(int $id): array {
            return $GLOBALS['labels'][$id] ?? ['notebook' => '', 'category' => '', 'tags' => []];
        }
    }
}
namespace CB\Notes\Library {
    final class Markers {
        public static function for_post(int $id): array {
            return ['favorite' => false, 'pinned' => false];
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
            public string $post_modified = '2026-10-13 10:00:00'
        ) {}
    }
    function get_current_user_id(): int { return 42; }
    function current_user_can(string $cap, int $id = 0): bool { return true; }
    function get_edit_post_link(int $id): string { return '/edit/' . $id; }
    function get_delete_post_link(int $id, string $deprecated = '', bool $force = false): string { return '/trash/' . $id; }
    function get_the_title(WP_Post $post): string { return 'Note #' . $post->ID; }
    function get_option(string $key): string { return $key === 'date_format' ? 'M j, Y' : 'g:i a'; }
    function mysql2date(string $format, string $date, bool $translate = true): string { return 'Oct 13, 2026 10:00 am'; }
    function __(string $text, string $domain): string { return $text; }
    function esc_html__(string $text, string $domain): string { return esc_html(__($text, $domain)); }
    function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    function esc_url(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    function wp_create_nonce(string $action): string { return 'test-nonce'; }

    require_once dirname(__DIR__) . '/src/Library/Page.php';
    use CB\Notes\Library\Page;

    function check_view(bool $yes, string $message): void {
        if (!$yes) {
            fwrite(STDERR, "FAIL Library overview: {$message}\n");
            exit(1);
        }
        echo "PASS Library overview: {$message}\n";
    }
    function render_note_for(int $id, int $owner = 42): string {
        ob_start();
        (new \ReflectionMethod(Page::class, 'render_note'))->invoke(null, new WP_Post($id, $owner), 42);
        return (string) ob_get_clean();
    }

    $GLOBALS['labels'][1] = [
        'notebook' => '', 'category' => '', 'tags' => [],
    ];
    $empty = render_note_for(1);
    check_view(str_contains($empty, 'Notebook:') && str_contains($empty, 'Unfiled'),
        'Unfiled note has clear notebook metadata');
    check_view(!str_contains($empty, 'Category:') && !str_contains($empty, 'Tags:'),
        'Missing classifications do not create empty chips');

    $GLOBALS['labels'][2] = [
        'notebook' => 'Research & Planning',
        'category' => 'External',
        'tags' => ['Alpha', 'Bravo', 'Charlie', 'Delta', '<unsafe>'],
    ];
    $full = render_note_for(2);
    check_view(str_contains($full, 'Research &amp; Planning') &&
        str_contains($full, 'Category:') && str_contains($full, 'External'),
        'Labeled notebook/category render with escaped text');
    check_view(str_contains($full, 'Alpha') && str_contains($full, 'Bravo') &&
        str_contains($full, 'Charlie') && str_contains($full, '>+2</span>'),
        'Only first three tags shown, remaining count correct');
    check_view(str_contains($full, 'Additional tags:') &&
        str_contains($full, 'Delta') && str_contains($full, '&lt;unsafe&gt;') &&
        !str_contains($full, '<unsafe>'),
        'Overflow title includes escaped private extra tags');

    $GLOBALS['labels'][3] = [
        'notebook' => 'Projects', 'category' => '',
        'tags' => ['One', 'Two', 'Three'],
    ];
    $three = render_note_for(3);
    check_view(!str_contains($three, 'tag-more') && str_contains($three, 'Tags:'),
        'Exactly three tags do not show overflow indicator');
    check_view(str_contains($three, 'cb-notes-library__actions') &&
        str_contains($three, 'dashicons-star-filled') &&
        str_contains($three, 'dashicons-edit') &&
        str_contains($three, 'dashicons-trash'),
        'Four existing note actions remain intact');

    $foreign = render_note_for(2, 999);
    check_view($foreign === '', 'Foreign-owned note renders no metadata or actions');

    echo "PASS PHP: tests/library-overview-smoke.php\n";
}

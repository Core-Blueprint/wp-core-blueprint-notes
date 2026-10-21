<?php
declare(strict_types=1);

namespace CoreBlueprint\Core\DataExchange {
    final class Foundation {
        public const MODE_CREATE_ONLY = 'create_only';
        public const OP_CREATE = 'create';
    }
}
namespace CB\Notes {
    final class Plugin { public const TYPE = 'cb_note'; }
}
namespace CB\Notes\Organization {
    final class Catalog {
        public const USER_META = 'cb_notes_personal_catalog_v1';
        public const NOTEBOOK = 'cb_notes_notebook_id';
        public const CATEGORY = 'cb_notes_category_id';
        public const TAGS = 'cb_notes_tag_ids';
        public static function catalog(int $owner): array {
            return $GLOBALS['catalog'] ?? ['notebook' => [], 'category' => [], 'tag' => []];
        }
    }
}
namespace CB\Notes\Library {
    final class Markers {
        public const FAVORITE = '_cb_notes_favorite';
        public const PINNED = '_cb_notes_pinned';
    }
}
namespace CB\Notes\Portability {
    final class ImportSession {
        public static function authorized(array $context): bool {
            return ($context['ticket'] ?? '') === 'trusted';
        }
        public static function catalog(array $context): array {
            return ['source' => $GLOBALS['original_catalog']];
        }
        public static function target_plan(): array {
            return ['previous_catalog' => $GLOBALS['previous_catalog'], 'catalog' => $GLOBALS['desired_catalog']];
        }
        public static function target_catalog(): array {
            return $GLOBALS['desired_catalog'];
        }
    }
    final class ImportPlanner {
        public static function plan_record(array $source, string $mode, int $version, array $context): array {
            return $GLOBALS['expected_plan'];
        }
    }
    final class ImportJournal {
        public const META = '_cb_notes_import_receipt_v1';
        public static function reserve(int $owner, string $digest, string $reference): string|\WP_Error {
            $key = $owner . ':' . $digest . ':' . $reference;
            if (isset($GLOBALS['reserved'][$key])) {
                return new \WP_Error('reserved', 'Unsafe automatic retry refused');
            }
            $GLOBALS['reserved'][$key] = 'pending';
            return $key;
        }
        public static function complete(int $owner, string $digest, string $reference, int $id): true|\WP_Error {
            $key = $owner . ':' . $digest . ':' . $reference;
            if (($GLOBALS['post_meta'][$id][self::META] ?? null) !== $key) {
                return new \WP_Error('bad receipt', 'Not same note');
            }
            $GLOBALS['reserved'][$key] = 'complete';
            return true;
        }
    }
}
namespace {
    final class WP_Error {
        public function __construct(public string $code, public string $message) {}
    }
    final class WP_Post {
        public string $post_date = '2026-10-21 12:00:00';
        public string $post_date_gmt = '2026-10-21 12:00:00';
        public string $post_modified = '2026-10-21 12:00:00';
        public string $post_modified_gmt = '2026-10-21 12:00:00';
        public function __construct(
            public int $ID, public int $post_author, public string $post_type,
            public string $post_status, public string $post_title, public string $post_content
        ) {}
    }
    $GLOBALS['owner'] = 10;
    $GLOBALS['catalog'] = ['notebook' => [], 'category' => [], 'tag' => []];
    $GLOBALS['previous_catalog'] = $GLOBALS['catalog'];
    $GLOBALS['original_catalog'] = [
        'record_type' => 'catalog', 'reference' => 'catalog:personal',
        'catalog' => ['notebook' => [['id' => str_repeat('a', 32), 'name' => 'Work']],
            'category' => [], 'tag' => []],
    ];
    $GLOBALS['desired_catalog'] = [
        'notebook' => [['id' => str_repeat('b', 32), 'name' => 'Work']],
        'category' => [], 'tag' => [],
    ];
    $GLOBALS['user_meta'] = [];
    $GLOBALS['posts'] = [];
    $GLOBALS['post_meta'] = [];
    $GLOBALS['reserved'] = [];
    // Mock WordPress posts table: model a failed, conditional SQL date write.
    $GLOBALS['wpdb'] = new class {
        public string $posts = 'wp_posts';
        public bool $fail_date_write = false;
        public function update(string $table, array $values, array $where,
            array $value_formats, array $where_formats): int|false {
            if ($this->fail_date_write || $table !== $this->posts
                || $where['post_type'] !== 'cb_note'
                || $where['post_author'] !== $GLOBALS['owner']
                || $value_formats !== ['%s', '%s', '%s', '%s']
                || $where_formats !== ['%d', '%s', '%d']) {
                return false;
            }
            $post = $GLOBALS['posts'][$where['ID']] ?? null;
            if (!$post instanceof WP_Post
                || $post->post_author !== $where['post_author']
                || $post->post_type !== $where['post_type']) {
                return false;
            }
            foreach ($values as $key => $value) {
                $post->$key = $value;
            }
            return 1;
        }
    };
    function get_current_user_id(): int { return $GLOBALS['owner']; }
    function current_user_can(string $cap): bool { return $cap === 'cb_notes_edit'; }
    function get_user_meta(int $id, string $key, bool $single): mixed {
        return $single ? ($GLOBALS['user_meta'][$key] ?? '') :
            (isset($GLOBALS['user_meta'][$key]) ? [$GLOBALS['user_meta'][$key]] : []);
    }
    function add_user_meta(int $id, string $key, mixed $value, bool $unique): int|false {
        if (isset($GLOBALS['user_meta'][$key])) { return false; }
        $GLOBALS['user_meta'][$key] = $value;
        $GLOBALS['catalog'] = $value;
        return 1;
    }
    function update_user_meta(int $id, string $key, mixed $value, mixed $old): int|false {
        if (($GLOBALS['user_meta'][$key] ?? null) !== $old) { return false; }
        $GLOBALS['user_meta'][$key] = $value;
        $GLOBALS['catalog'] = $value;
        return 1;
    }
    function wp_insert_post(array $values, bool $return_error): int|WP_Error {
        if ($GLOBALS['fail_insert'] ?? false) {
            return new WP_Error('injected', 'WordPress insertion failed');
        }
        $id = count($GLOBALS['posts']) + 101;
        $GLOBALS['posts'][$id] = new WP_Post(
            $id, $values['post_author'], $values['post_type'],
            $values['post_status'], $values['post_title'], $values['post_content']
        );
        $GLOBALS['post_meta'][$id] = $values['meta_input'];
        return $id;
    }
    function clean_post_cache(int $id): void {
        $GLOBALS['cleaned_post_cache'][] = $id;
    }
    function get_post(int $id): ?WP_Post { return $GLOBALS['posts'][$id] ?? null; }
    function get_post_meta(int $id, string $key, bool $single): mixed {
        return $GLOBALS['post_meta'][$id][$key] ?? '';
    }
    function get_date_from_gmt(string $date): string { return $date; }
    function check_apply(bool $yes, string $label): void {
        if (!$yes) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    }

    require dirname(__DIR__) . '/src/Portability/ImportApplier.php';
    use CB\Notes\Portability\ImportApplier;
    $ctx = ['ticket' => 'trusted'];
    $digest = str_repeat('a', 64);
    $catalogPlan = [
        'operation' => 'create', 'reference' => 'catalog:personal',
        'payload' => [
            'record_type' => 'catalog', 'owner' => 10, 'digest' => $digest,
            'catalog' => $GLOBALS['desired_catalog']
        ],
        'warnings' => [],
    ];
    $GLOBALS['expected_plan'] = $catalogPlan;
    check_apply(ImportApplier::apply($catalogPlan, $ctx) === ['reference' => 'catalog:personal'],
        'catalog reconciled for owner');
    check_apply($GLOBALS['catalog'] === $GLOBALS['desired_catalog'],
        'destination catalog uses mapped opaque ID');
    $source = [
        'record_type' => 'note', 'reference' => 'note:11',
        'title' => 'Draft text', 'content' => '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
        'status' => 'private', 'created_gmt' => '2026-10-19 10:00:00',
        'modified_gmt' => '2026-10-20 17:31:20',
        'notebook_id' => str_repeat('a', 32), 'category_id' => '',
        'tag_ids' => [], 'favorite' => true, 'pinned' => false,
    ];
    $notePlan = [
        'operation' => 'create', 'reference' => 'note:11',
        'payload' => [
            'record_type' => 'note', 'owner' => 10, 'digest' => $digest,
            'source' => $source,
            'mapped' => [
                'operation' => 'create', 'source_reference' => 'note:11',
                'status' => 'private', 'notebook_id' => str_repeat('b', 32),
                'category_id' => '', 'tag_ids' => [], 'warnings' => [],
            ],
        ],
        'warnings' => [],
    ];
    $GLOBALS['expected_plan'] = $notePlan;
    $created = ImportApplier::apply($notePlan, $ctx);
    check_apply(is_array($created) && $created['reference'] === 'note:11'
        && count($GLOBALS['posts']) === 1, 'authorized record created once');
    check_apply($GLOBALS['posts'][101]->post_date_gmt === $source['created_gmt']
        && $GLOBALS['posts'][101]->post_modified_gmt === $source['modified_gmt']
        && ($GLOBALS['reserved']['10:' . $digest . ':note:11'] ?? '') === 'complete'
        && in_array(101, $GLOBALS['cleaned_post_cache'] ?? [], true),
        'creation and modification time restored before completed journal receipt');
    check_apply($GLOBALS['post_meta'][101]['cb_notes_notebook_id'] === str_repeat('b', 32)
        && ($GLOBALS['post_meta'][101]['_cb_notes_favorite'] ?? '') === '1'
        && !isset($GLOBALS['post_meta'][101]['_cb_notes_public_snapshot']),
        'relations and markers restored without sharing state');
    check_apply(ImportApplier::apply($notePlan, $ctx) instanceof WP_Error
        && count($GLOBALS['posts']) === 1, 'no duplicate on repeated apply');
    $GLOBALS['expected_plan'] = $notePlan;
    $invalid = $notePlan;
    $invalid['payload']['source']['title'] = 'tampered';
    check_apply(ImportApplier::apply($invalid, $ctx) instanceof WP_Error,
        'altered Base plan denied');
    $other = $notePlan;
    $other['payload']['source']['reference'] = 'note:12';
    $other['reference'] = 'note:12';
    $other['payload']['mapped']['source_reference'] = 'note:12';
    $GLOBALS['expected_plan'] = $other;
    $GLOBALS['fail_insert'] = true;
    check_apply(ImportApplier::apply($other, $ctx) instanceof WP_Error,
        'injected insertion fault surfaced');
    $GLOBALS['fail_insert'] = false;
    check_apply(ImportApplier::apply($other, $ctx) instanceof WP_Error,
        'uncertain prior attempt blocks auto retry');
    $dateFailure = $notePlan;
    $dateFailure['reference'] = 'note:13';
    $dateFailure['payload']['source']['reference'] = 'note:13';
    $dateFailure['payload']['mapped']['source_reference'] = 'note:13';
    $GLOBALS['expected_plan'] = $dateFailure;
    $GLOBALS['wpdb']->fail_date_write = true;
    $dateResult = ImportApplier::apply($dateFailure, $ctx);
    check_apply($dateResult instanceof WP_Error
        && $dateResult->code === 'cb_notes_import_timestamp_write_failed'
        && count($GLOBALS['posts']) === 2
        && ($GLOBALS['reserved']['10:' . $digest . ':note:13'] ?? '') === 'pending',
        'failed timestamp write remains pending, never completed');
    $GLOBALS['wpdb']->fail_date_write = false;
    check_apply(ImportApplier::apply($dateFailure, $ctx) instanceof WP_Error
        && count($GLOBALS['posts']) === 2,
        'timestamp write failure blocks automatic duplicate retry');
    echo "Notes P2-B inert create-only applier fault smoke: PASS (mocked)\n";
}

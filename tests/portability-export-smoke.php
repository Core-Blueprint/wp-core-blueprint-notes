<?php
declare(strict_types=1);
/** Source-only, non-mutating Notes Data Exchange export contract tests. */

namespace CoreBlueprint\Core\DataExchange {
    interface EntityInterface {
        public function is_available(): bool;
        public function schema_version(): int;
        public function supports_schema_version(int $schema_version): bool;
        public function can_export(array $context = []): bool;
        public function can_import(array $context = []): bool;
        public function export_records(array $context = []): iterable|\WP_Error;
        public function plan_import(array $record, string $mode, int $source_schema_version, array $context = []): array|\WP_Error;
        public function apply_import(array $plan, array $context = []): array|\WP_Error;
    }
    final class Foundation {
        public const MAX_RECORDS = 5000;
        public const CONTRACT_OWNER = 'core-blueprint';
        public const CONTRACT_ID = 'data-exchange.entity';
        public const CONTRACT_VERSION = '1';
        public const SUPPORT_EXPORT = 'export';
        public const SUPPORT_JSON = 'format.json';
    }
}
namespace CoreBlueprint\Core\Interoperability {
    final class Registry {
        public static array $entries = [];
        public static function register_implementation(array $record): void {
            self::$entries[] = $record;
        }
    }
}
namespace CB\Notes {
    final class Plugin { public const TYPE = 'cb_note'; public const META_NOTEBOOK = 'cb_notes_notebook'; }
    final class Access {
        public static function can_read(\WP_Post $post): bool {
            return $post->post_type === 'cb_note' && $post->post_status !== 'trash'
                && $post->post_author === get_current_user_id();
        }
    }
}
namespace CB\Notes\Organization {
    final class Catalog {
        public const USER_META = 'cb_notes_personal_catalog_v1';
        public const NOTEBOOK = 'cb_notes_notebook_id';
        public const CATEGORY = 'cb_notes_category_id';
        public const TAGS = 'cb_notes_tag_ids';
        public static function valid_id(mixed $id): bool {
            return is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) === 1;
        }
        public static function catalog(int $user_id): array {
            $raw = get_user_meta($user_id, self::USER_META, true);
            $result = ['notebook' => [], 'category' => [], 'tag' => []];
            if (!is_array($raw)) { return $result; }
            foreach (array_keys($result) as $kind) {
                if (!isset($raw[$kind]) || !is_array($raw[$kind])) { continue; }
                foreach ($raw[$kind] as $item) {
                    if (!is_array($item) || !self::valid_id($item['id'] ?? null)) { continue; }
                    $name = trim(sanitize_text_field($item['name'] ?? ''));
                    if ($name === '' || count($result[$kind]) >= 150) { continue; }
                    $result[$kind][] = ['id' => $item['id'], 'name' => $name];
                }
            }
            return $result;
        }
    }
}
namespace CB\Notes\Library {
    final class Markers {
        public static function for_post(int $id): array {
            return ['favorite' => $id === 11, 'pinned' => $id === 12];
        }
    }
}
namespace {
    define('ABSPATH', __DIR__);
    $GLOBALS['owner'] = 10;
    $GLOBALS['can_edit'] = true;
    $GLOBALS['notes'] = [];
    $GLOBALS['meta'] = [];
    $GLOBALS['catalogs'] = [];
    $GLOBALS['queries'] = [];
    $GLOBALS['forced_count'] = null;

    final class WP_Error {
        public function __construct(public string $code, public string $message) {}
        public function get_error_code(): string { return $this->code; }
    }
    final class WP_Post {
        public function __construct(
            public int $ID, public int $post_author, public string $post_title,
            public string $post_content, public string $post_status = 'private',
            public string $post_type = 'cb_note',
            public string $post_date_gmt = '2026-10-15 11:00:00',
            public string $post_modified_gmt = '2026-10-16 11:00:00'
        ) {}
    }
    final class WP_Query {
        public int $found_posts;
        public array $posts;
        public function __construct(array $args) {
            $GLOBALS['queries'][] = $args;
            $all = array_values(array_filter($GLOBALS['notes'], static fn(WP_Post $p): bool =>
                $p->post_author === $args['author'] && $p->post_type === $args['post_type']
                && in_array($p->post_status, $args['post_status'], true)
            ));
            usort($all, static fn(WP_Post $a, WP_Post $b): int => $a->ID <=> $b->ID);
            $this->found_posts = $GLOBALS['forced_count'] ?? count($all);
            $from = ($args['paged'] - 1) * $args['posts_per_page'];
            $this->posts = array_map(static fn(WP_Post $p): int => $p->ID,
                array_slice($all, $from, $args['posts_per_page']));
        }
    }
    function __(string $s, string $domain): string { return $s; }
    function get_current_user_id(): int { return $GLOBALS['owner']; }
    function current_user_can(string $cap): bool { return $cap === 'cb_notes_edit' && $GLOBALS['can_edit']; }
    function get_user_meta(int $owner, string $key, bool $single): mixed { return $GLOBALS['catalogs'][$owner] ?? ''; }
    function get_post_meta(int $id, string $key, bool $single): mixed { return $GLOBALS['meta'][$id][$key] ?? ''; }
    function get_post(int $id): ?WP_Post { return $GLOBALS['notes'][$id] ?? null; }
    function sanitize_text_field(string $v): string { return trim(strip_tags($v)); }
    function update_post_meta(): never { throw new \RuntimeException('Export must never write'); }
    function expect(bool $pass, string $label): void {
        if (!$pass) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
        echo "PASS: $label\n";
    }

    require dirname(__DIR__) . '/src/Portability/NotesEntity.php';
    require dirname(__DIR__) . '/src/Portability/Provider.php';

    use CB\Notes\Portability\NotesEntity;
    use CB\Notes\Portability\Provider;
    use CoreBlueprint\Core\Interoperability\Registry;
    use CB\Notes\Organization\Catalog;

    $nb = str_repeat('a', 32);
    $category = str_repeat('b', 32);
    $tag = str_repeat('c', 32);
    $GLOBALS['catalogs'][10] = [
        'notebook' => [['id' => $nb, 'name' => 'Planning']],
        'category' => [['id' => $category, 'name' => 'Work']],
        'tag' => [['id' => $tag, 'name' => 'Governance']],
    ];
    $GLOBALS['catalogs'][20] = [
        'notebook' => [['id' => str_repeat('d', 32), 'name' => 'Foreign']],
        'category' => [], 'tag' => [],
    ];
    $GLOBALS['notes'][11] = new WP_Post(11, 10, 'My private note', '<!-- wp:paragraph --><p>Private</p><!-- /wp:paragraph -->');
    $GLOBALS['notes'][12] = new WP_Post(12, 10, 'Draft', '<!-- wp:heading --><h2>Plan</h2><!-- /wp:heading -->', 'draft');
    $GLOBALS['notes'][99] = new WP_Post(99, 20, 'Foreign secret', 'SECRET');
    $GLOBALS['meta'][11] = [Catalog::NOTEBOOK => $nb, Catalog::CATEGORY => $category, Catalog::TAGS => [$tag]];
    $GLOBALS['meta'][12] = [Catalog::NOTEBOOK => '', Catalog::CATEGORY => '', Catalog::TAGS => ''];
    $GLOBALS['meta'][99] = [Catalog::NOTEBOOK => str_repeat('d', 32)];

    Provider::register();
    expect(count(Registry::$entries) === 1, 'One Base Data Exchange provider registered');
    $entry = Registry::$entries[0];
    expect($entry['provider'] === 'core-blueprint-notes' && $entry['id'] === 'personal-notes', 'Stable Notes entity identity');
    expect($entry['supports'] === ['export', 'format.json'], 'Read-only JSON export advertised; import/CSV not advertised');
    $entity = ($entry['factory'])();
    expect($entity instanceof NotesEntity && $entity->schema_version() === 1, 'Versioned provider factory');
    expect($entity->can_export() && !$entity->can_import(), 'Owner export allowed but import remains unavailable');
    $records = $entity->export_records();
    expect(is_array($records) && count($records) === 3, 'Complete catalog plus two own Notes exported');
    expect($records[0]['record_type'] === 'catalog' && $records[0]['catalog']['tag'][0]['name'] === 'Governance', 'Catalog names and opaque IDs retained');
    expect($records[1]['notebook_id'] === $nb && $records[1]['category_id'] === $category && $records[1]['tag_ids'] === [$tag], 'Note relations retained');
    expect($records[1]['content'] === $GLOBALS['notes'][11]->post_content && $records[2]['status'] === 'draft', 'Gutenberg source and status retained');
    expect($records[1]['favorite'] === true && $records[2]['pinned'] === true, 'Personal marker state exported');
    expect(!str_contains(json_encode($records, JSON_THROW_ON_ERROR), 'Foreign secret'), 'Foreign author content omitted');
    expect(!str_contains(json_encode($records, JSON_THROW_ON_ERROR), 'secure_grant'), 'No sharing authority fields in export');
    expect($GLOBALS['queries'][0]['author'] === 10 && $GLOBALS['queries'][0]['posts_per_page'] === 100, 'Bounded owner-only SQL queries');
    expect($entity->export_records(['owner_id' => 20]) instanceof WP_Error, 'Actor override rejected');
    expect($entity->export_records(['user_id' => 20]) instanceof WP_Error, 'User override rejected');
    expect($entity->export_records(['author' => 20]) instanceof WP_Error, 'Author override rejected');
    $GLOBALS['owner'] = 0;
    expect($entity->export_records() instanceof WP_Error, 'Anonymous export rejected');
    $GLOBALS['owner'] = 10;
    $GLOBALS['can_edit'] = false;
    expect($entity->export_records() instanceof WP_Error, 'Permissionless export rejected');
    $GLOBALS['can_edit'] = true;
    $GLOBALS['meta'][11][Catalog::NOTEBOOK] = str_repeat('e', 32);
    $err = $entity->export_records();
    expect($err instanceof WP_Error && $err->code === 'cb_notes_export_invalid_relation', 'Broken note-to-notebook mapping fails closed');
    $GLOBALS['meta'][11][Catalog::NOTEBOOK] = $nb;
    $GLOBALS['catalogs'][10]['notebook'][] = ['id' => 'invalid', 'name' => 'Broken'];
    $err = $entity->export_records();
    expect($err instanceof WP_Error && $err->code === 'cb_notes_export_invalid_catalog', 'Malformed catalog fails instead of omitting items');
    array_pop($GLOBALS['catalogs'][10]['notebook']);
    $GLOBALS['forced_count'] = 5000;
    $err = $entity->export_records();
    expect($err instanceof WP_Error && $err->code === 'cb_notes_export_changed', 'Overflow refuses instead of truncating');
    $GLOBALS['forced_count'] = null;
    expect($entity->plan_import([], 'create_only', 1) instanceof WP_Error, 'No import planning before authorization');
    expect($entity->apply_import([]) instanceof WP_Error, 'No hidden import mutation');
    echo "Notes Data Exchange export smoke: PASS\n";
}

<?php
declare(strict_types=1);

namespace CB\Notes\Organization;

use CB\Notes\Access;
use CB\Notes\Plugin;

defined('ABSPATH') || exit;

/**
 * A private, per-owner catalog, never a site-wide WordPress taxonomy.
 * IDs are opaque; the catalog is authoritative, and unrecognized IDs do not
 * resolve to labels or filter results. Public/Secure Share never serializes it.
 */
final class Catalog {
    public const USER_META = 'cb_notes_personal_catalog_v1';
    public const NOTEBOOK = 'cb_notes_notebook_id';
    public const CATEGORY = 'cb_notes_category_id';
    public const TAGS = 'cb_notes_tag_ids';
    private const KINDS = ['notebook', 'category', 'tag'];
    private const META = [
        'notebook' => self::NOTEBOOK,
        'category' => self::CATEGORY,
        'tag' => self::TAGS,
    ];
    private const ID_PATTERN = '/^[a-f0-9]{32}$/D';
    private const MAX_ITEMS = 150;
    private const MAX_TAGS_PER_NOTE = 12;
    private static string $admin_hook = '';

    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 7);
        add_action('rest_api_init', [self::class, 'register_rest']);
        add_filter('rest_pre_insert_' . Plugin::TYPE, [self::class, 'validate_note_rest'], 10, 2);
        add_action('admin_init', [self::class, 'migrate_legacy'], 24);
        add_action('admin_menu', [self::class, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin']);
        add_action('restrict_manage_posts', [self::class, 'filters'], 10, 2);
        add_action('pre_get_posts', [self::class, 'filter_list'], 30);
        add_filter('manage_' . Plugin::TYPE . '_posts_columns', [self::class, 'columns'], 20);
        add_action('manage_' . Plugin::TYPE . '_posts_custom_column', [self::class, 'column'], 20, 2);
    }

    private static function blank(): array {
        return ['notebook' => [], 'category' => [], 'tag' => []];
    }

    public static function valid_id(mixed $id): bool {
        return is_string($id) && preg_match(self::ID_PATTERN, $id) === 1;
    }

    public static function catalog(int $user_id): array {
        if ($user_id < 1) {
            return self::blank();
        }
        $raw = get_user_meta($user_id, self::USER_META, true);
        $result = self::blank();
        if (!is_array($raw)) {
            return $result;
        }
        foreach (self::KINDS as $kind) {
            if (!isset($raw[$kind]) || !is_array($raw[$kind])) {
                continue;
            }
            foreach ($raw[$kind] as $item) {
                if (!is_array($item) || !self::valid_id($item['id'] ?? null)) {
                    continue;
                }
                $name = self::normalize_name($item['name'] ?? '');
                if ('' === $name || count($result[$kind]) >= self::MAX_ITEMS) {
                    continue;
                }
                $result[$kind][] = ['id' => $item['id'], 'name' => $name];
            }
        }
        return $result;
    }

    private static function normalize_name(mixed $value): string {
        if (!is_string($value)) {
            return '';
        }
        $name = trim(sanitize_text_field($value));
        return function_exists('mb_substr') ? mb_substr($name, 0, 80) : substr($name, 0, 80);
    }

    private static function name_key(string $name): string {
        return function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    }

    private static function label(array $catalog, string $kind, string $id): string {
        foreach ($catalog[$kind] ?? [] as $item) {
            if ($item['id'] === $id) {
                return $item['name'];
            }
        }
        return '';
    }

    public static function contains(int $user_id, string $kind, mixed $id): bool {
        return in_array($kind, self::KINDS, true) && self::valid_id($id)
            && self::label(self::catalog($user_id), $kind, $id) !== '';
    }

    private static function error(string $code, string $message, int $status = 422): \WP_Error {
        return new \WP_Error($code, $message, ['status' => $status]);
    }

    public static function register_meta(): void {
        foreach ([self::NOTEBOOK, self::CATEGORY] as $key) {
            register_post_meta(Plugin::TYPE, $key, [
                'single' => true, 'type' => 'string', 'default' => '',
                'show_in_rest' => ['schema' => ['type' => 'string', 'pattern' => '^[a-f0-9]{32}$|^$']],
                'sanitize_callback' => static fn (mixed $v): string => self::valid_id($v) ? $v : '',
                'auth_callback' => [self::class, 'can_edit_meta'],
            ]);
        }
        register_post_meta(Plugin::TYPE, self::TAGS, [
            'single' => true, 'type' => 'array', 'default' => [],
            'show_in_rest' => ['schema' => [
                'type' => 'array', 'maxItems' => self::MAX_TAGS_PER_NOTE,
                'items' => ['type' => 'string', 'pattern' => '^[a-f0-9]{32}$'],
            ]],
            'sanitize_callback' => static function (mixed $values): array {
                if (!is_array($values)) {
                    return [];
                }
                return array_slice(array_values(array_unique(array_filter($values, [self::class, 'valid_id']))), 0, self::MAX_TAGS_PER_NOTE);
            },
            'auth_callback' => [self::class, 'can_edit_meta'],
        ]);
    }

    public static function can_edit_meta(bool $allowed, string $key, int $post_id, int $user_id): bool {
        unset($allowed, $key);
        $post = get_post($post_id);
        return $post instanceof \WP_Post && Access::allowed($post, 'edit', $user_id);
    }

    /** Restrict values coming from the Gutenberg native REST post-meta save. */
    public static function validate_note_rest(mixed $prepared, \WP_REST_Request $request): mixed {
        if (is_wp_error($prepared)) {
            return $prepared;
        }
        $meta = $request->get_param('meta');
        if (!is_array($meta)) {
            return $prepared;
        }
        $post_id = absint($request->get_param('id'));
        $post = $post_id > 0 ? get_post($post_id) : null;
        $owner_id = $post instanceof \WP_Post ? (int) $post->post_author : get_current_user_id();
        if ($owner_id < 1 || $owner_id !== get_current_user_id()) {
            // Only a manager with explicit object access may preserve an
            // owner's unchanged labels while editing that note's body.
            // Other users must not even replay another owner's metadata.
            $may_preserve = $post instanceof \WP_Post
                && current_user_can('cb_notes_manage_all')
                && current_user_can('edit_post', (int)$post->ID);
            foreach ([self::NOTEBOOK, self::CATEGORY, self::TAGS] as $field) {
                if (!array_key_exists($field, $meta)) {
                    continue;
                }
                $saved = $post instanceof \WP_Post ? get_post_meta((int)$post->ID, $field, true) : null;
                if (!$may_preserve || $saved !== $meta[$field]) {
                    return self::error('cb_notes_organization_forbidden',
                        'Personal classifications belong to the note owner.', 403);
                }
            }
            return $prepared;
        }
        $cat = self::catalog($owner_id);
        foreach (['notebook' => self::NOTEBOOK, 'category' => self::CATEGORY] as $kind => $field) {
            if (!array_key_exists($field, $meta)) {
                continue;
            }
            $value = $meta[$field];
            if (!is_string($value) || ($value !== '' && self::label($cat, $kind, $value) === '')) {
                return self::error('cb_notes_organization_invalid', 'Unknown personal organization item.');
            }
        }
        if (array_key_exists(self::TAGS, $meta)) {
            $tags = $meta[self::TAGS];
            if (!is_array($tags) || count($tags) > self::MAX_TAGS_PER_NOTE || count(array_unique($tags)) !== count($tags)) {
                return self::error('cb_notes_organization_invalid', 'Invalid personal tags.');
            }
            foreach ($tags as $id) {
                if (!is_string($id) || self::label($cat, 'tag', $id) === '') {
                    return self::error('cb_notes_organization_invalid', 'Unknown personal tag.');
                }
            }
        }
        return $prepared;
    }

    public static function register_rest(): void {
        register_rest_route('core-blueprint-notes/v1', '/organization', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [self::class, 'list_items'],
                'permission_callback' => [self::class, 'can_organize'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'mutate'],
                'permission_callback' => [self::class, 'can_organize'],
            ],
        ]);
    }

    public static function can_organize(): bool {
        return get_current_user_id() > 0 && current_user_can('cb_notes_edit');
    }

    public static function list_items(): array {
        return self::catalog(get_current_user_id());
    }

    /** @return array|\WP_Error */
    public static function mutate(\WP_REST_Request $request): array|\WP_Error {
        if (!self::can_organize()) {
            return self::error('cb_notes_organization_forbidden', 'Access denied.', 403);
        }
        $kind = (string) $request->get_param('kind');
        $action = (string) $request->get_param('action');
        if (!in_array($kind, self::KINDS, true) || !in_array($action, ['create', 'rename', 'delete'], true)) {
            return self::error('cb_notes_organization_invalid', 'Invalid organization action.');
        }
        $owner = get_current_user_id();
        $items = self::catalog($owner);
        $id = $request->get_param('id');
        $name = self::normalize_name($request->get_param('name'));
        if ($action !== 'create' && !self::valid_id($id)) {
            return self::error('cb_notes_organization_invalid', 'Invalid item.');
        }
        $index = null;
        foreach ($items[$kind] as $i => $item) {
            if ($item['id'] === $id) {
                $index = $i;
                break;
            }
        }
        if ($action !== 'create' && $index === null) {
            return self::error('cb_notes_organization_not_found', 'Organization item not found.', 404);
        }
        if ($action !== 'delete') {
            if ($name === '') {
                return self::error('cb_notes_organization_invalid', 'A name is required.');
            }
            foreach ($items[$kind] as $item) {
                if ($item['id'] !== $id && self::name_key($item['name']) === self::name_key($name)) {
                    return self::error('cb_notes_organization_duplicate', 'This name already exists.', 409);
                }
            }
        }
        if ($action === 'create') {
            if (count($items[$kind]) >= self::MAX_ITEMS) {
                return self::error('cb_notes_organization_limit', 'Organization item limit reached.', 409);
            }
            $items[$kind][] = ['id' => bin2hex(random_bytes(16)), 'name' => $name];
        } elseif ($action === 'rename') {
            if ($items[$kind][$index]['name'] === $name) {
                return $items;
            }
            $items[$kind][$index]['name'] = $name;
        } else {
            $cleaned = self::clear_references($owner, $kind, $id);
            if (is_wp_error($cleaned)) {
                return $cleaned;
            }
            array_splice($items[$kind], $index, 1);
        }
        if (!update_user_meta($owner, self::USER_META, $items)) {
            return self::error('cb_notes_organization_storage', 'Could not save organization changes.', 500);
        }
        return $items;
    }

    /**
     * Before catalog deletion, remove exact references in a bounded owner-only
     * batch. Refuse a large operation rather than leaving a partial unknown scope.
     */
    private static function clear_references(int $owner, string $kind, string $id): true|\WP_Error {
        $key = self::META[$kind];
        $ids = get_posts([
            'post_type' => Plugin::TYPE, 'post_status' => [
                'private', 'publish', 'draft', 'pending', 'future', 'trash',
            ],
            'author' => $owner, 'fields' => 'ids', 'posts_per_page' => 251,
            'suppress_filters' => true,
            'meta_query' => [[
                'key' => $key,
                'value' => $kind === 'tag' ? 's:32:"' . $id . '";' : $id,
                'compare' => $kind === 'tag' ? 'LIKE' : '=',
            ]],
        ]);
        if (!is_array($ids)) {
            return self::error('cb_notes_organization_query', 'Could not verify affected notes.', 500);
        }
        if (count($ids) > 250) {
            return self::error('cb_notes_organization_limit',
                'This label belongs to more than 250 notes. Bulk removal needs an operator-reviewed batch.', 409);
        }
        foreach ($ids as $post_id) {
            $post = get_post((int)$post_id);
            if (!$post instanceof \WP_Post || $post->post_type !== Plugin::TYPE
                || (int)$post->post_author !== $owner) {
                return self::error('cb_notes_organization_scope', 'Could not verify note ownership.', 403);
            }
            if ($kind === 'tag') {
                $old = get_post_meta((int)$post_id, self::TAGS, true);
                if (!is_array($old)) {
                    continue;
                }
                $next = array_values(array_filter($old, static fn($value) => $value !== $id));
                if ($next !== $old && !update_post_meta((int)$post_id, self::TAGS, $next)) {
                    return self::error('cb_notes_organization_storage', 'Could not update a note tag assignment.', 500);
                }
            } else {
                $before = get_post_meta((int)$post_id, $key, true);
                if ($before === $id && !update_post_meta((int)$post_id, $key, '')) {
                    return self::error('cb_notes_organization_storage', 'Could not clear a note assignment.', 500);
                }
            }
        }
        return true;
    }

    /** Alpha1/2 text-only notebooks are imported for their owner in bounded batches. */
    public static function migrate_legacy(): void {
        if (!self::can_organize()) {
            return;
        }
        $owner = get_current_user_id();
        $ids = get_posts([
            'post_type' => Plugin::TYPE, 'post_status' => ['private', 'publish', 'draft', 'pending', 'future'],
            'author' => $owner, 'fields' => 'ids', 'posts_per_page' => 30,
            'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => Plugin::META_NOTEBOOK, 'compare' => 'EXISTS'],
                ['key' => self::NOTEBOOK, 'compare' => 'NOT EXISTS'],
            ],
        ]);
        if (!is_array($ids) || $ids === []) {
            return;
        }
        $items = self::catalog($owner);
        $changed = false;
        $assignments = [];
        foreach ($ids as $id) {
            $name = self::normalize_name(get_post_meta((int)$id, Plugin::META_NOTEBOOK, true));
            if ($name === '') {
                $assignments[(int)$id] = '';
                continue;
            }
            $found = '';
            foreach ($items['notebook'] as $item) {
                if (self::name_key($item['name']) === self::name_key($name)) {
                    $found = $item['id'];
                    break;
                }
            }
            if ($found === '' && count($items['notebook']) < self::MAX_ITEMS) {
                $found = bin2hex(random_bytes(16));
                $items['notebook'][] = ['id' => $found, 'name' => $name];
                $changed = true;
            }
            if ($found !== '') {
                $assignments[(int)$id] = $found;
            }
        }
        if ($changed && !update_user_meta($owner, self::USER_META, $items)) {
            return;
        }
        // First persist catalog IDs, then references. No note may refer to
        // a newly created catalog ID that failed to save.
        foreach ($assignments as $id => $ref) {
            update_post_meta($id, self::NOTEBOOK, $ref);
        }
    }

    /** Labels are resolved only from the canonical note author's catalog. */
    public static function labels_for_post(int $post_id): array {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post || $post->post_type !== Plugin::TYPE || !Access::can_read($post)) {
            return ['notebook' => '', 'category' => '', 'tags' => []];
        }
        $cat = self::catalog((int)$post->post_author);
        $tags = get_post_meta($post_id, self::TAGS, true);
        return [
            'notebook' => self::label($cat, 'notebook', (string)get_post_meta($post_id, self::NOTEBOOK, true)),
            'category' => self::label($cat, 'category', (string)get_post_meta($post_id, self::CATEGORY, true)),
            'tags' => array_values(array_filter(array_map(
                static fn($id) => self::label($cat, 'tag', (string)$id),
                is_array($tags) ? $tags : []
            ))),
        ];
    }

    public static function columns(array $columns): array {
        $columns['cb_notes_category'] = __('Category', 'core-blueprint-notes');
        $columns['cb_notes_tags'] = __('Tags', 'core-blueprint-notes');
        return $columns;
    }

    public static function column(string $column, int $post_id): void {
        if (!in_array($column, ['cb_notes_notebook', 'cb_notes_category', 'cb_notes_tags'], true)) {
            return;
        }
        $labels = self::labels_for_post($post_id);
        if ($column === 'cb_notes_notebook') {
            echo esc_html($labels['notebook']);
        } elseif ($column === 'cb_notes_category') {
            echo esc_html($labels['category']);
        } else {
            echo esc_html(implode(', ', $labels['tags']));
        }
    }

    public static function filters(string $post_type, string $which = 'top'): void {
        if ($post_type !== Plugin::TYPE || $which !== 'top' || !self::can_organize()) {
            return;
        }
        $cat = self::catalog(get_current_user_id());
        foreach (self::META as $kind => $meta_key) {
            $param = 'cb_notes_filter_' . $kind;
            $selected = isset($_GET[$param]) && is_string($_GET[$param])
                ? sanitize_text_field(wp_unslash($_GET[$param])) : '';
            echo '<label class="screen-reader-text" for="' . esc_attr($param) . '">'
                . esc_html(sprintf(__('Filter by %s', 'core-blueprint-notes'), $kind)) . '</label>';
            echo '<select name="' . esc_attr($param) . '" id="' . esc_attr($param) . '">';
            $all_label = match ($kind) {
                'notebook' => __('All notebooks', 'core-blueprint-notes'),
                'category' => __('All categories', 'core-blueprint-notes'),
                'tag' => __('All tags', 'core-blueprint-notes'),
                default => '',
            };
            echo '<option value="">' . esc_html($all_label) . '</option>';
            foreach ($cat[$kind] as $item) {
                echo '<option value="' . esc_attr($item['id']) . '" ' .
                    selected($selected, $item['id'], false) . '>' . esc_html($item['name']) . '</option>';
            }
            echo '</select>';
        }
    }

    public static function filter_list(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== Plugin::TYPE || !self::can_organize()) {
            return;
        }
        $catalog = self::catalog(get_current_user_id());
        $clauses = (array)$query->get('meta_query');
        foreach (self::META as $kind => $key) {
            $param = 'cb_notes_filter_' . $kind;
            $id = isset($_GET[$param]) && is_string($_GET[$param])
                ? sanitize_text_field(wp_unslash($_GET[$param])) : '';
            if ($id === '') {
                continue;
            }
            if (self::label($catalog, $kind, $id) === '') {
                $query->set('post__in', [0]);
                return;
            }
            $clauses[] = [
                'key' => $key,
                // WordPress serializes ID arrays. This matches an exact,
                // length-bounded serialized tag ID rather than a substring.
                'value' => $kind === 'tag' ? 's:32:"' . $id . '";' : $id,
                'compare' => $kind === 'tag' ? 'LIKE' : '=',
            ];
        }
        if ($clauses !== []) {
            $query->set('meta_query', $clauses);
        }
    }

    public static function register_admin_menu(): void {
        self::$admin_hook = (string)add_submenu_page('edit.php?post_type=' . Plugin::TYPE,
            __('Organize Notes', 'core-blueprint-notes'),
            __('Organize', 'core-blueprint-notes'),
            'cb_notes_edit', 'cb-notes-organize',
            [self::class, 'render_admin']);

        if (self::$admin_hook !== '') {
            \CoreBlueprint\Core\UI\AdminTheme::register_screen(self::$admin_hook, [
                'components' => ['buttons', 'cards', 'fields', 'form-controls', 'nav-tabs', 'notices', 'panels'],
            ]);
        }
    }

    public static function render_admin(): void {
        if (!self::can_organize()) {
            wp_die(esc_html__('Access denied.', 'core-blueprint-notes'));
        }
        echo '<div class="wrap cb-core-wrap cb-core-page cb-core-form-scope cb-notes-organize-page">';
        echo '<h1 class="cb-core-title">' . esc_html__('Organize Notes', 'core-blueprint-notes') . '</h1>';
        echo '<p class="cb-core-intro">' . esc_html__('Manage your personal notebooks, categories and tags. These labels stay private and are not included in shared notes.', 'core-blueprint-notes') . '</p>';
        echo '<div id="cb-notes-organization-root"></div></div>';
    }

    public static function enqueue_admin(string $hook): void {
        if ($hook !== self::$admin_hook || !self::can_organize()) {
            return;
        }
        \CoreBlueprint\Core\UI\Assets::enqueue_modals(\CoreBlueprint\Core\UI\Assets::MODAL_PRESENTATION_WP_NATIVE);
        // Dedicated presentation revision invalidates cached alpha4 editor UI
        // without changing the plugin release version or unrelated assets.
        $presentation_revision = CB_NOTES_VERSION . '-organize-ui4';
        wp_enqueue_style('cb-notes-organize-layout',
            plugins_url('assets/organization-admin.css', CB_NOTES_FILE),
            [], $presentation_revision);
        wp_enqueue_script('cb-notes-organize',
            plugins_url('assets/organization-admin.js', CB_NOTES_FILE),
            ['wp-element', 'wp-components', 'wp-api-fetch'],
            $presentation_revision, true);
    }
}

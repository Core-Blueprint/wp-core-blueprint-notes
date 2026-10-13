<?php
declare(strict_types=1);

namespace CB\Notes\Library;

use CB\Notes\Access;
use CB\Notes\Organization\Catalog;
use CB\Notes\Plugin;
use CoreBlueprint\Core\UI\AdminTheme;
use CoreBlueprint\Core\UI\Assets;

defined('ABSPATH') || exit;

/** A personal, server-rendered Library; Gutenberg remains the only editor. */
final class Page {
    private static string $hook = '';

    public static function init(): void {
        add_action('admin_menu', [self::class, 'register_menu'], 11);
        add_action('admin_menu', [Menu::class, 'reorder'], 999);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function register_menu(): void {
        self::$hook = (string)add_submenu_page(
            'edit.php?post_type=' . Plugin::TYPE,
            __('Notes Library', 'core-blueprint-notes'),
            __('Library', 'core-blueprint-notes'),
            'cb_notes_edit',
            'cb-notes-library',
            [self::class, 'render']
        );
        if (self::$hook !== '') {
            AdminTheme::register_screen(self::$hook, [
                'components' => ['buttons', 'panels', 'form-controls', 'fields', 'empty-state', 'badges'],
            ]);
        }
    }

    public static function enqueue(string $hook): void {
        if ($hook !== self::$hook || !current_user_can('cb_notes_edit')) {
            return;
        }
        Assets::enqueue_modals(Assets::MODAL_PRESENTATION_WP_NATIVE);
        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'cb-notes-library',
            plugins_url('assets/library-admin.css', CB_NOTES_FILE),
            [],
            CB_NOTES_VERSION
        );
        wp_enqueue_script(
            'cb-notes-library',
            plugins_url('assets/library-admin.js', CB_NOTES_FILE),
            [],
            CB_NOTES_VERSION,
            true
        );
    }

    private static function url(array $state, array $changes = []): string {
        $state = array_merge($state, $changes);
        $parameters = [
            'post_type' => Plugin::TYPE,
            'page' => 'cb-notes-library',
        ];
        foreach ([
            'search' => 'cbn_search',
            'view' => 'cbn_view',
            'notebook' => 'cbn_notebook',
            'category' => 'cbn_category',
            'tag' => 'cbn_tag',
            'sort' => 'cbn_sort',
            'paged' => 'cbn_paged',
        ] as $key => $parameter) {
            $value = $state[$key] ?? '';
            if ($value !== '' && $value !== null && !($key === 'paged' && (int)$value === 1)
                && !($key === 'sort' && $value === 'modified')) {
                $parameters[$parameter] = (string)$value;
            }
        }
        return add_query_arg($parameters, admin_url('edit.php'));
    }

    private static function nav_link(string $label, array $state, string $notebook): void {
        $active = $state['view'] === '' && $state['notebook'] === $notebook;
        $href = self::url($state, ['view' => '', 'notebook' => $notebook, 'paged' => 1]);
        echo '<a class="cb-notes-library__nav-link' . ($active ? ' is-active' : '') . '" href="' . esc_url($href) . '"'
            . ($active ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
    }

    private static function view_link(string $label, array $state, string $view): void {
        $active = $state['view'] === $view;
        $href = self::url($state, ['view' => $view, 'notebook' => '', 'paged' => 1]);
        echo '<a class="cb-notes-library__nav-link' . ($active ? ' is-active' : '') . '" href="' . esc_url($href) . '"'
            . ($active ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
    }

    private static function select_options(array $entries, string $selected, string $empty): void {
        echo '<option value="">' . esc_html($empty) . '</option>';
        foreach ($entries as $entry) {
            $id = (string)($entry['id'] ?? '');
            $name = (string)($entry['name'] ?? '');
            if (!Catalog::valid_id($id) || $name === '') {
                continue;
            }
            echo '<option value="' . esc_attr($id) . '" ' . selected($selected, $id, false) . '>'
                . esc_html($name) . '</option>';
        }
    }

    private static function render_filters(array $state, array $catalog): void {
        $expanded = $state['category'] !== '' || $state['tag'] !== '' || $state['sort'] !== 'modified';
        echo '<form class="cb-notes-library__search" method="get" action="' . esc_url(admin_url('edit.php')) . '">';
        echo '<input type="hidden" name="post_type" value="' . esc_attr(Plugin::TYPE) . '">';
        echo '<input type="hidden" name="page" value="cb-notes-library">';
        if ($state['notebook'] !== '') {
            echo '<input type="hidden" name="cbn_notebook" value="' . esc_attr($state['notebook']) . '">';
        }
        if ($state['view'] !== '') {
            echo '<input type="hidden" name="cbn_view" value="' . esc_attr($state['view']) . '">';
        }
        echo '<div class="cb-notes-library__search-row">';
        echo '<div class="cb-notes-library__search-field"><label for="cbn-search">'
            . esc_html__('Search notes', 'core-blueprint-notes') . '</label>';
        echo '<input type="search" id="cbn-search" name="cbn_search" value="' . esc_attr($state['search']) . '"'
            . ' placeholder="' . esc_attr__('Search your notes', 'core-blueprint-notes') . '"></div>';
        echo '<button class="button button-secondary" type="submit">' . esc_html__('Search', 'core-blueprint-notes') . '</button>';
        echo '</div>';
        echo '<details class="cb-notes-library__advanced"' . ($expanded ? ' open' : '') . '><summary>'
            . esc_html__('More filters', 'core-blueprint-notes') . '</summary>';
        echo '<div class="cb-notes-library__filter-grid">';
        echo '<div><label for="cbn-category">' . esc_html__('Category', 'core-blueprint-notes') . '</label>';
        echo '<select name="cbn_category" id="cbn-category">';
        self::select_options($catalog['category'], $state['category'], __('All categories', 'core-blueprint-notes'));
        echo '</select></div>';
        echo '<div><label for="cbn-tag">' . esc_html__('Tag', 'core-blueprint-notes') . '</label>';
        echo '<select name="cbn_tag" id="cbn-tag">';
        self::select_options($catalog['tag'], $state['tag'], __('All tags', 'core-blueprint-notes'));
        echo '</select></div>';
        echo '<div><label for="cbn-sort">' . esc_html__('Sort by', 'core-blueprint-notes') . '</label>';
        echo '<select name="cbn_sort" id="cbn-sort">';
        foreach ([
            'modified' => __('Recently modified', 'core-blueprint-notes'),
            'oldest' => __('Least recently modified', 'core-blueprint-notes'),
            'title' => __('Title A to Z', 'core-blueprint-notes'),
        ] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($state['sort'], $value, false) . '>'
                . esc_html($label) . '</option>';
        }
        echo '</select></div></div>';
        echo '<button class="button button-secondary" type="submit">' . esc_html__('Apply filters', 'core-blueprint-notes') . '</button>';
        echo '</details></form>';
    }

    private static function render_note(\WP_Post $post, int $owner): void {
        if ((int)$post->post_author !== $owner || !Access::can_read($post)) {
            return;
        }
        $link = get_edit_post_link($post->ID);
        if (!is_string($link) || $link === '') {
            return;
        }
        $labels = Catalog::labels_for_post((int)$post->ID);
        $title = get_the_title($post);
        if (trim($title) === '') {
            $title = __('Untitled note', 'core-blueprint-notes');
        }
        $modified = mysql2date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $post->post_modified,
            true
        );
        echo '<li class="cb-notes-library__note">';
        echo '<div class="cb-notes-library__note-content"><a class="cb-notes-library__note-title" href="'
            . esc_url($link) . '">' . esc_html($title) . '</a>';
        echo '<div class="cb-notes-library__meta">';
        echo '<span>' . esc_html($modified) . '</span>';
        if ($post->post_status === 'draft') {
            echo '<span class="cb-notes-library__status">' . esc_html__('Draft', 'core-blueprint-notes') . '</span>';
        } else {
            echo '<span class="cb-notes-library__status">' . esc_html__('Private', 'core-blueprint-notes') . '</span>';
        }
        foreach (['notebook', 'category'] as $field) {
            if ($labels[$field] !== '') {
                echo '<span class="cb-notes-library__label">' . esc_html($labels[$field]) . '</span>';
            }
        }
        foreach (array_slice($labels['tags'], 0, 3) as $tag) {
            echo '<span class="cb-notes-library__tag">' . esc_html($tag) . '</span>';
        }
        echo '</div></div><div class="cb-notes-library__actions">';
        $markers = Markers::for_post((int)$post->ID);
        foreach ([
            'favorite' => [
                'set' => __('Remove from favorites', 'core-blueprint-notes'),
                'unset' => __('Add to favorites', 'core-blueprint-notes'),
                'icon' => 'dashicons-star-filled',
            ],
            'pinned' => [
                'set' => __('Unpin note', 'core-blueprint-notes'),
                'unset' => __('Pin note', 'core-blueprint-notes'),
                'icon' => 'dashicons-admin-post',
            ],
        ] as $kind => $config) {
            $marked = $markers[$kind];
            $label = $marked ? $config['set'] : $config['unset'];
            $variant = $marked ? 'primary' : 'secondary';
            echo '<button type="button" hidden class="button cb-core-button cb-core-button--compact cb-core-button--icon-only cb-core-button--'
                . esc_attr($variant) . ' cb-notes-library__marker"'
                . ' data-cb-notes-marker="' . esc_attr($kind) . '"'
                . ' data-cb-notes-id="' . esc_attr((string)$post->ID) . '"'
                . ' data-cb-notes-nonce="' . esc_attr(wp_create_nonce('cb_notes_mark_' . $post->ID)) . '"'
                . ' aria-pressed="' . ($marked ? 'true' : 'false') . '"'
                . ' aria-label="' . esc_attr($label) . '" title="' . esc_attr($label) . '">'
                . '<span class="dashicons ' . esc_attr($config['icon']) . '" aria-hidden="true"></span></button>';
        }
        echo '<a class="button cb-core-button cb-core-button--secondary cb-core-button--compact" href="' . esc_url($link) . '">'
            . esc_html__('Edit', 'core-blueprint-notes') . '</a>';
        $trash = get_delete_post_link($post->ID, '', false);
        if (is_string($trash) && $trash !== '' && current_user_can('delete_post', $post->ID)) {
            // Fail closed: the action is hidden until the Base confirmation
            // handler is available. WordPress core still owns the trash URL.
            echo '<button type="button" class="button cb-core-button cb-core-button--danger cb-core-button--compact cb-notes-library__trash" hidden'
                . ' data-cb-notes-trash-url="' . esc_url($trash) . '">'
                . esc_html__('Move to Trash', 'core-blueprint-notes') . '</button>';
        }
        echo '</div></li>';
    }

    /** Contextual guidance without revealing catalog labels for invalid filters. */
    private static function render_empty_state(array $state, int $total, string $create_url): void {
        $has_secondary_filters = $state['search'] !== '' || $state['category'] !== '' || $state['tag'] !== '';
        $has_notebook = $state['notebook'] !== '';
        $reset = self::url($state, [
            'search' => '', 'view' => '', 'notebook' => '', 'category' => '', 'tag' => '',
            'sort' => 'modified', 'paged' => 1,
        ]);

        if ($total > 0 && $state['paged'] > 1) {
            $heading = __('No notes on this page', 'core-blueprint-notes');
            $hint = __('Try the first page of these results.', 'core-blueprint-notes');
            $action = __('First page', 'core-blueprint-notes');
            $href = self::url($state, ['paged' => 1]);
        } elseif ($state['invalid'] || $has_secondary_filters) {
            $heading = __('No matching notes', 'core-blueprint-notes');
            $hint = __('Try a different search or clear your filters.', 'core-blueprint-notes');
            $action = __('Clear filters', 'core-blueprint-notes');
            $href = $reset;
        } elseif ($state['view'] === 'favorites') {
            $heading = __('No favorites yet', 'core-blueprint-notes');
            $hint = __('Use the star next to a note to save it here.', 'core-blueprint-notes');
            $action = __('Show all notes', 'core-blueprint-notes');
            $href = $reset;
        } elseif ($state['view'] === 'pinned') {
            $heading = __('No pinned notes yet', 'core-blueprint-notes');
            $hint = __('Pin important notes to keep them at the top of your Library.', 'core-blueprint-notes');
            $action = __('Show all notes', 'core-blueprint-notes');
            $href = $reset;
        } elseif ($state['notebook'] === 'unfiled') {
            $heading = __('No unfiled notes', 'core-blueprint-notes');
            $hint = __('Your notes are already assigned to notebooks.', 'core-blueprint-notes');
            $action = __('Show all notes', 'core-blueprint-notes');
            $href = $reset;
        } elseif ($has_notebook) {
            $heading = __('This notebook is empty', 'core-blueprint-notes');
            $hint = __('Create a note, then select this notebook in the editor.', 'core-blueprint-notes');
            $action = __('New note', 'core-blueprint-notes');
            $href = $create_url;
        } else {
            $heading = __('Your Library is empty', 'core-blueprint-notes');
            $hint = __('Create your first private note to get started.', 'core-blueprint-notes');
            $action = __('New note', 'core-blueprint-notes');
            $href = $create_url;
        }

        echo '<div class="cb-notes-library__empty">';
        echo '<h3 class="cb-notes-library__empty-title">' . esc_html($heading) . '</h3>';
        echo '<p>' . esc_html($hint) . '</p>';
        echo '<a class="button button-secondary" href="' . esc_url($href) . '">'
            . esc_html($action) . '</a></div>';
    }

    private static function render_pagination(array $state, int $total): void {
        if ($total <= 1) {
            return;
        }
        $current = (int)$state['paged'];
        echo '<nav class="cb-notes-library__pagination" aria-label="'
            . esc_attr__('Notes pages', 'core-blueprint-notes') . '">';
        if ($current > 1) {
            echo '<a class="button button-secondary" href="' . esc_url(self::url($state, ['paged' => $current - 1])) . '">'
                . esc_html__('Previous', 'core-blueprint-notes') . '</a>';
        }
        echo '<span>' . esc_html(sprintf(
            /* translators: 1: page number, 2: total pages */
            __('Page %1$d of %2$d', 'core-blueprint-notes'),
            $current, $total
        )) . '</span>';
        if ($current < $total) {
            echo '<a class="button button-secondary" href="' . esc_url(self::url($state, ['paged' => $current + 1])) . '">'
                . esc_html__('Next', 'core-blueprint-notes') . '</a>';
        }
        echo '</nav>';
    }

    public static function render(): void {
        if (!current_user_can('cb_notes_edit') || get_current_user_id() < 1) {
            wp_die(esc_html__('Access denied.', 'core-blueprint-notes'));
        }
        $owner = get_current_user_id();
        $catalog = Catalog::catalog($owner);
        $state = Query::state($_GET, $catalog);
        $query = new \WP_Query(Query::args($owner, $state));
        $create_url = admin_url('post-new.php?post_type=' . Plugin::TYPE);
        $organize_url = admin_url('edit.php?post_type=' . Plugin::TYPE . '&page=cb-notes-organize');

        echo '<div class="wrap cb-core-wrap cb-core-page cb-core-form-scope cb-notes-library"'
            . ' data-cb-notes-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '"'
            . ' data-cb-notes-view="' . esc_attr($state['view']) . '">';
        echo '<header class="cb-notes-library__heading"><div><h1 class="cb-core-title">'
            . esc_html__('Notes Library', 'core-blueprint-notes') . '</h1>';
        echo '<p class="cb-core-intro">' . esc_html__('Your private space to find, organize and write notes.', 'core-blueprint-notes')
            . '</p></div><a class="button button-primary" href="' . esc_url($create_url) . '">'
            . esc_html__('New note', 'core-blueprint-notes') . '</a></header>';
        echo '<div class="cb-notes-library__layout"><aside class="cb-notes-library__sidebar cb-core-panel">';
        echo '<nav class="cb-notes-library__nav" aria-label="' . esc_attr__('Notes Library navigation', 'core-blueprint-notes') . '">';
        echo '<h2>' . esc_html__('Quick access', 'core-blueprint-notes') . '</h2>';
        self::nav_link(__('All Notes', 'core-blueprint-notes'), $state, '');
        self::view_link(__('Favorites', 'core-blueprint-notes'), $state, 'favorites');
        self::view_link(__('Pinned', 'core-blueprint-notes'), $state, 'pinned');
        echo '<h2>' . esc_html__('Notebooks', 'core-blueprint-notes') . '</h2>';
        self::nav_link(__('Unfiled', 'core-blueprint-notes'), $state, 'unfiled');
        foreach ($catalog['notebook'] as $notebook) {
            self::nav_link((string)$notebook['name'], $state, (string)$notebook['id']);
        }
        echo '</nav><a class="cb-notes-library__organize" href="' . esc_url($organize_url) . '">'
            . esc_html__('Manage notebooks and labels', 'core-blueprint-notes') . '</a></aside>';
        echo '<main class="cb-notes-library__main cb-core-panel" id="cb-notes-library-results">';
        echo '<p class="cb-notes-library__feedback" role="alert" aria-live="assertive" hidden></p>';
        self::render_filters($state, $catalog);
        $heading = match ($state['view']) {
            'favorites' => __('Your favorites', 'core-blueprint-notes'),
            'pinned' => __('Pinned notes', 'core-blueprint-notes'),
            default => __('Your notes', 'core-blueprint-notes'),
        };
        echo '<div class="cb-notes-library__results-heading"><h2>' . esc_html($heading) . '</h2>';
        echo '<span>' . esc_html(sprintf(
            /* translators: %d: number of matching notes */
            _n('%d note', '%d notes', (int)$query->found_posts, 'core-blueprint-notes'),
            (int)$query->found_posts
        )) . '</span></div>';
        if ($query->have_posts()) {
            echo '<ul class="cb-notes-library__notes">';
            foreach ($query->posts as $post) {
                if ($post instanceof \WP_Post) {
                    self::render_note($post, $owner);
                }
            }
            echo '</ul>';
        } else {
            self::render_empty_state($state, (int)$query->found_posts, $create_url);
        }
        self::render_pagination($state, (int)$query->max_num_pages);
        echo '</main></div></div>';
        wp_reset_postdata();
    }
}

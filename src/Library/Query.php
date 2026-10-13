<?php
declare(strict_types=1);

namespace CB\Notes\Library;

use CB\Notes\Organization\Catalog;
use CB\Notes\Plugin;

defined('ABSPATH') || exit;

/**
 * Owner-scoped Library query contract. All filters are derived from the
 * current user's personal catalog, never from site-wide taxonomy terms.
 */
final class Query {
    public const PER_PAGE = 20;

    private static function text(array $input, string $key, int $limit = 120): string {
        $raw = $input[$key] ?? '';
        if (!is_string($raw)) {
            return '';
        }
        $value = trim(sanitize_text_field(wp_unslash($raw)));
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    private static function catalog_has(array $catalog, string $kind, string $id): bool {
        if (!Catalog::valid_id($id)) {
            return false;
        }
        foreach ($catalog[$kind] ?? [] as $entry) {
            if (($entry['id'] ?? '') === $id) {
                return true;
            }
        }
        return false;
    }

    /** Normalize shareable URL state; unknown IDs fail closed. */
    public static function state(array $input, array $catalog): array {
        $notebook = self::text($input, 'cbn_notebook', 80);
        $category = self::text($input, 'cbn_category', 80);
        $tag = self::text($input, 'cbn_tag', 80);
        $view = self::text($input, 'cbn_view', 16);
        $view = in_array($view, ['favorites', 'pinned'], true) ? $view : '';
        $sort = self::text($input, 'cbn_sort', 16);
        $sort = in_array($sort, ['modified', 'oldest', 'title'], true) ? $sort : 'modified';
        $page = $input['cbn_paged'] ?? 1;
        $page = is_scalar($page) ? max(1, min(9999, (int)$page)) : 1;
        $invalid = ($notebook !== '' && $notebook !== 'unfiled' && !self::catalog_has($catalog, 'notebook', $notebook))
            || ($category !== '' && !self::catalog_has($catalog, 'category', $category))
            || ($tag !== '' && !self::catalog_has($catalog, 'tag', $tag));
        return [
            'search' => self::text($input, 'cbn_search'),
            'view' => $view,
            'notebook' => $notebook,
            'category' => $category,
            'tag' => $tag,
            'sort' => $sort,
            'paged' => $page,
            'invalid' => $invalid,
        ];
    }

    /** WP_Query args are always scoped to one owner, including for admins. */
    public static function args(int $owner, array $state): array {
        $args = [
            'post_type' => Plugin::TYPE,
            'post_status' => ['private', 'draft', 'pending', 'future', 'publish'],
            'author' => $owner > 0 ? $owner : -1,
            'posts_per_page' => self::PER_PAGE,
            'paged' => (int)$state['paged'],
            'no_found_rows' => false,
            'ignore_sticky_posts' => true,
            'cb_notes_pinned_first' => $state['sort'] === 'modified',
            's' => (string)$state['search'],
            'orderby' => match ($state['sort']) {
                'oldest' => ['modified' => 'ASC', 'ID' => 'ASC'],
                'title' => ['title' => 'ASC', 'ID' => 'DESC'],
                default => ['modified' => 'DESC', 'ID' => 'DESC'],
            },
        ];
        if ($owner < 1 || $state['invalid']) {
            $args['post__in'] = [0];
            return $args;
        }

        $clauses = [];
        if ($state['view'] === 'favorites') {
            $clauses[] = ['key' => Markers::FAVORITE, 'value' => '1', 'compare' => '='];
        } elseif ($state['view'] === 'pinned') {
            $clauses[] = ['key' => Markers::PINNED, 'value' => '1', 'compare' => '='];
        }
        if ($state['notebook'] === 'unfiled') {
            $clauses[] = [
                'relation' => 'OR',
                ['key' => Catalog::NOTEBOOK, 'compare' => 'NOT EXISTS'],
                ['key' => Catalog::NOTEBOOK, 'value' => '', 'compare' => '='],
            ];
        } elseif ($state['notebook'] !== '') {
            $clauses[] = ['key' => Catalog::NOTEBOOK, 'value' => $state['notebook'], 'compare' => '='];
        }
        if ($state['category'] !== '') {
            $clauses[] = ['key' => Catalog::CATEGORY, 'value' => $state['category'], 'compare' => '='];
        }
        if ($state['tag'] !== '') {
            // WordPress stores post-meta arrays serialized. Match one exact
            // 32-character ID including its serialization boundary.
            $clauses[] = [
                'key' => Catalog::TAGS,
                'value' => 's:32:"' . $state['tag'] . '";',
                'compare' => 'LIKE',
            ];
        }
        if ($clauses !== []) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $clauses);
        }
        return $args;
    }
}

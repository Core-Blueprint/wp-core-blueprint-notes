<?php
declare(strict_types=1);

namespace CB\Notes\Sharing;

defined('ABSPATH') || exit;

/** Frozen, restricted Gutenberg projection: no dynamic blocks, media or embeds. */
final class Snapshot {
    private const ALLOWED = [
        'core/paragraph', 'core/heading', 'core/list', 'core/list-item',
        'core/quote', 'core/code', 'core/preformatted', 'core/table',
        'core/separator', 'core/pullquote', 'core/verse',
    ];

    public static function capture(\WP_Post $post): string|\WP_Error {
        if ('cb_note' !== $post->post_type || in_array($post->post_status, ['trash', 'auto-draft'], true)) {
            return new \WP_Error('cb_notes_share_unavailable', __('This note cannot be shared.', 'core-blueprint-notes'), ['status' => 409]);
        }
        if (strlen($post->post_content) > 100000) {
            return new \WP_Error('cb_notes_share_size', __('This note is too large for sharing.', 'core-blueprint-notes'), ['status' => 422]);
        }
        $blocks = parse_blocks($post->post_content);
        if (!self::blocks_are_static($blocks)) {
            return new \WP_Error(
                'cb_notes_share_unsupported_blocks',
                __('Sharing currently supports static text, lists, quotations, code and tables only. Remove embeds, media and dynamic blocks before sharing.', 'core-blueprint-notes'),
                ['status' => 422]
            );
        }
        $allowed = [
            'p' => ['class' => true], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
            'u' => [], 's' => [], 'mark' => [], 'small' => [], 'code' => [], 'pre' => [],
            'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
            'ul' => ['class' => true, 'start' => true], 'ol' => ['class' => true, 'start' => true],
            'li' => [], 'blockquote' => [], 'cite' => [], 'table' => [], 'thead' => [],
            'tbody' => [], 'tfoot' => [], 'tr' => [], 'td' => ['colspan' => true, 'rowspan' => true],
            'th' => ['scope' => true, 'colspan' => true, 'rowspan' => true],
            'hr' => [], 'span' => [], 'div' => ['class' => true],
            'a' => ['href' => true, 'title' => true, 'rel' => true],
        ];
        $html = '';
        foreach ($blocks as $block) {
            $html .= wp_kses(render_block($block), $allowed, ['https', 'http', 'mailto']);
        }
        if (strlen($html) > 120000) {
            return new \WP_Error('cb_notes_share_size', __('The rendered note is too large for sharing.', 'core-blueprint-notes'), ['status' => 422]);
        }
        return $html;
    }

    /** @param array<int,array<string,mixed>> $blocks */
    private static function blocks_are_static(array $blocks): bool {
        foreach ($blocks as $block) {
            $name = $block['blockName'] ?? null;
            if (null === $name) {
                if (trim((string)($block['innerHTML'] ?? '')) !== '') {
                    return false;
                }
                continue;
            }
            if (!in_array($name, self::ALLOWED, true)) {
                return false;
            }
            $type = \WP_Block_Type_Registry::get_instance()->get_registered($name);
            if (null === $type || $type->is_dynamic()) {
                return false;
            }
            if (!self::blocks_are_static($block['innerBlocks'] ?? [])) {
                return false;
            }
        }
        return true;
    }
}

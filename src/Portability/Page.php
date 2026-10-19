<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CB\Notes\Plugin;
use CoreBlueprint\Core\UI\AdminTheme;

/**
 * Notes owner-facing portability entrypoint. Export is read-only. JSON upload
 * only previews; no import apply action is registered in the current batch.
 */
final class Page {
    private const SLUG = 'cb-notes-portability';
    private const ACTION = 'cb_notes_portability_export';
    private const PREVIEW_NONCE = 'cb_notes_portability_preview';
    private static string $hook = '';

    public static function init(): void {
        add_action('admin_menu', [self::class, 'register_menu'], 15);
        add_action('admin_menu', [self::class, 'hide_menu_row'], 50);
        add_action('admin_post_' . self::ACTION, [self::class, 'download']);
    }

    public static function url(): string {
        return admin_url('edit.php?post_type=' . Plugin::TYPE . '&page=' . self::SLUG);
    }

    public static function register_menu(): void {
        // Register a normal Notes parent/child admin screen for stable
        // WordPress load-hooks, then hide only its menu row. No duplicate
        // permanent Notes submenu or untyped null parent is necessary.
        self::$hook = (string)add_submenu_page(
            'edit.php?post_type=' . Plugin::TYPE,
            __('Data portability', 'core-blueprint-notes'),
            __('Data portability', 'core-blueprint-notes'),
            'cb_notes_edit',
            self::SLUG,
            [self::class, 'render']
        );
        if (self::$hook !== '') {
            add_action('load-' . self::$hook, [self::class, 'private_screen_headers']);
            AdminTheme::register_screen(self::$hook, [
                'components' => ['buttons', 'panels', 'form-controls', 'fields', 'notices'],
            ]);
        }
    }

    public static function private_screen_headers(): void {
        // Run before the WordPress admin header renders. A private preview
        // must not be cached by a reverse proxy or shared browser cache.
        if (self::owner()) {
            nocache_headers();
            header('Cache-Control: private, no-store, no-cache, max-age=0, must-revalidate', true);
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
            header('X-Content-Type-Options: nosniff', true);
        }
    }

    public static function hide_menu_row(): void {
        remove_submenu_page('edit.php?post_type=' . Plugin::TYPE, self::SLUG);
    }

    private static function owner(): bool {
        return get_current_user_id() > 0 && current_user_can('cb_notes_edit');
    }

    public static function download(): void {
        if (!self::owner()) {
            wp_die(esc_html__('Access denied.', 'core-blueprint-notes'), '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION);
        $format = isset($_POST['format']) && is_string($_POST['format'])
            ? sanitize_key(wp_unslash($_POST['format'])) : '';
        if (!in_array($format, ['json', 'markdown'], true)) {
            wp_die(esc_html__('Invalid note action.', 'core-blueprint-notes'), '', ['response' => 400]);
        }
        $bytes = $format === 'json' ? OwnerExport::json() : OwnerExport::markdown_zip();
        if ($bytes instanceof \WP_Error || !is_string($bytes) || strlen($bytes) < 1
            || strlen($bytes) > 10 * 1024 * 1024) {
            wp_die(esc_html__('Notes export is unavailable or exceeds the allowed size.', 'core-blueprint-notes'),
                '', ['response' => 422]);
        }
        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, max-age=0, must-revalidate', true);
        header('Pragma: no-cache', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        header('X-Content-Type-Options: nosniff', true);
        header('Content-Security-Policy: sandbox', true);
        header('Content-Type: ' . ($format === 'json'
            ? 'application/json; charset=UTF-8' : 'application/zip'), true);
        header('Content-Disposition: attachment; filename="core-blueprint-notes.'
            . ($format === 'json' ? 'json' : 'zip') . '"', true);
        header('Content-Length: ' . strlen($bytes), true);
        echo $bytes; // Owner-only, CSRF-protected, private downloadable content.
        exit;
    }

    /** @return array|\WP_Error */
    private static function preview_upload(): array|\WP_Error {
        check_admin_referer(self::PREVIEW_NONCE);
        $upload = $_FILES['notes_json'] ?? null;
        if (!is_array($upload) || !isset($upload['error'], $upload['size'], $upload['tmp_name'])
            || $upload['error'] !== UPLOAD_ERR_OK || !is_int($upload['size'])
            || $upload['size'] < 1 || $upload['size'] > 10 * 1024 * 1024
            || !is_string($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            return new \WP_Error('cb_notes_import_upload_invalid', 'The Notes import file is invalid.');
        }
        $json = file_get_contents($upload['tmp_name'], false, null, 0, 10 * 1024 * 1024 + 1);
        if (!is_string($json) || strlen($json) !== $upload['size']) {
            return new \WP_Error('cb_notes_import_upload_invalid', 'The Notes import file could not be read.');
        }
        // Strict owner-scoped complete file preflight; no import mutation and
        // no persistent copy of the untrusted archive.
        return ImportSession::with_validated_json($json, static function (array $context) use ($json): array|\WP_Error {
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            $catalog = ImportSession::record($decoded['records'][0], $context);
            return $catalog instanceof \WP_Error ? $catalog : $catalog['preview'];
        });
    }

    public static function render(): void {
        if (!self::owner()) {
            wp_die(esc_html__('Access denied.', 'core-blueprint-notes'), '', ['response' => 403]);
        }
        $preview = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = isset($_POST['cb_notes_portability_intent'])
                && is_string($_POST['cb_notes_portability_intent'])
                    ? sanitize_key(wp_unslash($_POST['cb_notes_portability_intent'])) : '';
            if ($action !== 'preview') {
                wp_die(esc_html__('Invalid note action.', 'core-blueprint-notes'), '', ['response' => 400]);
            }
            $preview = self::preview_upload();
            nocache_headers();
        }
        echo '<div class="wrap cb-core-wrap cb-core-page cb-core-form-scope">';
        echo '<h1 class="cb-core-title">' . esc_html__('Data portability', 'core-blueprint-notes') . '</h1>';
        echo '<p class="cb-core-intro">'
            . esc_html__('Export your own private notes or review a Notes JSON archive before import.', 'core-blueprint-notes')
            . '</p>';
        echo '<p><a href="' . esc_url(admin_url('edit.php?post_type=' . Plugin::TYPE
            . '&page=cb-notes-library')) . '">' . esc_html__('Notes Library', 'core-blueprint-notes') . '</a></p>';
        echo '<section class="cb-core-panel"><h2>' . esc_html__('Export', 'core-blueprint-notes') . '</h2>';
        echo '<p>' . esc_html__('JSON retains supported Gutenberg content and note relationships. Markdown ZIP includes readable notes and original block-source files; it does not include sharing grants, media or revisions.', 'core-blueprint-notes') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
        wp_nonce_field(self::ACTION);
        echo '<button type="submit" class="button cb-core-button cb-core-button--secondary" name="format" value="json">'
            . esc_html__('Export JSON', 'core-blueprint-notes') . '</button> ';
        echo '<button type="submit" class="button cb-core-button cb-core-button--secondary" name="format" value="markdown">'
            . esc_html__('Export Markdown ZIP', 'core-blueprint-notes') . '</button></form></section>';
        echo '<section class="cb-core-panel"><h2>' . esc_html__('Import preview', 'core-blueprint-notes') . '</h2>';
        echo '<p>' . esc_html__('Importing is not yet enabled. You can check the complete archive and see its destination plan without changing notes.', 'core-blueprint-notes') . '</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(self::url()) . '">';
        wp_nonce_field(self::PREVIEW_NONCE);
        echo '<input type="hidden" name="cb_notes_portability_intent" value="preview">';
        echo '<label for="cb-notes-import-file">' . esc_html__('Select Notes JSON file', 'core-blueprint-notes') . '</label> ';
        echo '<input id="cb-notes-import-file" type="file" name="notes_json" accept=".json,application/json" required> ';
        echo '<button type="submit" class="button cb-core-button cb-core-button--primary">'
            . esc_html__('Preview import', 'core-blueprint-notes') . '</button></form>';
        if ($preview instanceof \WP_Error) {
            echo '<div class="notice notice-error" role="alert"><p>'
                . esc_html__('The archive could not be validated. No notes were changed.', 'core-blueprint-notes')
                . '</p></div>';
        } elseif (is_array($preview)) {
            echo '<div class="notice notice-info" role="status"><p>'
                . esc_html(sprintf(
                    /* translators: 1: number of new notes, 2: number of notes to skip */
                    __('Preview: %1$d new notes, %2$d already imported. No changes made.', 'core-blueprint-notes'),
                    (int)$preview['create_count'],
                    (int)$preview['skip_count']
                )) . '</p></div>';
        }
        echo '</section></div>';
    }
}

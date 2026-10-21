<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CB\Notes\Plugin;
use CoreBlueprint\Core\UI\AdminTheme;

/**
 * Owner-only JSON/Markdown export and explicit two-request Base-backed import.
 * Product candidate only; browser runtime/security acceptance is still HOLD.
 */
final class Page {
    private const SLUG = 'cb-notes-portability';
    private const ACTION = 'cb_notes_portability_export';
    private const PREVIEW_NONCE = 'cb_notes_portability_preview';
    private const CONFIRM_ACTION = 'cb_notes_portability_confirm';
    private const CONFIRM_NONCE = 'cb_notes_portability_confirm_nonce';
    private const RESULT_PREFIX = 'cb_notes_import_status_v1_';
    private static string $hook = '';

    public static function init(): void {
        add_action('admin_menu', [self::class, 'register_menu'], 15);
        add_action('admin_menu', [self::class, 'hide_menu_row'], 50);
        add_action('admin_post_' . self::ACTION, [self::class, 'download']);
        add_action('admin_post_' . self::CONFIRM_ACTION, [self::class, 'confirm_import']);
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

    /** @return string|\WP_Error */
    private static function uploaded_json(): string|\WP_Error {
        $upload = $_FILES['notes_json'] ?? null;
        if (!is_array($upload) || !isset($upload['error'], $upload['size'], $upload['tmp_name'])
            || $upload['error'] !== UPLOAD_ERR_OK || !is_int($upload['size'])
            || $upload['size'] < 1 || $upload['size'] > 10 * 1024 * 1024
            || !is_string($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            return new \WP_Error('cb_notes_import_upload_invalid', 'The Notes import file is invalid.');
        }
        $bytes = file_get_contents($upload['tmp_name'], false, null, 0, 10 * 1024 * 1024 + 1);
        if (!is_string($bytes) || strlen($bytes) !== $upload['size']) {
            return new \WP_Error('cb_notes_import_upload_invalid', 'The Notes import file could not be read.');
        }
        return $bytes;
    }

    /** @return array|\WP_Error */
    private static function preview_upload(): array|\WP_Error {
        check_admin_referer(self::PREVIEW_NONCE);
        $bytes = self::uploaded_json();
        return $bytes instanceof \WP_Error ? $bytes : ImportFlow::preview_json($bytes);
    }

    /** A separate admin-post action ensures refresh can never repeat apply. */
    public static function confirm_import(): void {
        if (!self::owner()) {
            wp_die(esc_html__('Access denied.', 'core-blueprint-notes'), '', ['response' => 403]);
        }
        check_admin_referer(self::CONFIRM_NONCE);
        $ack = isset($_POST['cb_notes_confirm_create_only']) && is_string($_POST['cb_notes_confirm_create_only'])
            ? wp_unslash($_POST['cb_notes_confirm_create_only']) : '';
        $fingerprint = isset($_POST['cb_notes_base_fingerprint']) && is_string($_POST['cb_notes_base_fingerprint'])
            ? wp_unslash($_POST['cb_notes_base_fingerprint']) : '';
        $file_sha = isset($_POST['cb_notes_file_sha256']) && is_string($_POST['cb_notes_file_sha256'])
            ? wp_unslash($_POST['cb_notes_file_sha256']) : '';
        if ($ack !== '1' || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $file_sha) !== 1) {
            wp_die(esc_html__('The import confirmation is invalid.', 'core-blueprint-notes'),
                '', ['response' => 400]);
        }
        $bytes = self::uploaded_json();
        $result = $bytes instanceof \WP_Error
            ? $bytes : ImportFlow::apply_json($bytes, $file_sha, $fingerprint);
        $state = [
            'status' => 'failed',
            'applied' => 0,
            'skipped' => 0,
        ];
        if ($result instanceof \WP_Error) {
            $code = $result->get_error_code();
            $state['status'] = str_starts_with($code, 'cb_notes_import_journal_')
                || str_starts_with($code, 'cb_notes_import_mapping_seed_')
                || in_array($code, ['cb_notes_import_create_uncertain', 'cb_notes_import_timestamp_write_failed'], true)
                    ? 'recovery' : 'failed';
        } elseif (is_array($result)) {
            $state['status'] = !empty($result['needs_review']) ? 'recovery' : $result['status'];
            $state['applied'] = (int)($result['applied_count'] ?? 0);
            $state['skipped'] = (int)($result['skipped_count'] ?? 0);
        }
        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Throwable $throwable) {
            unset($throwable);
            wp_die(esc_html__('Import status is unavailable. Check your Notes before trying again.', 'core-blueprint-notes'),
                '', ['response' => 503]);
        }
        $key = self::RESULT_PREFIX . get_current_user_id() . '_' . $token;
        if (!set_transient($key, $state, 2 * MINUTE_IN_SECONDS)) {
            wp_die(esc_html__('Import status is unavailable. Check your Notes before trying again.', 'core-blueprint-notes'),
                '', ['response' => 503]);
        }
        nocache_headers();
        wp_safe_redirect(add_query_arg('cb_notes_import_result', $token, self::url()), 303);
        exit;
    }

    /** @return array|null */
    private static function consume_result(): ?array {
        $token = isset($_GET['cb_notes_import_result']) && is_string($_GET['cb_notes_import_result'])
            ? wp_unslash($_GET['cb_notes_import_result']) : '';
        if (preg_match('/^[a-f0-9]{32}$/D', $token) !== 1) {
            return null;
        }
        $key = self::RESULT_PREFIX . get_current_user_id() . '_' . $token;
        $value = get_transient($key);
        delete_transient($key);
        if (!is_array($value) || !in_array($value['status'] ?? null,
            ['complete', 'partial', 'failed', 'recovery'], true)) {
            return null;
        }
        return $value;
    }

    public static function render(): void {
        if (!self::owner()) {
            wp_die(esc_html__('Access denied.', 'core-blueprint-notes'), '', ['response' => 403]);
        }
        $preview = null;
        $result = self::consume_result();
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
        echo '<p>' . esc_html__('Import notes into your own Library. A changed export can create duplicates. Imports never restore sharing grants, attachments or revisions.', 'core-blueprint-notes') . '</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(self::url()) . '">';
        wp_nonce_field(self::PREVIEW_NONCE);
        echo '<input type="hidden" name="cb_notes_portability_intent" value="preview">';
        echo '<label for="cb-notes-import-file">' . esc_html__('Select Notes JSON file', 'core-blueprint-notes') . '</label> ';
        echo '<input id="cb-notes-import-file" type="file" name="notes_json" accept=".json,application/json" required> ';
        echo '<button type="submit" class="button cb-core-button cb-core-button--primary">'
            . esc_html__('Preview import', 'core-blueprint-notes') . '</button></form>';
        if ($result !== null) {
            $success = $result['status'] === 'complete';
            echo '<div class="notice ' . ($success ? 'notice-success' : 'notice-warning')
                . '" role="status"><p>';
            if ($success) {
                echo esc_html(sprintf(
                    /* translators: 1: imported Notes, 2: skipped Notes */
                    __('Import complete: %1$d applied, %2$d skipped.', 'core-blueprint-notes'),
                    (int)$result['applied'], (int)$result['skipped']
                ));
            } elseif ($result['status'] === 'recovery' || $result['status'] === 'partial') {
                echo esc_html__('Import may be incomplete. Recovery review is required before retrying.', 'core-blueprint-notes');
            } else {
                echo esc_html__('Import could not be confirmed. No success is claimed. Review the archive before retrying.', 'core-blueprint-notes');
            }
            echo '</p></div>';
        }
        if ($preview instanceof \WP_Error) {
            $code = $preview->get_error_code();
            $needs_review = str_starts_with($code, 'cb_notes_import_journal_')
                || str_starts_with($code, 'cb_notes_import_mapping_seed_');
            echo '<div class="notice notice-error" role="alert"><p>';
            if ($needs_review) {
                echo esc_html__('An earlier import or site mapping requires administrator review. Do not retry or reset import data.', 'core-blueprint-notes');
            } else {
                echo esc_html__('The archive could not be validated or has an import conflict. No notes were changed by preview.', 'core-blueprint-notes');
            }
            echo '</p></div>';
        } elseif (is_array($preview)) {
            echo '<div class="notice notice-info" role="status"><p>'
                . esc_html(sprintf(
                    /* translators: 1: number of new notes, 2: number of notes to skip */
                    __('Preview: %1$d new notes, %2$d already imported. No changes made.', 'core-blueprint-notes'),
                    (int)$preview['create_count'], (int)$preview['skip_count']
                )) . '</p></div>';
            echo '<p>' . esc_html__('Review the destination and reselect the exact same JSON file below. Changed archives may duplicate previously imported notes. Imported Notes will be draft or private and unshared.', 'core-blueprint-notes') . '</p>';
            echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="' . esc_attr(self::CONFIRM_ACTION) . '">';
            echo '<input type="hidden" name="cb_notes_base_fingerprint" value="'
                . esc_attr($preview['base_fingerprint']) . '">';
            echo '<input type="hidden" name="cb_notes_file_sha256" value="'
                . esc_attr($preview['file_sha256']) . '">';
            wp_nonce_field(self::CONFIRM_NONCE);
            echo '<label for="cb-notes-confirm-file">'
                . esc_html__('Select the same Notes JSON file again', 'core-blueprint-notes')
                . '</label> ';
            echo '<input id="cb-notes-confirm-file" type="file" name="notes_json" accept=".json,application/json" required>';
            echo '<p><label><input type="checkbox" name="cb_notes_confirm_create_only" value="1" required> '
                . esc_html__('I confirm create-only import into my own Notes Library, including possible duplicates from changed exports.', 'core-blueprint-notes')
                . '</label></p>';
            echo '<button type="submit" class="button cb-core-button cb-core-button--primary">'
                . esc_html__('Confirm import', 'core-blueprint-notes') . '</button></form>';
        }
        echo '</section></div>';
    }
}

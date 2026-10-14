<?php
declare(strict_types=1);

namespace CB\Notes\Sharing;

use CB\Notes\Admin\SettingsPage;
use CB\Notes\Integration\SecureProvider;

defined('ABSPATH') || exit;

/**
 * Persistent, bounded operator recovery for failed Secure Share revocations.
 *
 * Each grant uses its own non-autoload WordPress option so concurrent revoke
 * attempts never overwrite a shared array. The record contains only a grant
 * UUID and expected revision, never a bearer, password or note content.
 */
final class SecureRevocationRecovery {
    private const PREFIX = 'cb_notes_revoke_pending_v1_';
    private const LIMIT = 20;

    public static function register(): void {
        add_action('admin_post_cb_notes_retry_revocations', [self::class, 'retry_admin']);
    }

    private static function name(string $id): string {
        return self::PREFIX . hash('sha256', $id);
    }

    private static function valid(string $id, int $revision): bool {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $id) === 1
            && $revision > 0;
    }

    /**
     * Track the provider obligation independently of the note, including
     * after permanent note deletion. Return true only for confirmed revoke.
     */
    public static function revoke(string $id, int $revision): bool {
        if (!self::valid($id, $revision)) {
            self::warn();
            return false;
        }

        $name = self::name($id);
        $record = ['grant_id' => $id, 'revision' => $revision, 'queued_at' => gmdate('c')];
        if (!add_option($name, $record, '', false) && get_option($name, false) === false) {
            // DB write failure: still attempt immediate provider revocation.
            // If that fails, log the need for manual operator review.
            self::warn();
        }

        return self::attempt($id, $revision);
    }

    private static function attempt(string $id, int $revision): bool {
        if (!SecureProvider::available()) {
            self::warn();
            return false;
        }
        try {
            // False includes missing/stale grant revisions: never treat as proof
            // of revocation or silently clear a recovery obligation.
            $success = \CB\SecureShare\PublicApi\SecureShare::revoke($id, $revision);
            if ($success !== true) {
                self::warn();
                return false;
            }
            delete_option(self::name($id));
            return true;
        } catch (\Throwable $error) {
            self::warn();
            return false;
        }
    }

    /** Return count only, never reveal stored provider identifiers in UI. */
    public static function pending_count(): int {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->options)) {
            return 0;
        }
        $like = $wpdb->esc_like(self::PREFIX) . '%';
        return max(0, (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like
        )));
    }

    /** Retry only a bounded batch, with no automatic/background processing. */
    public static function retry_pending(): int {
        global $wpdb;
        if (!SecureProvider::available() || !isset($wpdb) || !is_object($wpdb) || !isset($wpdb->options)) {
            return 0;
        }

        $like = $wpdb->esc_like(self::PREFIX) . '%';
        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d",
            $like,
            self::LIMIT
        ));
        if (!is_array($names)) {
            return 0;
        }
        $done = 0;
        foreach ($names as $name) {
            if (!is_string($name) || !str_starts_with($name, self::PREFIX)) {
                continue;
            }
            $entry = get_option($name, false);
            if (!is_array($entry) || !is_string($entry['grant_id'] ?? null)) {
                self::warn();
                continue;
            }
            $id = $entry['grant_id'];
            $revision = (int)($entry['revision'] ?? 0);
            if (!self::valid($id, $revision) || $name !== self::name($id)) {
                self::warn();
                continue;
            }
            if (self::attempt($id, $revision)) {
                ++$done;
            }
        }
        return $done;
    }

    public static function retry_admin(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage Secure Share recovery.', 'core-blueprint-notes'));
        }
        check_admin_referer('cb_notes_retry_revocations');
        self::retry_pending();
        wp_safe_redirect(add_query_arg('cb_notes_recovery_checked', '1', SettingsPage::url()), 303);
        exit;
    }

    private static function warn(): void {
        // No grant IDs, bearer fragments, post content, or request parameters.
        error_log('[Core Blueprint Notes] Secure Share revocation requires operator review.');
    }
}

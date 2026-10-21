<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

/**
 * Private, site-persistent, versioned destination-identity seed.
 * This is provisioning, never an import-preview or import-apply operation.
 */
final class MappingSeed {
    private const OPTION = 'cb_notes_import_mapping_seed_v1';

    public static function read(): string|\WP_Error {
        $raw = get_option(self::OPTION, null);
        if (!is_string($raw) || preg_match('/^v1:[a-f0-9]{64}$/D', $raw) !== 1) {
            return self::error('cb_notes_import_mapping_seed_unavailable');
        }
        $decoded = hex2bin(substr($raw, 3));
        return is_string($decoded) && strlen($decoded) === 32
            ? $decoded : self::error('cb_notes_import_mapping_seed_invalid');
    }

    /**
     * Only invoked from trusted plugin activation or privileged admin upgrade.
     * If a pre-seed journal exists, there is no safe automatic legacy adoption.
     */
    public static function provision(): true|\WP_Error {
        $existing = get_option(self::OPTION, null);
        if ($existing !== null && $existing !== false) {
            return self::read() instanceof \WP_Error
                ? self::error('cb_notes_import_mapping_seed_invalid') : true;
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return self::error('cb_notes_import_mapping_seed_unavailable');
        }
        // Do not silently give previous import receipts a different mapping key.
        $pattern = $wpdb->esc_like('cb_notes_import_receipt_v1_') . '%';
        $query = $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1",
            $pattern
        );
        $legacy = $wpdb->get_var($query);
        if (!is_null($legacy) || $wpdb->last_error !== '') {
            return self::error('cb_notes_import_mapping_seed_review');
        }

        try {
            $raw = 'v1:' . bin2hex(random_bytes(32));
        } catch (\Throwable $failure) {
            unset($failure);
            return self::error('cb_notes_import_mapping_seed_unavailable');
        }
        if (!add_option(self::OPTION, $raw, '', false)) {
            // Another concurrent, valid one-time initializer may have won.
            return self::read() instanceof \WP_Error
                ? self::error('cb_notes_import_mapping_seed_review') : true;
        }
        return get_option(self::OPTION, null) === $raw
            ? true : self::error('cb_notes_import_mapping_seed_review');
    }

    public static function provision_for_admin_upgrade(): void {
        if (current_user_can('manage_options')) {
            self::provision();
        }
    }

    private static function error(string $code): \WP_Error {
        return new \WP_Error($code, 'Notes import mapping requires administrator review before import.');
    }
}

<?php
declare(strict_types=1);

namespace CB\Notes\Sharing;

defined('ABSPATH') || exit;

/**
 * Public frozen copies need a trust anchor outside WordPress' SQL snapshot.
 *
 * Operator provisions/rotates CB_NOTES_SHARE_EPOCH_KEY in protected host
 * configuration (64 hexadecimal characters, 32 random bytes). An old DB
 * backup cannot restore this secret on its own. A full filesystem/config
 * rollback CAN restore it: quarantine + re-rotation are required before
 * public access is restored. This is not an automatic restore detector.
 */
final class PublicShareGuard {
    private const VERSION = 1;
    private const KEY_CONSTANT = 'CB_NOTES_SHARE_EPOCH_KEY';

    private static function key(): string|false {
        if (!defined(self::KEY_CONSTANT)) {
            return false;
        }
        $value = constant(self::KEY_CONSTANT);
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            return false;
        }
        $decoded = hex2bin($value);
        return $decoded !== false && strlen($decoded) === 32 ? $decoded : false;
    }

    /** No default to wp_options, auth salts or a key bundled in plugin code. */
    public static function ready(): bool {
        return self::key() !== false
            && is_string(home_url('/'))
            && preg_match('~^https?://[^/]+/~i', home_url('/')) === 1;
    }

    /** @return array<string,mixed>|false New immutable authenticated snapshot. */
    public static function sign(int $postId, array $snapshot): array|false {
        if ($postId < 1 || !self::ready()) {
            return false;
        }
        $payload = self::payload($postId, $snapshot);
        $secret = self::key();
        if ($payload === false || $secret === false) {
            return false;
        }
        $snapshot['signature_version'] = self::VERSION;
        $snapshot['signature'] = hash_hmac('sha256', $payload, $secret);
        return $snapshot;
    }

    public static function verify(int $postId, array $snapshot): bool {
        if (($snapshot['signature_version'] ?? null) !== self::VERSION) {
            return false; // Legacy Alpha24 snapshots are intentionally disarmed.
        }
        $signature = $snapshot['signature'] ?? null;
        if (!is_string($signature) || preg_match('/^[a-f0-9]{64}$/D', $signature) !== 1) {
            return false;
        }
        $secret = self::key();
        $payload = self::ready() ? self::payload($postId, $snapshot) : false;
        return $secret !== false && $payload !== false
            && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    private static function payload(int $postId, array $snapshot): string|false {
        foreach (['key', 'html', 'title', 'modified', 'created'] as $name) {
            if (!array_key_exists($name, $snapshot) || !is_string($snapshot[$name])) {
                return false;
            }
        }
        if (preg_match('/^[a-f0-9]{32}$/D', $snapshot['key']) !== 1) {
            return false;
        }
        // Exact configured WordPress home URL binds grants to the site identity.
        // Moving or cloning the site requires explicit new public links.
        $values = [
            'cb-notes-public-share-v1',
            home_url('/'),
            $postId,
            $snapshot['key'],
            hash('sha256', $snapshot['html']),
            hash('sha256', $snapshot['title']),
            $snapshot['modified'],
            $snapshot['created'],
        ];
        $encoded = wp_json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) && $encoded !== '' ? $encoded : false;
    }
}

<?php
declare(strict_types=1);

namespace CB\Notes\Integration;

use CB\Notes\Plugin;
use CB\Notes\Sharing\Controller;
use CB\SecureShare\PublicApi\AccessContext;
use CB\SecureShare\PublicApi\ResourceProvider;
use CB\SecureShare\PublicApi\ResourceResponse;
use CB\SecureShare\PublicApi\ResourceUnavailable;

defined('ABSPATH') || exit;

/**
 * Optional Secure Share consumer. The Secure Share runtime grants transport
 * authority; Notes then rechecks its current private sharing state.
 */
final class SecureProvider implements ResourceProvider {
    private static ?self $instance = null;

    public static function available(): bool {
        return class_exists('CB\\SecureShare\\PublicApi\\SecureShare')
            && interface_exists(ResourceProvider::class)
            && \CB\SecureShare\PublicApi\SecureShare::isAvailable();
    }

    public static function register(): void {
        if (!self::available()) {
            return;
        }
        self::$instance ??= new self();
        \CB\SecureShare\PublicApi\SecureShare::registerProvider(self::$instance);
    }

    public function providerId(): string {
        return 'core-blueprint-notes';
    }

    public function deliver(AccessContext $context): ResourceResponse {
        if ($context->action() !== 'view' || $context->resourceType() !== 'note'
            || preg_match('/^([1-9][0-9]*):([a-f0-9]{32})$/D', $context->resourceKey(), $parts) !== 1) {
            throw ResourceUnavailable::unavailable();
        }
        $id = (int)$parts[1];
        $post = get_post($id);
        $record = get_post_meta($id, Controller::SECURE_META, true);
        if (!$post instanceof \WP_Post || $post->post_type !== Plugin::TYPE ||
            in_array($post->post_status, ['trash', 'auto-draft'], true) ||
            get_post_meta($id, Controller::MODE_META, true) !== 'secure' ||
            !is_array($record) ||
            !hash_equals((string)($record['key'] ?? ''), $parts[2]) ||
            !hash_equals((string)($record['grant_id'] ?? ''), $context->grantId()) ||
            (int)($record['revision'] ?? 0) !== $context->grantRevision()) {
            throw ResourceUnavailable::unavailable();
        }
        $title = esc_html((string)($record['title'] ?? 'Shared note'));
        $body = (string)($record['html'] ?? '');
        $html = '<!doctype html><html lang="' . esc_attr(get_bloginfo('language')) .
            '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' .
            '<meta name="robots" content="noindex,nofollow,noarchive"><title>' . $title . '</title></head>' .
            '<body><main><h1>' . $title . '</h1>' . $body . '</main></body></html>';
        return ResourceResponse::html($html, (string)($record['title'] ?? 'Shared note'));
    }
}

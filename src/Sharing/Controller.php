<?php
declare(strict_types=1);

namespace CB\Notes\Sharing;

use CB\Notes\Plugin;
use CB\Notes\Integration\SecureProvider;

defined('ABSPATH') || exit;

/**
 * Public and Secure Share routes use frozen snapshots. Normal WordPress
 * post visibility never authorizes an anonymous Notes reader.
 */
final class Controller {
    public const MODE_META = '_cb_notes_share_mode';
    public const PUBLIC_META = '_cb_notes_public_snapshot';
    public const PUBLIC_KEY = '_cb_notes_public_key';
    public const SECURE_META = '_cb_notes_secure_snapshot';

    public static function register(): void {
        add_rewrite_rule('^cb-notes/shared/([a-f0-9]{32})/?$', 'index.php?cb_notes_public=$matches[1]', 'top');
        add_rewrite_tag('%cb_notes_public%', '([a-f0-9]{32})');
    }

    public static function rest(): void {
        register_rest_route('core-blueprint-notes/v1', '/sharing/(?P<id>[1-9][0-9]*)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [self::class, 'read'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'change'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);
    }

    public static function can_manage(\WP_REST_Request $request): bool {
        $id = absint($request->get_param('id'));
        $post = get_post($id);
        return $post instanceof \WP_Post
            && $post->post_type === Plugin::TYPE
            && $post->post_status !== 'trash'
            && (int)$post->post_author === get_current_user_id()
            && current_user_can('edit_post', $id);
    }

    private static function content_version(\WP_Post $post): string {
        return hash('sha256', $post->post_title . "\\n" . $post->post_content . "\\n" . $post->post_modified_gmt);
    }

    private static function mode(int $id): string {
        $stored = (string)get_post_meta($id, self::MODE_META, true);
        return in_array($stored, ['public', 'secure'], true) ? $stored : 'private';
    }

    public static function read(\WP_REST_Request $request): array|\WP_Error {
        $post = get_post(absint($request->get_param('id')));
        if (!$post instanceof \WP_Post || !self::can_manage($request)) {
            return self::error('Note not found.', 404);
        }
        $mode = self::mode($post->ID);
        $snapshot = get_post_meta($post->ID, self::PUBLIC_META, true);
        return [
            'mode' => $mode,
            'modified_gmt' => (string)$post->post_modified_gmt,
            'content_version' => self::content_version($post),
            'public_url' => $mode === 'public' && is_array($snapshot)
                ? self::url((string)($snapshot['key'] ?? '')) : null,
            'secure_available' => SecureProvider::available(),
            'has_secure_link' => $mode === 'secure',
            'shared_at' => $mode === 'public' && is_array($snapshot)
                ? (string)($snapshot['created'] ?? '') : null,
        ];
    }

    public static function change(\WP_REST_Request $request): array|\WP_Error {
        $post = get_post(absint($request->get_param('id')));
        if (!$post instanceof \WP_Post || !self::can_manage($request)) {
            return self::error('Note not found.', 404);
        }
        $mode = (string)$request->get_param('mode');
        if (!in_array($mode, ['private', 'public', 'secure'], true)) {
            return self::error('Invalid sharing mode.', 422);
        }
        if ($mode === 'private') {
            // Revocation must remain available regardless of unsaved/stale edits.
            update_post_meta($post->ID, self::MODE_META, 'private');
            delete_post_meta($post->ID, self::PUBLIC_META);
            delete_post_meta($post->ID, self::PUBLIC_KEY);
            self::revoke_old_secure($post->ID);
            self::record('private', $post->ID);
            return self::read($request);
        }
        // Timestamp precision alone cannot detect edits within the same second.
        $expectedMode = (string)$request->get_param('expected_mode');
        if ($expectedMode !== self::mode($post->ID)) {
            return self::error('Sharing settings have changed. Reload before continuing.', 409);
        }
        $contentVersion = (string)$request->get_param('expected_content_version');
        if ($contentVersion === '' || !hash_equals(self::content_version($post), $contentVersion)) {
            return self::error('Note content has changed. Reload before sharing.', 409);
        }
        $expected = (string)$request->get_param('expected_modified_gmt');
        if ($expected === '' || $expected !== (string)$post->post_modified_gmt) {
            return self::error('This note has changed. Reload and review the latest version before sharing.', 409);
        }
        if ($mode === 'public' && (
            !current_user_can('cb_notes_share_public') ||
            $request->get_param('confirmation') !== 'PUBLIC'
        )) {
            return self::error('Public sharing requires a separate explicit confirmation.', 403);
        }
        if ($mode === 'secure' && !current_user_can('cb_notes_share_secure')) {
            return self::error('Secure sharing is not permitted for this account.', 403);
        }

        $html = Snapshot::capture($post);
        if (is_wp_error($html)) {
            return $html;
        }
        if ($mode === 'public') {
            $key = bin2hex(random_bytes(16));
            $snapshot = [
                'key' => $key, 'html' => $html,
                'title' => sanitize_text_field($post->post_title),
                'modified' => (string)$post->post_modified_gmt,
                'created' => gmdate('c'),
            ];
            update_post_meta($post->ID, self::PUBLIC_META, $snapshot);
            update_post_meta($post->ID, self::PUBLIC_KEY, $key);
            update_post_meta($post->ID, self::MODE_META, 'public');
            self::revoke_old_secure($post->ID);
            self::record('public', $post->ID);
            return self::read($request);
        }

        if (!SecureProvider::available()) {
            return self::error('Core Blueprint Secure Share is not available.', 503);
        }
        $password = $request->get_param('password');
        $password = is_string($password) && $password !== '' ? $password : null;
        $expiryDays = absint($request->get_param('expiry_days') ?: 7);
        if ($expiryDays < 1 || $expiryDays > 30) {
            return self::error('Secure links must expire within 1 to 30 days.', 422);
        }

        // Disarm existing sharing before issuing a replacement grant.
        update_post_meta($post->ID, self::MODE_META, 'private');
        self::revoke_old_secure($post->ID);
        delete_post_meta($post->ID, self::PUBLIC_META);
        delete_post_meta($post->ID, self::PUBLIC_KEY);
        $snapshotKey = bin2hex(random_bytes(16));
        try {
            $requestClass = \CB\SecureShare\PublicApi\ShareRequest::class;
            $facade = \CB\SecureShare\PublicApi\SecureShare::class;
            // Save a pending snapshot: delivery stays closed until mode and grant ID match.
            $pending = [
                'key' => $snapshotKey, 'html' => $html,
                'title' => sanitize_text_field($post->post_title),
                'created' => gmdate('c'),
                'grant_id' => '', 'revision' => 0,
            ];
            update_post_meta($post->ID, self::SECURE_META, $pending);
            $issued = $facade::issue(new $requestClass(
                provider: 'core-blueprint-notes',
                resourceType: 'note',
                resourceKey: $post->ID . ':' . $snapshotKey,
                actions: ['view'],
                label: 'Shared Note ' . $post->ID,
                password: $password,
                expiresAt: new \DateTimeImmutable('+' . $expiryDays . ' days', new \DateTimeZone('UTC')),
                createdBy: get_current_user_id()
            ));
            $pending['grant_id'] = $issued->grantId();
            $pending['revision'] = $issued->revision();
            update_post_meta($post->ID, self::SECURE_META, $pending);
            delete_post_meta($post->ID, self::PUBLIC_META);
            delete_post_meta($post->ID, self::PUBLIC_KEY);
            update_post_meta($post->ID, self::MODE_META, 'secure');
            self::record('secure', $post->ID);
            $result = self::read($request);
            // Bearer can only be copied on initial creation, never persisted or logged.
            $result['invitation_url'] = $issued->invitationUrl();
            return $result;
        } catch (\Throwable $error) {
            // Fail closed after error, including a possibly issued-but-uncommitted grant.
            update_post_meta($post->ID, self::MODE_META, 'private');
            delete_post_meta($post->ID, self::PUBLIC_META);
            delete_post_meta($post->ID, self::PUBLIC_KEY);
            delete_post_meta($post->ID, self::SECURE_META);
            return self::error('Secure sharing failed. No new link is active in Notes.', 500);
        }
    }

    /** Revoking local provider authority is immediate, even without Secure Share. */
    private static function revoke_old_secure(int $id): void {
        $old = get_post_meta($id, self::SECURE_META, true);
        delete_post_meta($id, self::SECURE_META);
        if (!is_array($old) || empty($old['grant_id']) || !SecureProvider::available()) {
            return;
        }
        try {
            \CB\SecureShare\PublicApi\SecureShare::revoke((string)$old['grant_id'], (int)$old['revision']);
        } catch (\Throwable $error) {
            // Provider is already disarmed by removing the Notes snapshot.
            // Never reveal sensitive grant information or bearer secrets.
            error_log('[Core Blueprint Notes] Secure Share grant revocation needs operator review.');
        }
    }

    private static function record(string $mode, int $id): void {
        \CoreBlueprint\Core\Governance\Audit::record('cbnotes.record.updated', 'notice', [
            'note_id' => $id, 'sharing_mode' => $mode,
        ]);
    }

    private static function url(string $key): string {
        return preg_match('/^[a-f0-9]{32}$/D', $key)
            ? home_url('/cb-notes/shared/' . $key . '/')
            : '';
    }

    private static function error(string $message, int $status): \WP_Error {
        return new \WP_Error('cb_notes_sharing_unavailable', __($message, 'core-blueprint-notes'), ['status' => $status]);
    }

    /** Authorization invariant shared by the public route and regression tests. */
    public static function public_snapshot_allowed(\WP_Post $post, string $key, mixed $snapshot): bool {
        if ($post->post_type !== Plugin::TYPE
            || in_array($post->post_status, ['trash', 'auto-draft', 'inherit'], true)
            || self::mode((int)$post->ID) !== 'public'
            || !is_array($snapshot)
            || preg_match('/^[a-f0-9]{32}$/D', $key) !== 1) {
            return false;
        }
        $stored = $snapshot['key'] ?? null;
        return is_string($stored) && strlen($stored) === 32
            && hash_equals($stored, $key);
    }

    /** Only the explicitly shared frozen copy is delivered to anonymous visitors. */
    public static function deliver_public(): void {
        $key = (string)get_query_var('cb_notes_public', '');
        if ($key === '') {
            return;
        }
        if (!preg_match('/^[a-f0-9]{32}$/D', $key)) {
            self::not_found();
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        global $wpdb;
        // Public routing is an explicit share-grant lookup, not a normal
        // WordPress post query; private-status posts must remain resolvable.
        $id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s ORDER BY meta_id DESC LIMIT 1",
            self::PUBLIC_KEY, $key
        ));
        $post = $id > 0 ? get_post($id) : null;
        if (!$post instanceof \WP_Post) {
            self::not_found();
        }
        $snapshot = get_post_meta($post->ID, self::PUBLIC_META, true);
        if (!self::public_snapshot_allowed($post, $key, $snapshot)) {
            self::not_found();
        }
        self::response_headers();
        $title = esc_html((string)($snapshot['title'] ?? 'Shared note'));
        $html = (string)($snapshot['html'] ?? '');
        echo '<!doctype html><html lang="' . esc_attr(get_bloginfo('language')) . '"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<meta name="robots" content="noindex,nofollow,noarchive">';
        echo '<title>' . $title . '</title><style>body{font:1.06rem/1.7 system-ui,sans-serif;max-width:68ch;margin:4rem auto;padding:0 1.4rem;color:#17202b;background:#fff}h1{line-height:1.25}pre,code{white-space:pre-wrap;overflow-wrap:anywhere}table{border-collapse:collapse;max-width:100%}td,th{border:1px solid #aaa;padding:.4rem}a{color:#075ba6}</style></head><body>';
        echo '<main><h1>' . $title . '</h1>' . $html . '</main></body></html>';
        exit;
    }

    public static function response_headers(): void {
        status_header(200);
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        header('Referrer-Policy: no-referrer', true);
        header('X-Content-Type-Options: nosniff', true);
        header('X-Frame-Options: DENY', true);
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'", true);
    }

    private static function not_found(): never {
        status_header(404);
        nocache_headers();
        exit;
    }
}

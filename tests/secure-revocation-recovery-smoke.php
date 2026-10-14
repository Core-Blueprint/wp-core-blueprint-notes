<?php
declare(strict_types=1);

/** No-WordPress failure simulation for durable Secure Share revocation. */
namespace {
    define('ABSPATH', __DIR__ . '/');
    $GLOBALS['revocations'] = [];
    $GLOBALS['options'] = [];
    $GLOBALS['provider_available'] = true;
    $GLOBALS['provider_behavior'] = 'false';
    $GLOBALS['add_option_failed'] = false;
    $GLOBALS['logs'] = [];
    function add_option(string $key, mixed $value, string $deprecated = '', mixed $autoload = null): bool {
        if (($GLOBALS['add_option_failed'] ?? false) || array_key_exists($key, $GLOBALS['options'])) return false;
        $GLOBALS['options'][$key] = $value;
        return true;
    }
    function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['options'][$key] ?? $default; }
    function delete_option(string $key): bool { unset($GLOBALS['options'][$key]); return true; }
    function error_log(string $message): bool { $GLOBALS['logs'][] = $message; return true; }
    class MockWpdb {
        public string $options = 'wp_options';
        public function esc_like(string $value): string { return $value; }
        public function prepare(string $sql, mixed ...$values): string { return $sql; }
        public function get_col(string $sql): array {
            return array_keys(array_filter($GLOBALS['options'], static fn($v,$k): bool =>
                str_starts_with((string)$k, 'cb_notes_revoke_pending_v1_'), ARRAY_FILTER_USE_BOTH));
        }
        public function get_var(string $sql): int {
            return count($this->get_col($sql));
        }
    }
    $wpdb = new MockWpdb();
    function check(bool $valid, string $reason): void {
        if (!$valid) { fwrite(STDERR, "FAIL: {$reason}\n"); exit(1); }
        echo "PASS: {$reason}\n";
    }
}
namespace CB\Notes\Integration {
    final class SecureProvider {
        public static function available(): bool { return (bool)$GLOBALS['provider_available']; }
    }
}
namespace CB\SecureShare\PublicApi {
    final class SecureShare {
        public static function revoke(string $id, int $revision): bool {
            $GLOBALS['revocations'][] = [$id, $revision];
            return match ($GLOBALS['provider_behavior']) {
                'true' => true,
                'throw' => throw new \RuntimeException('synthetic provider timeout'),
                default => false,
            };
        }
    }
}
namespace {
    require dirname(__DIR__) . '/src/Sharing/SecureRevocationRecovery.php';
    use CB\Notes\Sharing\SecureRevocationRecovery as Recovery;

    $id = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    check(!Recovery::revoke('not-a-grant', 1), 'Malformed provider ID is refused');
    check(Recovery::pending_count() === 0, 'Malformed provider ID is not persisted');
    check(!Recovery::revoke($id, 1), 'Provider false does not count as successfully revoked');
    check(Recovery::pending_count() === 1, 'Failed provider grant is durably queued');
    $record = reset($GLOBALS['options']);
    check(($record['grant_id'] ?? null) === $id && ($record['revision'] ?? 0) === 1
        && !array_key_exists('bearer', $record) && !array_key_exists('password', $record),
        'Recovery record stores only grant UUID/revision and no secrets');

    $GLOBALS['provider_behavior'] = 'throw';
    check(Recovery::retry_pending() === 0 && Recovery::pending_count() === 1,
        'Provider exception preserves recovery obligation');
    $GLOBALS['provider_available'] = false;
    check(Recovery::retry_pending() === 0 && Recovery::pending_count() === 1,
        'Missing optional provider keeps queue for operator');
    $GLOBALS['provider_available'] = true;
    $GLOBALS['provider_behavior'] = 'true';
    check(Recovery::retry_pending() === 1 && Recovery::pending_count() === 0,
        'Explicit successful retry clears persisted recovery obligation');

    $GLOBALS['provider_behavior'] = 'true';
    check(Recovery::revoke($id, 2) && Recovery::pending_count() === 0,
        'Immediate successful provider revocation leaves no queue');
    $GLOBALS['provider_behavior'] = 'false';
    $GLOBALS['add_option_failed'] = true;
    check(!Recovery::revoke($id, 3) && Recovery::pending_count() === 0,
        'Queue storage failure is not silently recorded as success');
    $GLOBALS['add_option_failed'] = false;
    check(count($GLOBALS['logs']) > 0 && !str_contains(implode("\n", $GLOBALS['logs']), $id),
        'Operator logs never contain grant UUIDs or bearer material');
    echo "Notes Secure Share durable revocation recovery smoke: PASS\n";
}

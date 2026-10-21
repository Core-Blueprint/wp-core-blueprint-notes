<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CoreBlueprint\Core\DataExchange\Engine;
use CoreBlueprint\Core\DataExchange\Foundation;

/**
 * Canonical Base-backed public import orchestration.
 * Does not accept caller-supplied Notes ticket, actor or import mode.
 * A complete Notes document session encloses EACH Base preview/apply call.
 */
final class ImportFlow {
    /** @return array<string,mixed>|\WP_Error */
    public static function preview_json(string $bytes): array|\WP_Error {
        if (!self::owner() || strlen($bytes) < 1 || strlen($bytes) > Foundation::MAX_INPUT_BYTES) {
            return self::error('cb_notes_import_forbidden');
        }
        return ImportSession::with_validated_json(
            $bytes,
            static function (array $context) use ($bytes): array|\WP_Error {
                $base = Engine::preview_json($bytes, Foundation::MODE_CREATE_ONLY, $context);
                if ($base instanceof \WP_Error) {
                    return $base;
                }
                if (!is_array($base) || ($base['valid'] ?? false) !== true
                    || !is_string($base['fingerprint'] ?? null)
                    || preg_match('/^[a-f0-9]{64}$/D', $base['fingerprint']) !== 1) {
                    return self::error('cb_notes_import_preview_invalid');
                }
                $plan = ImportSession::target_plan();
                if ($plan instanceof \WP_Error) {
                    return $plan;
                }
                return [
                    'file_sha256' => hash('sha256', $bytes),
                    'base_fingerprint' => $base['fingerprint'],
                    'create_count' => $plan['create_count'],
                    'skip_count' => $plan['skip_count'],
                    'record_count' => $plan['record_count'],
                    'catalog_changed' => $plan['catalog'] !== $plan['previous_catalog'],
                    'warning_count' => array_sum(array_map(
                        static fn(array $item): int => count($item['warnings']),
                        $plan['plans']
                    )),
                ];
            }
        );
    }

    /**
     * Confirmation must already have passed a separate owner/nonce/UI
     * acknowledgement check. Replays full Notes preflight and Base fingerprint.
     * Never accept a private session ticket supplied by a browser.
     * @return array<string,mixed>|\WP_Error
     */
    public static function apply_json(
        string $bytes,
        string $expected_file_sha,
        string $expected_base_fingerprint
    ): array|\WP_Error {
        if (!self::owner() || strlen($bytes) < 1 || strlen($bytes) > Foundation::MAX_INPUT_BYTES
            || preg_match('/^[a-f0-9]{64}$/D', $expected_file_sha) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $expected_base_fingerprint) !== 1
            || !hash_equals($expected_file_sha, hash('sha256', $bytes))) {
            return self::error('cb_notes_import_confirm_invalid');
        }
        return ImportSession::with_validated_json(
            $bytes,
            static function (array $context) use ($bytes, $expected_base_fingerprint): array|\WP_Error {
                $result = Engine::apply_json(
                    $bytes, Foundation::MODE_CREATE_ONLY, $expected_base_fingerprint, $context
                );
                if ($result instanceof \WP_Error) {
                    return $result;
                }
                if (!is_array($result)
                    || !in_array($result['status'] ?? null, ['complete', 'partial', 'failed'], true)) {
                    return self::error('cb_notes_import_result_invalid');
                }
                return [
                    'status' => $result['status'],
                    'applied_count' => self::count($result['applied_count'] ?? null),
                    'skipped_count' => self::count($result['skipped_count'] ?? null),
                    'failed_index' => is_int($result['failed_index'] ?? null)
                        ? $result['failed_index'] : null,
                    'needs_review' => ($result['status'] ?? null) !== 'complete'
                        && (
                            ($result['status'] ?? null) === 'partial'
                            || in_array($result['error']['code'] ?? null, [
                                'cb_notes_import_create_uncertain',
                                'cb_notes_import_timestamp_write_failed',
                                'cb_notes_import_journal_needs_review',
                                'cb_notes_import_journal_completion_failed',
                                'cb_notes_import_journal_write_failed',
                            ], true)
                        ),
                ];
            }
        );
    }

    private static function count(mixed $value): int {
        return is_int($value) && $value >= 0 ? min(5000, $value) : 0;
    }

    private static function owner(): bool {
        return get_current_user_id() > 0 && current_user_can('cb_notes_edit');
    }

    private static function error(string $code): \WP_Error {
        return new \WP_Error($code, 'Notes import could not be safely authorized or confirmed.');
    }
}

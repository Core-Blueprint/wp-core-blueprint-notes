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
                    // Base apply may already have written part of the batch:
                    // never mislabel unexpected outcomes as safe "failed".
                    return ['status' => 'recovery', 'applied_count' => 0,
                        'skipped_count' => 0, 'failed_index' => null,
                        'needs_review' => true];
                }
                // Base results include the catalog as record 0. Verify that
                // every reported successful item belongs to our exact bound
                // plan BEFORE showing a count. After apply may have written
                // anything, an unknown result is RECOVERY, never "complete".
                $plan = ImportSession::target_plan();
                $expected = is_array($plan) ? $plan['plans'] ?? null : null;
                $items = $result['items'] ?? null;
                $valid = is_array($expected) && array_is_list($expected)
                    && is_array($items) && array_is_list($items)
                    && is_int($result['record_count'] ?? null)
                    && $result['record_count'] === count($expected) + 1
                    && count($items) <= count($expected) + 1;
                $applied_notes = 0;
                $skipped_notes = 0;
                $base_applied = 0;
                $base_skipped = 0;
                if ($valid) {
                    foreach ($items as $position => $item) {
                        $expected_note = $position > 0 ? ($expected[$position - 1] ?? null) : null;
                        if ($position > 0 && !is_array($expected_note)) {
                            $valid = false;
                            break;
                        }
                        $expected_ref = $position === 0 ? 'catalog:personal'
                            : ($expected_note['source_reference'] ?? null);
                        $expected_op = $position === 0
                            ? ($plan['catalog'] === $plan['previous_catalog']
                                ? Foundation::OP_SKIP : Foundation::OP_CREATE)
                            : (($expected_note['operation'] ?? null) === 'skip'
                                ? Foundation::OP_SKIP : Foundation::OP_CREATE);
                        if (!is_array($item) || ($item['index'] ?? null) !== $position
                            || ($item['reference'] ?? null) !== $expected_ref
                            || !in_array($item['operation'] ?? null,
                                [Foundation::OP_CREATE, Foundation::OP_SKIP], true)
                            || $item['operation'] !== $expected_op) {
                            $valid = false;
                            break;
                        }
                        if ($item['operation'] === Foundation::OP_CREATE) {
                            ++$base_applied;
                            if ($position > 0) {
                                ++$applied_notes;
                            }
                        } else {
                            ++$base_skipped;
                            if ($position > 0) {
                                ++$skipped_notes;
                            }
                        }
                    }
                }
                $valid = $valid && ($result['applied_count'] ?? null) === $base_applied
                    && ($result['skipped_count'] ?? null) === $base_skipped
                    && (
                        ($result['status'] === 'complete' && count($items) === count($expected) + 1)
                        || ($result['status'] !== 'complete' && is_int($result['failed_index'] ?? null)
                            && $result['failed_index'] === count($items)
                            && count($items) < count($expected) + 1)
                    );
                $error_code = is_array($result['error'] ?? null)
                    && is_string($result['error']['code'] ?? null)
                    ? $result['error']['code'] : '';
                $review = !$valid || $result['status'] === 'partial'
                    || in_array($error_code, [
                        'cb_notes_import_create_uncertain',
                        'cb_notes_import_timestamp_write_failed',
                        'cb_notes_import_journal_needs_review',
                        'cb_notes_import_journal_completion_failed',
                        'cb_notes_import_journal_write_failed',
                    ], true);
                return [
                    'status' => $valid ? $result['status'] : 'recovery',
                    'applied_count' => $valid ? $applied_notes : 0,
                    'skipped_count' => $valid ? $skipped_notes : 0,
                    'failed_index' => is_int($result['failed_index'] ?? null)
                        ? $result['failed_index'] : null,
                    'needs_review' => $review,
                ];
            }
        );
    }

    private static function owner(): bool {
        return get_current_user_id() > 0 && current_user_can('cb_notes_edit');
    }

    private static function error(string $code): \WP_Error {
        return new \WP_Error($code, 'Notes import could not be safely authorized or confirmed.');
    }
}

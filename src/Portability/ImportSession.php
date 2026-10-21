<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CB\Notes\Organization\Catalog;

/**
 * Per-request, owner-bound whole-archive attestation for Base Data Exchange.
 * Base still validates its transport envelope and computes its own plan
 * fingerprint; this component guarantees the Notes document graph has been
 * preflighted BEFORE any per-record import callbacks can become eligible.
 *
 * There is intentionally no persistent bearer token and no open import route.
 */
final class ImportSession {
    private static ?array $active = null;

    /**
     * @param callable(array):mixed $callback Receives a private in-request context.
     */
    public static function with_validated_json(string $json, callable $callback): mixed {
        if (self::$active !== null || strlen($json) > 10 * 1024 * 1024 || str_contains($json, "\0")
            || get_current_user_id() < 1 || !current_user_can('cb_notes_edit')) {
            return self::error('cb_notes_import_session_forbidden');
        }
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            unset($exception);
            return self::error('cb_notes_import_invalid_json');
        }
        if (!is_array($data) || array_is_list($data)
            || !self::keys($data, ['format', 'format_version', 'extension_id', 'entity',
                'schema_version', 'exported_at', 'records'])
            || $data['format'] !== 'core-blueprint-data-exchange'
            || $data['format_version'] !== 1
            || $data['extension_id'] !== 'core-blueprint-notes'
            || $data['entity'] !== 'personal-notes'
            || $data['schema_version'] !== 1
            || !is_string($data['exported_at']) || strlen($data['exported_at']) > 64
            || !is_array($data['records']) || !array_is_list($data['records'])) {
            return self::error('cb_notes_import_invalid_envelope');
        }
        $archive = ArchivePreflight::inspect($data['records'], 1);
        if ($archive instanceof \WP_Error) {
            return $archive;
        }
        $owner = get_current_user_id();
        $target = Catalog::catalog($owner);
        $secret = MappingSeed::read();
        if ($secret instanceof \WP_Error) {
            return $secret;
        }
        // Deliberately accept no client-provided owner, source identity or
        // previously-completed receipts. The journal will supply verified
        // receipts at the later, explicitly gated P2-B stage.
        $completed = [];
        foreach ($archive['notes'] as $note) {
            $receipt = ImportJournal::receipt($owner, $archive['digest'], $note['reference']);
            if ($receipt instanceof \WP_Error) {
                // A pending or mismatched receipt is an explicit recovery stop:
                // never silently retry a possibly already-created note.
                return $receipt;
            }
            if (($receipt['state'] ?? null) === 'complete') {
                $completed[$archive['digest'] . ':' . $note['reference']] = $receipt['post_id'];
            } elseif (($receipt['state'] ?? null) !== 'new') {
                return self::error('cb_notes_import_session_unavailable');
            }
        }
        $plan = ImportPreview::plan($archive, $target, $owner, $secret, $completed);
        if ($plan instanceof \WP_Error) {
            return $plan;
        }
        try {
            $ticket = bin2hex(random_bytes(24));
        } catch (\Throwable $throwable) {
            unset($throwable);
            return self::error('cb_notes_import_session_unavailable');
        }
        $by_ref = ['catalog:personal' => $data['records'][0]];
        foreach ($archive['notes'] as $note) {
            $by_ref[$note['reference']] = $note;
        }
        self::$active = [
            'ticket' => $ticket,
            'owner' => $owner,
            'digest' => $archive['digest'],
            'plan' => $plan,
            'records' => $by_ref,
        ];
        try {
            return $callback(['cb_notes_import_ticket' => $ticket]);
        } finally {
            // Never persist or return this capability to a browser.
            self::$active = null;
        }
    }

    /** Original catalog source, never the generated destination catalog. */
    public static function catalog(array $context): array|\WP_Error {
        if (!self::authorized($context)) {
            return self::error('cb_notes_import_session_forbidden');
        }
        return self::record(self::$active['records']['catalog:personal'], $context);
    }

    /** Attested destination catalog state for a single owner request. */
    public static function target_plan(): array|\WP_Error {
        if (self::$active === null || self::$active['owner'] !== get_current_user_id()
            || !current_user_can('cb_notes_edit')) {
            return self::error('cb_notes_import_session_forbidden');
        }
        return self::$active['plan'];
    }

    public static function target_catalog(): array {
        $plan = self::target_plan();
        return $plan instanceof \WP_Error ? [] : $plan['catalog'];
    }

    public static function authorized(array $context): bool {
        $session = self::$active;
        $candidate = $context['cb_notes_import_ticket'] ?? null;
        return is_array($session)
            && is_string($candidate)
            && strlen($candidate) === strlen($session['ticket'])
            && hash_equals($session['ticket'], $candidate)
            && $session['owner'] === get_current_user_id()
            && current_user_can('cb_notes_edit')
            && count($context) === 1;
    }

    /** A document-bound, exact record with its approved destination plan. */
    public static function record(array $record, array $context): array|\WP_Error {
        if (!self::authorized($context) || !is_string($record['reference'] ?? null)) {
            return self::error('cb_notes_import_session_forbidden');
        }
        $expected = self::$active['records'][$record['reference']] ?? null;
        if ($expected === null || $expected !== $record) {
            return self::error('cb_notes_import_record_changed');
        }
        $plan = self::$active['plan'];
        return [
            'source' => $expected,
            'preview' => $plan,
            'fingerprint' => $plan['fingerprint'],
        ];
    }

    private static function keys(array $value, array $expected): bool {
        $keys = array_keys($value);
        sort($keys);
        sort($expected);
        return $keys === $expected;
    }

    private static function error(string $code): \WP_Error {
        return new \WP_Error($code, 'Notes import is not authorized or cannot safely be validated.');
    }
}

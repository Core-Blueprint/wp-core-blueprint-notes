<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CoreBlueprint\Core\DataExchange\Foundation;

/**
 * Pure bridge from a verified Notes whole-document import session to Base's
 * public per-record plan format. There are NO writes here. Registration of
 * SUPPORT_IMPORT remains explicitly disabled until P2-B is runtime-approved.
 */
final class ImportPlanner {
    public static function plan_record(
        array $record,
        string $mode,
        int $source_schema_version,
        array $context
    ): array|\WP_Error {
        if ($mode !== Foundation::MODE_CREATE_ONLY
            || $source_schema_version !== ArchivePreflight::SCHEMA_VERSION) {
            return self::error('cb_notes_import_mode_forbidden');
        }
        $bound = ImportSession::record($record, $context);
        if ($bound instanceof \WP_Error) {
            return $bound;
        }
        $destination = $bound['preview'];
        if ($record['record_type'] === 'catalog' && $record['reference'] === 'catalog:personal') {
            // A separate Base plan is required for the catalog, before notes
            // may attach translated opaque IDs. Import service must use a
            // verified CAS boundary before applying this record.
            return [
                'operation' => $destination['catalog'] === $destination['previous_catalog']
                    ? Foundation::OP_SKIP : Foundation::OP_CREATE,
                'reference' => 'catalog:personal',
                'payload' => [
                    'record_type' => 'catalog',
                    'owner' => $destination['owner'],
                    'digest' => $destination['archive_digest'],
                    'catalog' => $destination['catalog'],
                ],
                'warnings' => [],
            ];
        }
        if ($record['record_type'] !== 'note') {
            return self::error('cb_notes_import_invalid_record');
        }
        foreach ($destination['plans'] as $planned) {
            if ($planned['source_reference'] !== $record['reference']) {
                continue;
            }
            $operation = $planned['operation'] === 'skip'
                ? Foundation::OP_SKIP : Foundation::OP_CREATE;
            return [
                'operation' => $operation,
                'reference' => $record['reference'],
                'payload' => [
                    'record_type' => 'note',
                    'owner' => $destination['owner'],
                    'digest' => $destination['archive_digest'],
                    'source' => $record,
                    'mapped' => $planned,
                ],
                'warnings' => $planned['warnings'],
            ];
        }
        return self::error('cb_notes_import_invalid_record');
    }

    private static function error(string $code): \WP_Error {
        return new \WP_Error($code, 'Notes import requires a validated create-only plan.');
    }
}

<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CoreBlueprint\Core\DataExchange\Engine;

/**
 * Read-only owner-only export service. No HTTP route is registered here.
 * Base owns the canonical JSON envelope; Notes owns only Markdown composition.
 */
final class OwnerExport {
    public static function json(): string|\WP_Error {
        if (!class_exists(Engine::class)) {
            return new \WP_Error('cb_notes_export_unavailable', 'Base Data Exchange is not available.');
        }
        return Engine::export_json('core-blueprint-notes', 'personal-notes');
    }

    public static function markdown_zip(): string|\WP_Error {
        $entity = new NotesEntity();
        if (!$entity->can_export()) {
            return new \WP_Error('cb_notes_export_forbidden', 'Notes export is not authorized.');
        }
        $records = $entity->export_records();
        if ($records instanceof \WP_Error) {
            return $records;
        }
        if (!is_array($records)) {
            return new \WP_Error('cb_notes_export_invalid_records', 'Notes export did not yield a complete record set.');
        }
        return MarkdownArchive::zip($records);
    }
}

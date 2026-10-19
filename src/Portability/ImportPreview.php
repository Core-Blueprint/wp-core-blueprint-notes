<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CB\Notes\Organization\Catalog;

/**
 * P2-A: deterministic, read-only destination plan for a complete validated
 * archive. Deliberately does not expose EntityInterface import capability.
 */
final class ImportPreview {
    private const KINDS = ['notebook', 'category', 'tag'];

    /**
     * @param array{digest:string,count:int,catalog:array,notes:array} $archive
     * @param array{notebook:array,category:array,tag:array} $target_catalog
     * @param array<string,int> $completed Fully verified owner-bound journal receipts.
     * @return array|\WP_Error
     */
    public static function plan(
        array $archive,
        array $target_catalog,
        int $owner,
        string $site_key,
        array $completed = []
    ): array|\WP_Error {
        if ($owner < 1 || strlen($site_key) < 32
            || !isset($archive['digest'], $archive['catalog'], $archive['notes'], $archive['count'])
            || !is_string($archive['digest'])
            || preg_match('/^[a-f0-9]{64}$/D', $archive['digest']) !== 1
            || !is_array($archive['catalog']) || !is_array($archive['notes'])
            || !array_is_list($archive['notes'])
            || $archive['count'] !== count($archive['notes'])) {
            return self::error('cb_notes_import_plan_invalid');
        }
        $map = [];
        $new_catalog = $target_catalog;
        foreach (self::KINDS as $kind) {
            $source = $archive['catalog'][$kind] ?? null;
            $destination = $target_catalog[$kind] ?? null;
            if (!is_array($source) || !array_is_list($source) || !is_array($destination)
                || !array_is_list($destination)) {
                return self::error('cb_notes_import_catalog_invalid');
            }
            $existing_names = [];
            $existing_ids = [];
            foreach ($destination as $label) {
                if (!is_array($label) || !isset($label['id'], $label['name'])
                    || !Catalog::valid_id($label['id']) || !is_string($label['name'])) {
                    return self::error('cb_notes_import_catalog_invalid');
                }
                $folded = self::fold($label['name']);
                if (isset($existing_names[$folded]) || isset($existing_ids[$label['id']])) {
                    return self::error('cb_notes_import_catalog_invalid');
                }
                $existing_ids[$label['id']] = $label['name'];
                $existing_names[$folded] = $label['id'];
            }
            foreach ($source as $label) {
                if (!is_array($label) || !isset($label['id'], $label['name'])
                    || !Catalog::valid_id($label['id']) || !is_string($label['name'])) {
                    return self::error('cb_notes_import_catalog_invalid');
                }
                $derived = substr(hash_hmac('sha256',
                    "cb_notes_import_v1\0{$owner}\0{$archive['digest']}\0{$kind}\0{$label['id']}",
                    $site_key), 0, 32);
                $map[$kind][$label['id']] = $derived;
                $folded = self::fold($label['name']);
                if (isset($existing_ids[$derived])) {
                    // Exact previously-created ID+name is eligible for safe
                    // resume, but the journal and owner check are still required.
                    if ($existing_ids[$derived] !== $label['name']
                        || $existing_names[$folded] !== $derived) {
                        return self::error('cb_notes_import_catalog_collision');
                    }
                    continue;
                }
                if (isset($existing_names[$folded])) {
                    // Never merge owner-private labels by name alone.
                    return self::error('cb_notes_import_catalog_collision');
                }
                $new_catalog[$kind][] = ['id' => $derived, 'name' => $label['name']];
                $existing_ids[$derived] = $label['name'];
                $existing_names[$folded] = $derived;
            }
            if (count($new_catalog[$kind]) > 150) {
                return self::error('cb_notes_import_catalog_limit');
            }
        }
        $plans = [];
        $create = 0;
        $skip = 0;
        foreach ($archive['notes'] as $note) {
            if (!is_array($note) || !isset($note['reference'], $note['status'])
                || !is_string($note['reference']) || !is_string($note['status'])) {
                return self::error('cb_notes_import_plan_invalid');
            }
            $key = $archive['digest'] . ':' . $note['reference'];
            $was_imported = array_key_exists($key, $completed);
            if ($was_imported && (!is_int($completed[$key]) || $completed[$key] < 1)) {
                return self::error('cb_notes_import_receipt_invalid');
            }
            $converted_status = $note['status'] === 'draft' ? 'draft' : 'private';
            $warnings = [];
            if ($converted_status !== $note['status']) {
                $warnings[] = 'Imported note will be private rather than its source status.';
            }
            $plans[] = [
                'source_reference' => $note['reference'],
                'operation' => $was_imported ? 'skip' : 'create',
                'target_id' => $was_imported ? $completed[$key] : null,
                'title' => $note['title'],
                'status' => $converted_status,
                'notebook_id' => $note['notebook_id'] === '' ? ''
                    : ($map['notebook'][$note['notebook_id']] ?? ''),
                'category_id' => $note['category_id'] === '' ? ''
                    : ($map['category'][$note['category_id']] ?? ''),
                'tag_ids' => array_map(static fn(string $id): string => $map['tag'][$id] ?? '', $note['tag_ids']),
                'warnings' => $warnings,
            ];
            if ($note['notebook_id'] !== '' && $plans[array_key_last($plans)]['notebook_id'] === ''
                || $note['category_id'] !== '' && $plans[array_key_last($plans)]['category_id'] === ''
                || in_array('', $plans[array_key_last($plans)]['tag_ids'], true)) {
                return self::error('cb_notes_import_relation_invalid');
            }
            $was_imported ? ++$skip : ++$create;
        }
        $result = [
            'owner' => $owner,
            'archive_digest' => $archive['digest'],
            'record_count' => $archive['count'],
            'create_count' => $create,
            'skip_count' => $skip,
            'catalog' => $new_catalog,
            'source_id_map' => $map,
            'plans' => $plans,
        ];
        try {
            $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            unset($exception);
            return self::error('cb_notes_import_plan_invalid');
        }
        $result['fingerprint'] = hash('sha256', $encoded);
        return $result;
    }

    private static function fold(string $value): string {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function error(string $code): \WP_Error {
        return new \WP_Error($code, 'Notes import preview cannot safely map this archive.');
    }
}

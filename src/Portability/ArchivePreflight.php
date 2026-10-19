<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CB\Notes\Organization\Catalog;

/**
 * P2-A: side-effect-free, whole-document Notes schema validation.
 *
 * Base owns envelope/format parsing. This class owns the *complete* Notes
 * record graph and does not authenticate an import, write records, or grant
 * sharing rights. No Base per-record callback is sufficient by itself.
 */
final class ArchivePreflight {
    public const SCHEMA_VERSION = 1;
    private const MAX_RECORDS = 5000;
    private const MAX_ITEMS = 150;
    private const MAX_TAGS = 12;
    private const STATUSES = ['draft', 'private', 'pending', 'future', 'publish'];
    private const CATALOG_KINDS = ['notebook', 'category', 'tag'];

    /** @return array{digest:string,count:int,catalog:array,notes:array}|\WP_Error */
    public static function inspect(array $records, int $schema_version = self::SCHEMA_VERSION): array|\WP_Error {
        if ($schema_version !== self::SCHEMA_VERSION || !array_is_list($records)
            || count($records) < 1 || count($records) > self::MAX_RECORDS) {
            return self::error('cb_notes_portability_schema');
        }
        $first = $records[0];
        if (!self::object_keys($first, ['record_type', 'reference', 'catalog'])
            || $first['record_type'] !== 'catalog'
            || $first['reference'] !== 'catalog:personal'
            || !is_array($first['catalog']) || array_is_list($first['catalog'])
            || !self::same_keys($first['catalog'], self::CATALOG_KINDS)) {
            return self::error('cb_notes_portability_catalog');
        }
        $catalog = $first['catalog'];
        $known = [];
        foreach (self::CATALOG_KINDS as $kind) {
            if (!is_array($catalog[$kind]) || !array_is_list($catalog[$kind])
                || count($catalog[$kind]) > self::MAX_ITEMS) {
                return self::error('cb_notes_portability_catalog');
            }
            $known[$kind] = [];
            $names = [];
            foreach ($catalog[$kind] as $item) {
                if (!self::object_keys($item, ['id', 'name'])
                    || !Catalog::valid_id($item['id'])
                    || !is_string($item['name'])
                    || $item['name'] !== trim($item['name'])
                    || $item['name'] === ''
                    || self::length($item['name']) > 80
                    || preg_match('/[\x00-\x1f\x7f]/', $item['name']) === 1) {
                    return self::error('cb_notes_portability_catalog');
                }
                $name_key = self::lower($item['name']);
                if (isset($known[$kind][$item['id']]) || isset($names[$name_key])) {
                    return self::error('cb_notes_portability_catalog_collision');
                }
                $known[$kind][$item['id']] = true;
                $names[$name_key] = true;
            }
        }

        $refs = ['catalog:personal' => true];
        $notes = [];
        foreach (array_slice($records, 1) as $record) {
            $required = ['record_type', 'reference', 'title', 'content', 'status',
                'created_gmt', 'modified_gmt', 'notebook_id', 'category_id',
                'tag_ids', 'favorite', 'pinned'];
            if (!self::object_keys($record, $required)
                || $record['record_type'] !== 'note'
                || !is_string($record['reference'])
                || preg_match('/^note:[1-9][0-9]*$/D', $record['reference']) !== 1
                || isset($refs[$record['reference']])
                || !is_string($record['title'])
                || !is_string($record['content'])
                || str_contains($record['title'], "\0")
                || str_contains($record['content'], "\0")
                || !in_array($record['status'], self::STATUSES, true)
                || !is_string($record['created_gmt'])
                || !is_string($record['modified_gmt'])
                || !self::gmt($record['created_gmt'])
                || !self::gmt($record['modified_gmt'])
                || !is_bool($record['favorite']) || !is_bool($record['pinned'])
                || !is_string($record['notebook_id'])
                || !is_string($record['category_id'])
                || !self::relation($known['notebook'], $record['notebook_id'])
                || !self::relation($known['category'], $record['category_id'])
                || !is_array($record['tag_ids']) || !array_is_list($record['tag_ids'])
                || count($record['tag_ids']) > self::MAX_TAGS) {
                return self::error('cb_notes_portability_record');
            }
            $tags = [];
            foreach ($record['tag_ids'] as $tag) {
                if (!is_string($tag) || $tag === '' || !self::relation($known['tag'], $tag)
                    || isset($tags[$tag])) {
                    return self::error('cb_notes_portability_relation');
                }
                $tags[$tag] = true;
            }
            $refs[$record['reference']] = true;
            $notes[] = $record;
        }
        try {
            $serialized = json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            unset($exception);
            return self::error('cb_notes_portability_encoding');
        }
        if (!is_string($serialized) || strlen($serialized) > 10 * 1024 * 1024) {
            return self::error('cb_notes_portability_size');
        }
        return ['digest' => hash('sha256', $serialized), 'count' => count($notes),
            'catalog' => $catalog, 'notes' => $notes];
    }

    private static function length(string $value): int {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        // mbstring is optional. Count Unicode codepoints without truncation.
        $count = preg_match_all('/./us', $value);
        return $count === false ? PHP_INT_MAX : $count;
    }

    private static function lower(string $value): string {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function object_keys(mixed $record, array $required): bool {
        return is_array($record) && !array_is_list($record) && self::same_keys($record, $required);
    }

    private static function same_keys(array $value, array $keys): bool {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);
        return $actual === $keys;
    }

    private static function relation(array $known, string $id): bool {
        return $id === '' || (Catalog::valid_id($id) && isset($known[$id]));
    }

    private static function gmt(string $value): bool {
        // WordPress uses this exact SQL datetime form for exported GMT fields.
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        return $dt instanceof \DateTimeImmutable && $dt->format('Y-m-d H:i:s') === $value;
    }

    private static function error(string $code): \WP_Error {
        return new \WP_Error($code, 'Notes portability archive did not pass complete validation.');
    }
}

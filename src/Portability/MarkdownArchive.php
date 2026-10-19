<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

/**
 * P3-B Notes-owned Markdown + YAML export (not a new Base transport).
 * Only prevalidated, owner-scoped P1 records are accepted.
 */
final class MarkdownArchive {
    /**
     * Pure archive composition. No disk operations or downloads.
     * @return array<string,string>|\WP_Error
     */
    public static function files(array $records): array|\WP_Error {
        $checked = ArchivePreflight::inspect($records);
        if ($checked instanceof \WP_Error) {
            return $checked;
        }
        $names = ['notebook' => [], 'category' => [], 'tag' => []];
        foreach ($names as $kind => $_) {
            foreach ($checked['catalog'][$kind] as $item) {
                $names[$kind][$item['id']] = $item['name'];
            }
        }
        $files = [];
        $manifest = [
            'format' => 'core-blueprint-notes-markdown',
            'format_version' => 1,
            'source_schema_version' => ArchivePreflight::SCHEMA_VERSION,
            'note_count' => $checked['count'],
            'archive_digest' => $checked['digest'],
            'contents' => ['notes/*.md', 'sources/*.gutenberg.txt'],
            'excluded' => ['share grants', 'share URLs', 'bearer tokens', 'snapshots',
                'revisions', 'media attachments', 'private uploaded files'],
            'limitations' => [
                'Markdown is a best-effort readable representation, not a lossless Gutenberg import.',
                'Each raw .gutenberg.txt sidecar preserves the exact exported block source.',
                'Links, embeds, media and custom/dynamic blocks are not downloaded or executed.',
                'Local WordPress post IDs in filenames are not portable identities.',
            ],
        ];
        try {
            $files['manifest.json'] = json_encode($manifest,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        } catch (\JsonException $exception) {
            unset($exception);
            return new \WP_Error('cb_notes_markdown_encoding', 'Notes archive metadata cannot be encoded.');
        }
        foreach ($checked['notes'] as $note) {
            $number = substr($note['reference'], strlen('note:'));
            // ArchivePreflight verifies positive ASCII digits, never a user filename.
            $basename = 'note-' . $number;
            $file = 'notes/' . $basename . '.md';
            $files[$file] = self::markdown($note, $names);
            $files['sources/' . $basename . '.gutenberg.txt'] = $note['content'];
        }
        return $files;
    }

    /**
     * Assemble an ephemeral private ZIP. Caller owns HTTP auth and no-cache
     * response; no site upload file or public URL is ever created here.
     */
    public static function zip(array $records): string|\WP_Error {
        $files = self::files($records);
        if ($files instanceof \WP_Error) {
            return $files;
        }
        if (!class_exists('ZipArchive')) {
            return new \WP_Error('cb_notes_markdown_zip_unavailable',
                'ZIP extension is unavailable; no Notes archive was created.');
        }
        $temp = tempnam(sys_get_temp_dir(), 'cb_notes_export_');
        if ($temp === false) {
            return new \WP_Error('cb_notes_markdown_zip_failed', 'Private ZIP archive could not be prepared.');
        }
        @chmod($temp, 0600);
        try {
            $zip = new \ZipArchive();
            if ($zip->open($temp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return new \WP_Error('cb_notes_markdown_zip_failed', 'Private ZIP archive could not be opened.');
            }
            foreach ($files as $path => $body) {
                if (!$zip->addFromString($path, $body)) {
                    $zip->close();
                    return new \WP_Error('cb_notes_markdown_zip_failed', 'Private ZIP archive could not be created.');
                }
            }
            if (!$zip->close()) {
                return new \WP_Error('cb_notes_markdown_zip_failed', 'Private ZIP archive could not be finalized.');
            }
            $bytes = file_get_contents($temp);
            if (!is_string($bytes) || $bytes === '') {
                return new \WP_Error('cb_notes_markdown_zip_failed', 'Private ZIP archive could not be read.');
            }
            return $bytes;
        } finally {
            // No persistent archive, no public uploads and no stale temporary file.
            @unlink($temp);
        }
    }

    private static function markdown(array $note, array $names): string {
        $notebook = $names['notebook'][$note['notebook_id']] ?? '';
        $category = $names['category'][$note['category_id']] ?? '';
        $tags = array_map(static fn(string $id): string => $names['tag'][$id], $note['tag_ids']);
        $yaml = [
            '---',
            'title: ' . self::quoted($note['title']),
            'notebook: ' . self::quoted($notebook),
            'category: ' . self::quoted($category),
            'tags:',
        ];
        if (!$tags) {
            $yaml[count($yaml) - 1] = 'tags: []';
        } else {
            foreach ($tags as $tag) {
                $yaml[] = '  - ' . self::quoted($tag);
            }
        }
        $yaml[] = 'status: ' . self::quoted($note['status']);
        $yaml[] = 'source_reference: ' . self::quoted($note['reference']);
        $yaml[] = 'created_gmt: ' . self::quoted($note['created_gmt']);
        $yaml[] = 'modified_gmt: ' . self::quoted($note['modified_gmt']);
        $yaml[] = 'favorite: ' . ($note['favorite'] ? 'true' : 'false');
        $yaml[] = 'pinned: ' . ($note['pinned'] ? 'true' : 'false');
        $yaml[] = '---';
        // Escape special heading characters; this is a readable representation.
        $title = trim(str_replace(["\r", "\n", "#", "<", ">"], [' ', ' ', '\\#', '&lt;', '&gt;'], $note['title']));
        $title = $title === '' ? 'Untitled note' : $title;
        return implode("\n", $yaml) . "\n\n# " . $title . "\n\n"
            . self::plain_text($note['content']) . "\n";
    }

    private static function quoted(string $value): string {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function plain_text(string $html): string {
        // Extract a readable static fallback without executing blocks/shortcodes.
        $text = preg_replace('/<!--[\s\S]*?-->/', '', $html) ?? '';
        $text = preg_replace('/<(script|style)\b[^>]*>[\s\S]*?<\/\1\s*>/i', '', $text) ?? '';
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? '';
        $text = preg_replace('/<li\b[^>]*>/i', "\n- ", $text) ?? '';
        $text = preg_replace('/<\/\s*(?:p|h[1-6]|li|ul|ol|blockquote|pre|div)\s*>/i', "\n\n", $text) ?? '';
        $text = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($text) : strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }
}

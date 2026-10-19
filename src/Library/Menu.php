<?php
declare(strict_types=1);

namespace CB\Notes\Library;

use CB\Notes\Plugin;

defined('ABSPATH') || exit;

/**
 * Make Library the single visible Notes workspace. Keep the native WordPress
 * list endpoint available for deep links, Gutenberg and compatibility, but
 * hide its duplicate submenu entry when Library is registered. Preserve a
 * native fallback for roles/environments without the Library submenu.
 */
final class Menu {
    private const LIBRARY = 'cb-notes-library';
    private const ORGANIZE = 'cb-notes-organize';

    public static function reorder(): void {
        global $submenu;

        $parent = 'edit.php?post_type=' . Plugin::TYPE;
        if (!isset($submenu[$parent]) || !is_array($submenu[$parent])) {
            return;
        }

        $submenu[$parent] = self::order($submenu[$parent]);
    }

    /** @param array<int, array<mixed>> $entries
     *  @return array<int, array<mixed>>
     */
    public static function order(array $entries): array {
        $native = 'edit.php?post_type=' . Plugin::TYPE;
        $new = 'post-new.php?post_type=' . Plugin::TYPE;

        // Never hide native navigation unless the personal Library is present.
        // This only filters the visible menu row; WordPress core keeps its
        // original edit.php list route and post type screens intact.
        $has_library = false;
        foreach ($entries as $entry) {
            if (is_array($entry) && ($entry[2] ?? null) === self::LIBRARY) {
                $has_library = true;
                break;
            }
        }

        $groups = [
            self::LIBRARY => [],
            $native => [],
            $new => [],
            self::ORGANIZE => [],
        ];
        $additional = [];

        foreach ($entries as $entry) {
            // Don't reinterpret the entry: WordPress keeps title, capability,
            // route and callback in its original submenu data.
            $slug = is_array($entry) && isset($entry[2]) && is_string($entry[2])
                ? $entry[2] : '';

            // The former AI shortcut is only accessible through Base Settings Hub.
            // Do not reintroduce its visible row if old menu providers are present.
            if ($slug === 'cb-notes-ai') {
                continue;
            } elseif ($has_library && $slug === $native) {
                // Library is canonical. Do not mutate or redirect the native
                // screen; simply omit its duplicate visible submenu item.
                continue;
            } elseif (array_key_exists($slug, $groups)) {
                $groups[$slug][] = $entry;
            } else {
                $additional[] = $entry;
            }
        }

        return array_values(array_merge(
            $groups[self::LIBRARY],
            $groups[$native],
            $groups[$new],
            $groups[self::ORGANIZE],
            $additional
        ));
    }
}

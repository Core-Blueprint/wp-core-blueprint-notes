<?php
// WP-CLI eval-file executes this fixture through eval(); do not use a
// strict_types declaration here, because WP-CLI can prepend statements.

/**
 * P4 synthetic Site A -> Site B installed-WordPress portability checks.
 *
 * Invoked only with "wp eval-file" by the gated Notes P4 runner.
 * This is a first-party test fixture, never shipped in the Notes plugin ZIP.
 * It intentionally uses the Notes internal import adapter while the public
 * Direct Base import without an owner session must be refused even when
 * the provider advertises import. This does NOT test confirmed HTTP apply.
 */

use CB\Notes\Library\Markers;
use CB\Notes\Organization\Catalog;
use CB\Notes\Plugin;
use CB\Notes\Portability\ArchivePreflight;
use CB\Notes\Portability\ImportJournal;
use CB\Notes\Portability\ImportSession;
use CB\Notes\Portability\NotesEntity;
use CB\Notes\Portability\OwnerExport;
use CoreBlueprint\Core\DataExchange\Engine;
use CoreBlueprint\Core\DataExchange\Foundation;

function cbp4_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Notes P4 assertion failed: ' . $message);
    }
}
function cbp4_user(string $login, bool $create): int {
    $user = get_user_by('login', $login);
    if ($create) {
        cbp4_assert(!$user, 'fresh site must not already contain ' . $login);
        $id = wp_insert_user([
            'user_login' => $login,
            'user_pass' => bin2hex(random_bytes(24)),
            'user_email' => $login . '@example.invalid',
            'role' => 'editor',
        ]);
        cbp4_assert(is_int($id) && $id > 0, 'create fixture owner ' . $login);
        return $id;
    }
    cbp4_assert($user instanceof WP_User && $user->ID > 0, 'missing fixture owner ' . $login);
    return (int) $user->ID;
}
function cbp4_read(string $path): array {
    cbp4_assert(is_file($path) && !is_link($path), 'runner-owned JSON input is missing');
    $bytes = file_get_contents($path);
    cbp4_assert(is_string($bytes) && strlen($bytes) > 0 && strlen($bytes) <= 10 * 1024 * 1024,
        'JSON input must be nonempty and bounded');
    $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
    cbp4_assert(is_array($data) && isset($data['records']), 'invalid JSON transport envelope');
    cbp4_assert(is_array(ArchivePreflight::inspect($data['records'])), 'complete source graph valid');
    return [$bytes, $data];
}
function cbp4_save(string $path, string $bytes): void {
    cbp4_assert(!file_exists($path) && !is_link($path), 'never overwrite an existing archive');
    cbp4_assert(strlen($bytes) > 0 && strlen($bytes) <= 10 * 1024 * 1024,
        'archive must remain bounded');
    cbp4_assert(file_put_contents($path, $bytes, LOCK_EX) === strlen($bytes),
        'cannot save runner-private test archive');
    @chmod($path, 0600);
}
function cbp4_owner_posts(int $owner): array {
    return get_posts([
        'post_type' => Plugin::TYPE, 'post_status' => ['draft', 'private', 'pending', 'future', 'publish'],
        'author' => $owner, 'posts_per_page' => 100, 'fields' => 'ids',
        'suppress_filters' => true,
    ]);
}
function cbp4_note(int $owner, string $title, string $blocks, string $status,
    string $created, string $modified, array $meta): int {
    $id = wp_insert_post([
        'post_type' => Plugin::TYPE, 'post_author' => $owner,
        'post_title' => $title, 'post_content' => $blocks, 'post_status' => $status,
        'post_date' => $created, 'post_date_gmt' => $created,
        'meta_input' => $meta,
    ], true);
    cbp4_assert(is_int($id) && $id > 0, 'WordPress accepted synthetic Note');
    // Seed intentionally DIFFERENT creation and modification times. The
    // import code itself must preserve both; do not adjust target values.
    global $wpdb;
    $changed = $wpdb->update($wpdb->posts, [
        'post_date' => $created, 'post_date_gmt' => $created,
        'post_modified' => $modified, 'post_modified_gmt' => $modified,
    ], ['ID' => $id], ['%s', '%s', '%s', '%s'], ['%d']);
    cbp4_assert($changed === 1, 'synthetic source timestamps persisted');
    clean_post_cache($id);
    $post = get_post($id);
    cbp4_assert($post instanceof WP_Post && $post->post_date_gmt === $created
        && $post->post_modified_gmt === $modified, 'timestamp fixture verified');
    return $id;
}
function cbp4_seed(string $json_path, string $zip_path): void {
    Plugin::grant_roles();
    $owner = cbp4_user('cb_notes_p4_source_owner', true);
    $other = cbp4_user('cb_notes_p4_source_foreign', true);
    wp_set_current_user($owner);
    $nb = str_repeat('a', 32);
    $cat = str_repeat('b', 32);
    $tag = str_repeat('c', 32);
    $catalog = [
        'notebook' => [['id' => $nb, 'name' => 'Planning & regie']],
        'category' => [['id' => $cat, 'name' => 'Governance']],
        'tag' => [['id' => $tag, 'name' => 'Privacy']],
    ];
    cbp4_assert(update_user_meta($owner, Catalog::USER_META, $catalog) !== false,
        'source catalog persisted');
    $first = cbp4_note($owner, 'Migratie & eigenaarschap',
        '<!-- wp:paragraph --><p>Privacy &amp; eigen regie</p><!-- /wp:paragraph -->',
        'private', '2024-04-11 10:00:00', '2024-04-12 13:14:15',
        [Catalog::NOTEBOOK => $nb, Catalog::CATEGORY => $cat, Catalog::TAGS => [$tag],
            Markers::FAVORITE => '1', Markers::PINNED => '1']);
    cbp4_note($owner, 'Concept evaluatie',
        '<!-- wp:heading --><h2>Voorbereiding</h2><!-- /wp:heading -->',
        'draft', '2025-07-09 08:00:00', '2025-07-10 17:31:20',
        [Catalog::NOTEBOOK => '', Catalog::CATEGORY => '', Catalog::TAGS => []]);
    wp_set_current_user($other);
    cbp4_note($other, 'FOREIGN-P4-SECRET',
        '<!-- wp:paragraph --><p>NEVER-EXPORT-FOREIGN</p><!-- /wp:paragraph -->',
        'private', '2024-05-01 10:00:00', '2024-05-02 10:00:00', []);
    wp_set_current_user($owner);
    $json = OwnerExport::json();
    cbp4_assert(is_string($json), 'Base owner JSON export succeeds');
    $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    cbp4_assert(count($decoded['records']) === 3, 'source exports catalog and exactly two own notes');
    cbp4_assert(strpos($json, 'FOREIGN-P4-SECRET') === false, 'foreign author omitted');
    cbp4_assert($decoded['records'][1]['reference'] === 'note:' . $first, 'stable first source ref');
    cbp4_assert($decoded['records'][1]['modified_gmt'] !== $decoded['records'][1]['created_gmt'],
        'nonidentical timestamps actually exported');
    cbp4_save($json_path, $json);
    $zip = OwnerExport::markdown_zip();
    cbp4_assert(is_string($zip), 'owner Markdown ZIP exports successfully');
    cbp4_save($zip_path, $zip);
    $archive = new ZipArchive();
    cbp4_assert($archive->open($zip_path) === true, 'Markdown ZIP opens');
    try {
        cbp4_assert($archive->getFromName('sources/note-' . $first . '.gutenberg.txt')
            === $decoded['records'][1]['content'], 'Gutenberg raw sidecar exactly preserved');
        $manifest = json_decode((string)$archive->getFromName('manifest.json'), true, 32, JSON_THROW_ON_ERROR);
        cbp4_assert(($manifest['note_count'] ?? null) === 2, 'Markdown manifest includes two Notes');
    } finally {
        $archive->close();
    }
    WP_CLI::success('P4 Site A: independent owner JSON/Markdown export and timestamps verified');
}
function cbp4_prepare_target(): void {
    Plugin::grant_roles();
    $owner = cbp4_user('cb_notes_p4_target_owner', true);
    $other = cbp4_user('cb_notes_p4_target_foreign', true);
    wp_set_current_user($other);
    cbp4_note($other, 'FOREIGN-TARGET-SECRET',
        '<!-- wp:paragraph --><p>foreign target note</p><!-- /wp:paragraph -->',
        'private', '2024-06-01 10:00:00', '2024-06-02 10:00:00', []);
    wp_set_current_user($owner);
    cbp4_assert(count(cbp4_owner_posts($owner)) === 0, 'target starts empty');
    cbp4_assert(Catalog::catalog($owner) === ['notebook' => [], 'category' => [], 'tag' => []],
        'fresh destination label catalog');
    WP_CLI::success('P4 Site B: independent target owner/foreign owner prepared');
}
function cbp4_transfer(string $path, bool $replay): void {
    [$bytes, $envelope] = cbp4_read($path);
    $owner = cbp4_user('cb_notes_p4_target_owner', false);
    wp_set_current_user($owner);
    cbp4_assert(is_wp_error(Engine::preview_json($bytes, Foundation::MODE_CREATE_ONLY)),
        'unauthorized direct Base import must still fail closed');
    $count_before = count(cbp4_owner_posts($owner));
    cbp4_assert($count_before === ($replay ? 2 : 0), 'destination has expected pre-import count');
    if (!$replay) {
        $altered = $envelope;
        $altered['records'][1]['notebook_id'] = str_repeat('f', 32);
        $bad = json_encode($altered, JSON_THROW_ON_ERROR);
        $invoked = false;
        $bad_plan = ImportSession::with_validated_json($bad,
            static function () use (&$invoked): bool {
                $invoked = true;
                return true;
            });
        cbp4_assert(is_wp_error($bad_plan) && !$invoked, 'orphan reference denied before writes');
        $altered = $envelope;
        $altered['records'][1]['share_grant'] = 'forged';
        cbp4_assert(is_wp_error(ImportSession::with_validated_json(
            json_encode($altered, JSON_THROW_ON_ERROR), static fn(): bool => true
        )), 'sharing authority injection denied');
    }
    $entity = new NotesEntity();
    $result = ImportSession::with_validated_json($bytes, static function (array $context) use ($entity, $envelope, $replay): bool {
        cbp4_assert($entity->can_import($context), 'in-request capability attested');
        foreach ($envelope['records'] as $record) {
            $plan = $entity->plan_import($record, Foundation::MODE_CREATE_ONLY, 1, $context);
            cbp4_assert(is_array($plan), 'Base-compatible record plan is valid');
            cbp4_assert($plan['operation'] === ($replay ? Foundation::OP_SKIP : Foundation::OP_CREATE),
                'destination plan is create on first pass, skip on verified replay');
            if ($plan['operation'] === Foundation::OP_SKIP) {
                continue;
            }
            $done = $entity->apply_import($plan, $context);
            cbp4_assert(is_array($done) && ($done['reference'] ?? null) === $record['reference'],
                'real WordPress record persisted');
        }
        return true;
    });
    cbp4_assert($result === true && !$entity->can_import(), 'import ticket dropped after request');
    cbp4_assert(count(cbp4_owner_posts($owner)) === 2, 'no missing or duplicated notes');
    $checked = ArchivePreflight::inspect($envelope['records']);
    cbp4_assert(is_array($checked), 'source digest verified');
    foreach ($checked['notes'] as $note) {
        $receipt = ImportJournal::receipt($owner, $checked['digest'], $note['reference']);
        cbp4_assert(is_array($receipt) && $receipt['state'] === 'complete',
            'persisted journal resolves verified destination post');
    }
    WP_CLI::success('P4 Site B: ' . ($replay ? 'replay SKIP / journal' : 'real import / new IDs') . ' verified');
}
function cbp4_normalized(array $records): array {
    $catalog = $records[0]['catalog'];
    $names = [];
    foreach (['notebook', 'category', 'tag'] as $kind) {
        $names[$kind] = [];
        foreach ($catalog[$kind] as $item) {
            $names[$kind][$item['id']] = $item['name'];
        }
    }
    $result = [];
    foreach (array_slice($records, 1) as $note) {
        $result[] = [
            'title' => $note['title'],
            'content' => $note['content'],
            'status' => $note['status'] === 'draft' ? 'draft' : 'private',
            'created_gmt' => $note['created_gmt'],
            'modified_gmt' => $note['modified_gmt'],
            'notebook' => $note['notebook_id'] === '' ? '' : $names['notebook'][$note['notebook_id']],
            'category' => $note['category_id'] === '' ? '' : $names['category'][$note['category_id']],
            'tags' => array_map(static fn(string $id): string => $names['tag'][$id], $note['tag_ids']),
            'favorite' => $note['favorite'],
            'pinned' => $note['pinned'],
        ];
    }
    usort($result, static fn(array $a, array $b): int => strcmp($a['title'], $b['title']));
    return $result;
}
function cbp4_verify(string $source, string $destination): void {
    [, $origin] = cbp4_read($source);
    [, $target] = cbp4_read($destination);
    cbp4_assert(count($origin['records']) === 3 && count($target['records']) === 3,
        'site-to-site reexport includes two Notes and one catalog');
    $source_notes = cbp4_normalized($origin['records']);
    $target_notes = cbp4_normalized($target['records']);
    if ($source_notes !== $target_notes) {
        // Only fixed schema field names are surfaced, never Note titles,
        // body blocks, catalog labels or private values.
        $changed_fields = [];
        foreach ($source_notes as $index => $note) {
            foreach ($note as $field => $value) {
                if (($target_notes[$index][$field] ?? null) !== $value) {
                    $changed_fields[$field] = true;
                }
            }
        }
        cbp4_assert(false,
            'semantic round-trip mismatch (field names only: '
            . implode(', ', array_keys($changed_fields)) . ')');
    }
    foreach (['notebook', 'category', 'tag'] as $kind) {
        cbp4_assert($origin['records'][0]['catalog'][$kind][0]['id']
            !== $target['records'][0]['catalog'][$kind][0]['id'], 'opaque IDs remapped per installation');
    }
    $ids_origin = array_column(array_slice($origin['records'], 1), 'reference');
    $ids_target = array_column(array_slice($target['records'], 1), 'reference');
    cbp4_assert($ids_origin !== $ids_target, 'post references are local, not portable');
    WP_CLI::success('P4 Site A -> Site B: semantic JSON reexport / ID remap / time fidelity PASS');
}
function cbp4_target_export(string $path): void {
    $owner = cbp4_user('cb_notes_p4_target_owner', false);
    wp_set_current_user($owner);
    $json = OwnerExport::json();
    cbp4_assert(is_string($json), 'target Base JSON export');
    cbp4_assert(strpos($json, 'FOREIGN-TARGET-SECRET') === false,
        'reexport cannot include other target owner');
    cbp4_assert(strpos($json, 'share_grant') === false, 'sharing grants cannot be exported');
    cbp4_save($path, $json);
    WP_CLI::success('P4 Site B: owner-only JSON reexport');
}

// WP-CLI eval-file supplies positional parameters in its local $args variable.
// Declaring $args global here shadows those values and loses the phase.
if (!isset($args) || !is_array($args)) {
    throw new RuntimeException('Notes P4 requires WP-CLI eval-file positional arguments.');
}
$mode = (string)($args[0] ?? '');
$first = (string)($args[1] ?? '');
$second = (string)($args[2] ?? '');
switch ($mode) {
    case 'source':
        cbp4_seed($first, $second);
        break;
    case 'target-init':
        cbp4_prepare_target();
        break;
    case 'import':
        cbp4_transfer($first, false);
        break;
    case 'replay':
        cbp4_transfer($first, true);
        break;
    case 'export-target':
        cbp4_target_export($first);
        break;
    case 'verify':
        cbp4_verify($first, $second);
        break;
    default:
        throw new InvalidArgumentException('Notes P4 requires a named, runner-owned phase.');
}

<?php
declare(strict_types=1);

/** Pure, isolated epoch contract; never uses a WordPress or production secret. */
define('ABSPATH', __DIR__ . '/');
$mode = $argv[1] ?? '';
if (!in_array($mode, ['issue', 'verify', 'missing'], true)) {
    exit(2);
}
$keyVariant = getenv('CB_EPOCH_TEST_VARIANT') ?: '';
if ($mode !== 'missing') {
    if (!in_array($keyVariant, ['a', 'b'], true)) exit(2);
    define('CB_NOTES_SHARE_EPOCH_KEY', str_repeat($keyVariant, 64));
}
function home_url(string $path = ''): string {
    return rtrim(getenv('CB_EPOCH_TEST_SITE') ?: 'https://notes-epoch.invalid/', '/') . '/' . ltrim($path, '/');
}
function wp_json_encode(mixed $value, int $options = 0): string|false {
    return json_encode($value, $options);
}
require dirname(__DIR__) . '/src/Sharing/PublicShareGuard.php';
$guard = \CB\Notes\Sharing\PublicShareGuard::class;
$synthetic = [
    'key' => str_repeat('a', 32),
    'title' => 'Synthetic test note',
    'html' => '<p>Synthetic fixture only</p>',
    'modified' => '2026-10-14 00:00:00',
    'created' => '2026-10-14T00:00:00+00:00',
];
if ($mode === 'missing') {
    if ($guard::ready() || $guard::sign(12, $synthetic) !== false ||
        $guard::verify(12, $synthetic)) {
        throw new RuntimeException('Missing external epoch must deny all public shares');
    }
    echo 'MISSING_DENIED';
    exit(0);
}
$received = (string) getenv('CB_EPOCH_TEST_SNAPSHOT');
if ($mode === 'issue') {
    if (!$guard::ready()) throw new RuntimeException('Test key not ready');
    $signed = $guard::sign(12, $synthetic);
    if (!is_array($signed) || !$guard::verify(12, $signed)) {
        throw new RuntimeException('Signed synthetic share must be valid for current epoch');
    }
    echo json_encode($signed, JSON_THROW_ON_ERROR);
    exit(0);
}
$decoded = json_decode($received, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($decoded)) throw new RuntimeException('Bad synthetic input');
echo $guard::verify(12, $decoded) ? 'ALLOWED' : 'DENIED';

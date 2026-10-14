<?php
declare(strict_types=1);

// Disposable guarded rollback HTTP test only; never a production router.
$root = realpath((string)getenv('CB_NOTES_ROLLBACK_HTTP_SITE'));
if ($root === false || !str_starts_with($root, '/tmp/core-blueprint-tests/core-blueprint-notes-rollback/run.')
    || !str_ends_with($root, '/target')) {
    http_response_code(403);
    exit('Only disposable recovered target is routable');
}
$uri = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_contains($uri, '..')) {
    http_response_code(403);
    exit;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require $root . '/index.php';

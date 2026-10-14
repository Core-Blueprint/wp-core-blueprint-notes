<?php
declare(strict_types=1);

// Local PHP development server router; test root is never a public vhost.
$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$root = realpath(getenv('CB_NOTES_HTTP_SITE') ?: '');
$path = $root === false ? false : realpath($root . '/' . ltrim($uri, '/'));
if ($root === false || !str_contains($root, '/core-blueprint-notes-http/run.')) {
    http_response_code(403);
    exit('Invalid disposable site root');
}
// WordPress static assets only, never return wp-config or dotfiles directly.
if ($uri !== '' && $uri !== '/'
    && !str_contains($uri, '..')
    && !str_starts_with(basename($uri), '.')
    && str_starts_with($uri, '/wp-content/')
    && $path !== false && str_starts_with($path, $root . '/')
    && is_file($path)
    && !str_ends_with($path, '.php')) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require $root . '/index.php';

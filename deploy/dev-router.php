<?php
/**
 * Local preview only (PHP's built-in server ignores .htaccess):
 *   php -S localhost:8000 deploy/dev-router.php
 * Preview the PHP site (from ./content) instead of the old .html files:
 *   JAIBABA_ENABLED=1 JAIBABA_PREVIEW_PHP=1 php -S localhost:8000 deploy/dev-router.php
 * Mimics the .htaccess rules: private folders blocked, real files served as-is,
 * everything else goes to index.php.
 */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$root = dirname(__DIR__);

if (preg_match('#^/(app|templates|content|tests|scripts|deploy|\.git|\.claude|\.vscode)(/|$)#', $path)
    || preg_match('#^/admin/_#', $path)
    || preg_match('#(^|/)\.(?!well-known/)#', $path)
    || preg_match('#\.(md|py|sh|sql|yml|yaml|lock|log|json|bak|tar|gz|zip)$#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

// JAIBABA_PREVIEW_PHP=1 (with JAIBABA_ENABLED=1): ignore the old .html files and
// show every page as the PHP site renders it, exactly as after go-live.
// A folder with its own index.php (the dashboard at /admin/) is served like Apache would.
if (substr($path, -1) === '/' && $path !== '/' && is_file($root . $path . 'index.php')) {
    $_SERVER['SCRIPT_NAME'] = $path . 'index.php';
    chdir($root . $path);
    require $root . $path . 'index.php';
    return true;
}

$phpPreview = getenv('JAIBABA_PREVIEW_PHP') === '1';
if ($phpPreview && ($path === '/' || preg_match('#\.html$#', $path) || substr($path, -1) === '/')) {
    require $root . '/index.php';
    return true;
}

if ($path === '/' && is_file($root . '/index.html')) {
    return false; // DirectoryIndex index.html wins while it exists
}
if ($path !== '/' && is_file($root . $path)) {
    return false; // real file: let the built-in server send it
}
require $root . '/index.php';

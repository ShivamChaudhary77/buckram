<?php
/**
 * Local preview only (PHP's built-in server ignores .htaccess):
 *   php -S localhost:8000 deploy/dev-router.php
 * Mimics the .htaccess rules: private folders blocked, real files served as-is,
 * everything else goes to index.php.
 */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$root = dirname(__DIR__);

if (preg_match('#^/(app|templates|content|tests|scripts|deploy|\.git|\.claude|\.vscode)(/|$)#', $path)
    || preg_match('#(^|/)\.(?!well-known/)#', $path)
    || preg_match('#\.(md|py|sh|sql|yml|yaml|lock|log|json|bak|tar|gz|zip)$#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

if ($path === '/' && is_file($root . '/index.html')) {
    return false; // DirectoryIndex index.html wins while it exists
}
if ($path !== '/' && is_file($root . $path)) {
    return false; // real file: let the built-in server send it
}
require $root . '/index.php';

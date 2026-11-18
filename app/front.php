<?php
/**
 * Front controller: turns a request into a response array
 *   ['status' => int, 'headers' => [name => value], 'body' => string]
 * so it can be tested without a web server. front_emit() sends it.
 *
 * Order: method check → feature switch → address clean-up (301) → special files
 * → dashboard redirects (301/410) → published page → branded 404.
 *
 * Real files (CSS, JS, images, /php/contact-form.php, /admin) never get here:
 * the web server serves them directly.
 */

function response(int $status, string $body = '', array $headers = []): array
{
    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}

function redirect_response(string $to, int $code = 301): array
{
    return response($code, '', ['Location' => $to, 'Cache-Control' => 'public, max-age=3600']);
}

function front_dispatch(string $method, string $uri): array
{
    $method = strtoupper($method);
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return response(405, 'Method not allowed', ['Allow' => 'GET, HEAD', 'Content-Type' => 'text/plain; charset=utf-8']);
    }
    $resp = front_route($uri);
    if ($method === 'HEAD') {
        $resp['body'] = '';
    }
    return $resp;
}

function front_route(string $uri): array
{
    $rawPath = (string) parse_url($uri, PHP_URL_PATH);
    $query = (string) parse_url($uri, PHP_URL_QUERY);
    $path = rawurldecode($rawPath === '' ? '/' : $rawPath);

    if (strpos($path, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $path)) {
        return response(400, 'Bad request', ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    if (!config('enabled')) {
        return front_static_fallback($path);
    }

    // 1. Clean-up: one official form for every address.
    $clean = strtolower((string) preg_replace('#/+#', '/', $path));
    if (strlen($clean) > 1 && substr($clean, -6) === '.html/') {
        $clean = rtrim($clean, '/');
    }
    if ($clean === '/index.html' && config('home_path') === '/') {
        $clean = '/';
    }
    if ($clean !== $path) {
        return redirect_response($clean . ($query !== '' ? '?' . $query : ''));
    }

    // 2. Generated files (sitemap.xml, robots.txt, llms.txt) – added in Phase 5.
    $special = front_special_route($path);
    if ($special !== null) {
        return $special;
    }

    // 3. Redirects managed in the dashboard.
    $r = redirect_find($path);
    if ($r) {
        if ((int) $r['code'] === 410) {
            return front_html(410, render_not_found(), false);
        }
        return redirect_response((string) $r['to'], 301);
    }

    // 4. A published page.
    $lookup = $path === '/' ? '/index.html' : $path;
    $page = page_find_by_path($lookup);
    if ($page) {
        return front_page($path, $page);
    }

    // "/about" or "/about/" → "/about.html" when that page exists.
    $trimmed = rtrim($path, '/');
    if ($trimmed !== '' && substr($trimmed, -5) !== '.html' && page_find_by_path($trimmed . '.html')) {
        return redirect_response($trimmed . '.html');
    }

    // 5. Not found (a real 404, never a "soft" 200).
    return front_html(404, render_not_found(), false);
}

/** Hook for generated files. Returns a response or null. */
function front_special_route(string $path): ?array
{
    return null;
}

function front_page(string $path, array $page): array
{
    $html = cache_get($path);
    if ($html === null) {
        $html = render_page($page);
        cache_put($path, $html);
    }
    $resp = front_html(200, $html, true);
    $updated = strtotime((string) ($page['updated'] ?? '')) ?: null;
    if (!$updated && valid_id($page['id'] ?? null) && is_file(page_file($page['id']))) {
        $updated = filemtime(page_file($page['id'])) ?: null;
    }
    if ($updated) {
        $resp['headers']['Last-Modified'] = gmdate('D, d M Y H:i:s', $updated) . ' GMT';
    }
    $etag = '"' . substr(sha1($html), 0, 20) . '"';
    $resp['headers']['ETag'] = $etag;
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        return response(304, '', ['ETag' => $etag]);
    }
    return $resp;
}

function front_html(int $status, string $html, bool $cacheable): array
{
    return response($status, $html, [
        'Content-Type'  => 'text/html; charset=utf-8',
        'Cache-Control' => $cacheable ? 'public, max-age=300' : 'no-cache',
    ]);
}

/**
 * Before go-live: serve the old static file if the web server handed us the
 * request anyway (for example "/" when index.php is first in DirectoryIndex).
 */
function front_static_fallback(string $path): array
{
    if (config('static_fallback')) {
        $rel = $path === '/' || substr($path, -1) === '/' ? rtrim($path, '/') . '/index.html' : $path;
        if (preg_match('#^(/[a-z0-9_-]+)*/[a-z0-9_-]+\.html$#i', $rel) && !preg_match('#^/(includes|templates|admin|app|content)/#i', $rel)) {
            $file = realpath(ROOT_DIR . $rel);
            $root = realpath(ROOT_DIR);
            if ($file && $root && strpos($file, $root . DIRECTORY_SEPARATOR) === 0 && is_file($file)) {
                return front_html(200, (string) file_get_contents($file), true);
            }
        }
    }
    $notFound = ROOT_DIR . '/404.html';
    $body = is_file($notFound) ? (string) file_get_contents($notFound) : '<!DOCTYPE html><title>Page not found</title><h1>Page not found</h1>';
    return front_html(404, $body, false);
}

function front_emit(array $resp): void
{
    http_response_code($resp['status']);
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    foreach ($resp['headers'] as $name => $value) {
        header($name . ': ' . $value);
    }
    echo $resp['body'];
}

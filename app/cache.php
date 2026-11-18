<?php
/**
 * Rendered-page cache: one HTML file per web address in <content>/_cache/pages/.
 * Cleared completely on every save (pages, settings, redirects). Never used for
 * the dashboard or previews.
 */

function cache_dir(): string
{
    return content_path('_cache', 'pages');
}

function cache_file(string $path): string
{
    return cache_dir() . '/' . sha1($path) . '.html';
}

function cache_get(string $path): ?string
{
    if (!config('cache')) {
        return null;
    }
    $file = cache_file($path);
    if (!is_file($file)) {
        return null;
    }
    $html = @file_get_contents($file);
    return $html === false ? null : $html;
}

function cache_put(string $path, string $html): void
{
    if (!config('cache')) {
        return;
    }
    try {
        file_write_atomic(cache_file($path), $html);
    } catch (RuntimeException $e) {
        error_log('Page cache write failed: ' . $e->getMessage()); // the page still renders
    }
}

function cache_clear(): void
{
    rm_tree(content_path('_cache'));
}

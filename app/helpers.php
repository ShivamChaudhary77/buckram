<?php
/**
 * Small shared helpers: escaping, URLs, JSON files (atomic, locked), arrays.
 */

/** Escape for HTML text and attributes. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Turn a site path into a full URL ("/about.html" → "https://www…/about.html"). Full URLs pass through. */
function absolute_url(string $path): string
{
    if ($path === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)) {
        return $path;
    }
    return config('base_url') . '/' . ltrim($path, '/');
}

/** Page ids are file names: lowercase letters, digits and dashes only. */
function valid_id($id): bool
{
    return is_string($id) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) === 1 && strlen($id) <= 100;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = str_replace('&', ' and ', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-');
}

/** ISO 8601 time with milliseconds, so two saves in the same second still differ. */
function now_iso(): string
{
    return (new DateTime())->format('Y-m-d\TH:i:s.vP');
}

/** Path inside the content folder. */
function content_path(string ...$parts): string
{
    $base = (string) config('content_dir');
    return $parts ? $base . '/' . implode('/', $parts) : $base;
}

/** Read "a.b.c" from a nested array. */
function array_get($array, string $path, $default = null)
{
    $cur = $array;
    foreach (explode('.', $path) as $key) {
        if (!is_array($cur) || !array_key_exists($key, $cur)) {
            return $default;
        }
        $cur = $cur[$key];
    }
    return $cur;
}

/** Set "a.b.c" in a nested array. */
function array_set(array &$array, string $path, $value): void
{
    $cur = &$array;
    foreach (explode('.', $path) as $key) {
        if (!isset($cur[$key]) || !is_array($cur[$key])) {
            $cur[$key] = [];
        }
        $cur = &$cur[$key];
    }
    $cur = $value;
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Could not create folder: $dir");
    }
}

/**
 * Read a JSON file. Returns $default when missing. Throws when the file is corrupt,
 * so a broken file is never silently treated as empty (and then overwritten).
 */
function json_read(string $file, $default = null)
{
    if (!is_file($file)) {
        return $default;
    }
    $fh = fopen($file, 'rb');
    if ($fh === false) {
        throw new RuntimeException("Could not open $file");
    }
    try {
        flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
    } finally {
        fclose($fh);
    }
    if ($raw === '' || $raw === false) {
        return $default;
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Broken JSON in $file: " . json_last_error_msg());
    }
    return $data;
}

/**
 * Write JSON atomically: write a temp file next to it, then rename over the old one,
 * while holding an exclusive lock on "<file>.lock". A crash never leaves half a file.
 */
function json_write(string $file, $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Could not encode JSON: ' . json_last_error_msg());
    }
    file_write_atomic($file, $json . "\n");
}

function file_write_atomic(string $file, string $contents): void
{
    ensure_dir(dirname($file));
    // One lock file per folder (kept, never deleted, so the lock is always the same file).
    $lock = fopen(dirname($file) . '/.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException("Could not lock $file");
    }
    try {
        flock($lock, LOCK_EX);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $contents) === false) {
            throw new RuntimeException("Could not write $tmp");
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException("Could not save $file");
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Delete a folder and everything in it (used for cache and tests). */
function rm_tree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

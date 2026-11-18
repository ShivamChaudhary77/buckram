<?php
/**
 * Content store: pages (one JSON file each), site settings, URL index, history,
 * trash and redirects. Every write goes through here so validation, history and
 * cache clearing can't be skipped.
 *
 * Layout inside the content folder:
 *   site.json               business details, menu, footer, defaults
 *   pages/<id>.json         one page each
 *   redirects.json          {"redirects":[{"from","to","code"}]}
 *   _index.json             path → id (rebuilt automatically)
 *   _history/<id>/<ts>.json last 30 versions of each page
 *   _trash/<id>__<ts>.json  deleted pages
 *   _cache/                 rendered HTML (see cache.php)
 */

const INDEX_VERSION = 1;
const HISTORY_KEEP = 30;
const PAGE_TYPES = ['home', 'product', 'listing', 'about', 'contact', 'page'];
const PAGE_STATUSES = ['draft', 'published'];
const RESERVED_PREFIXES = ['/admin', '/app', '/assets', '/content', '/includes', '/php', '/templates', '/tests', '/scripts', '/deploy', '/uploads'];

class ContentError extends RuntimeException
{
    /** @var string[] */
    public array $errors;

    public function __construct(array $errors)
    {
        $this->errors = array_values($errors);
        parent::__construct(implode(' ', $this->errors));
    }
}

/* ------------------------------------------------------------------ site */

function site_load(): array
{
    static $memo = [];
    $file = content_path('site.json');
    clearstatcache(true, $file);
    // Every save writes a new file (atomic rename), so the inode changes even within one second.
    $key = $file . '@' . (is_file($file) ? fileinode($file) . ':' . filemtime($file) . ':' . filesize($file) : '0');
    if (!isset($memo[$key])) {
        $memo = [$key => json_read($file, [])];
    }
    return $memo[$key];
}

function site_save(array $site): void
{
    json_write(content_path('site.json'), $site);
    clearstatcache();
    cache_clear();
}

/* ----------------------------------------------------------------- pages */

function page_file(string $id): string
{
    if (!valid_id($id)) {
        throw new ContentError(['Invalid page id.']);
    }
    return content_path('pages', $id . '.json');
}

function page_load(string $id): ?array
{
    if (!valid_id($id)) {
        return null;
    }
    return json_read(page_file($id));
}

/** All pages, keyed by id, sorted by path. */
function pages_all(): array
{
    $pages = [];
    foreach (glob(content_path('pages', '*.json')) ?: [] as $file) {
        $id = basename($file, '.json');
        if (!valid_id($id)) {
            continue;
        }
        $page = json_read($file);
        if (is_array($page)) {
            $pages[$id] = $page;
        }
    }
    uasort($pages, function ($a, $b) {
        return strcmp((string) ($a['path'] ?? ''), (string) ($b['path'] ?? ''));
    });
    return $pages;
}

function normalize_page_path(string $path): string
{
    $path = '/' . ltrim(trim($path), '/');
    $path = (string) preg_replace('#/+#', '/', $path);
    return strtolower($path);
}

function path_is_valid(string $path): bool
{
    return preg_match('#^/(?:[a-z0-9]+(?:-[a-z0-9]+)*/)*(?:[a-z0-9]+(?:-[a-z0-9]+)*\.html|[a-z0-9]+(?:-[a-z0-9]+)*/)$#', $path) === 1;
}

function path_is_reserved(string $path): bool
{
    foreach (RESERVED_PREFIXES as $prefix) {
        if ($path === $prefix || strpos($path, $prefix . '/') === 0 || strpos($path, $prefix . '.') === 0) {
            return true;
        }
    }
    return false;
}

/** Returns a list of problems (empty = valid). */
function page_validate(array $page, ?array $allPages = null): array
{
    $errors = [];
    $id = $page['id'] ?? null;
    if (!valid_id($id)) {
        $errors[] = 'The page id may only contain lowercase letters, numbers and dashes.';
    }
    if (!in_array($page['type'] ?? null, PAGE_TYPES, true)) {
        $errors[] = 'Unknown page type.';
    }
    if (!in_array($page['status'] ?? null, PAGE_STATUSES, true)) {
        $errors[] = 'Status must be draft or published.';
    }
    $path = (string) ($page['path'] ?? '');
    if (!path_is_valid($path)) {
        $errors[] = 'The web address must look like /name.html or /folder/name.html (lowercase letters, numbers and dashes).';
    } elseif (path_is_reserved($path)) {
        $errors[] = 'That web address is reserved by the system. Please choose another.';
    }
    if (trim((string) ($page['title'] ?? '')) === '') {
        $errors[] = 'The page needs a name.';
    }
    if (isset($page['blocks']) && !is_array($page['blocks'])) {
        $errors[] = 'Page sections are damaged.';
    }
    foreach (($page['blocks'] ?? []) as $i => $block) {
        if (!is_array($block) || !is_string($block['type'] ?? null) || !preg_match('/^[a-z0-9-]+$/', $block['type'])) {
            $errors[] = 'Section ' . ($i + 1) . ' is damaged.';
        }
    }
    if (!$errors) {
        $allPages = $allPages ?? pages_all();
        foreach ($allPages as $otherId => $other) {
            if ($otherId !== $id && ($other['path'] ?? null) === $path) {
                $errors[] = 'Another page ("' . ($other['title'] ?? $otherId) . '") already uses this web address.';
            }
        }
        foreach (redirects_load() as $r) {
            if ($r['from'] === $path) {
                $errors[] = 'This web address is currently redirected elsewhere. Remove that redirect first.';
            }
        }
    }
    return $errors;
}

/**
 * Validate and save a page. Keeps the previous version in history, rebuilds the
 * URL index and clears the cache. Returns the saved page.
 */
function page_save(array $page): array
{
    if (isset($page['path'])) {
        $page['path'] = normalize_page_path((string) $page['path']);
    }
    $page['blocks'] = $page['blocks'] ?? [];
    $errors = page_validate($page);
    if ($errors) {
        throw new ContentError($errors);
    }
    $file = page_file($page['id']);
    $old = json_read($file);
    if (is_array($old)) {
        history_add($old);
        $page['created'] = $old['created'] ?? $page['created'] ?? now_iso();
    } else {
        $page['created'] = $page['created'] ?? now_iso();
    }
    $page['updated'] = now_iso();
    json_write($file, $page);
    index_rebuild();
    cache_clear();
    return $page;
}

/**
 * Save a page edited in the dashboard. When a live page gets a new web address,
 * the old address is redirected (301) to the new one automatically, and older
 * redirects that pointed at the old address are updated so there are no chains.
 * Returns [savedPage, messages[]].
 */
function page_save_from_editor(array $page, ?array $old): array
{
    $messages = [];
    $newPath = normalize_page_path((string) ($page['path'] ?? ''));
    $oldPath = $old['path'] ?? null;
    $list = redirects_load();
    $changed = false;
    // A redirect from the new address would hide the page: drop it.
    $filtered = array_values(array_filter($list, function ($r) use ($newPath) {
        return $r['from'] !== $newPath;
    }));
    if (count($filtered) !== count($list)) {
        $list = $filtered;
        $changed = true;
    }
    if ($changed) {
        redirects_save($list);
    }
    $saved = page_save($page);
    if ($oldPath && $oldPath !== $saved['path'] && ($old['status'] ?? '') === 'published') {
        foreach ($list as &$r) {
            if ($r['code'] === 301 && $r['to'] === $oldPath) {
                $r['to'] = $saved['path'];
            }
        }
        unset($r);
        $list[] = ['from' => $oldPath, 'to' => $saved['path'], 'code' => 301];
        redirects_save($list);
        $messages[] = 'The old address ' . $oldPath . ' now sends visitors (and Google) to ' . $saved['path'] . '.';
    }
    return [$saved, $messages];
}

/* ------------------------------------------------------------- URL index */

function index_rebuild(): array
{
    $index = ['version' => INDEX_VERSION, 'built' => now_iso(), 'paths' => []];
    foreach (pages_all() as $id => $page) {
        if (isset($page['path'])) {
            $index['paths'][$page['path']] = ['id' => $id, 'status' => $page['status'] ?? 'draft'];
        }
    }
    json_write(content_path('_index.json'), $index);
    return $index;
}

function index_get(): array
{
    $index = null;
    try {
        $index = json_read(content_path('_index.json'));
    } catch (RuntimeException $e) {
        $index = null; // corrupt index: just rebuild it
    }
    if (!is_array($index) || ($index['version'] ?? 0) !== INDEX_VERSION) {
        $index = index_rebuild();
    }
    return $index;
}

/** Find a page by its web address. Drafts only when $includeDrafts. */
function page_find_by_path(string $path, bool $includeDrafts = false): ?array
{
    $entry = index_get()['paths'][$path] ?? null;
    if (!$entry) {
        return null;
    }
    if (!$includeDrafts && $entry['status'] !== 'published') {
        return null;
    }
    $page = page_load($entry['id']);
    if (!$page || ($page['path'] ?? null) !== $path) {
        // Index out of date (file edited by hand): rebuild once and retry.
        $entry = index_rebuild()['paths'][$path] ?? null;
        $page = $entry ? page_load($entry['id']) : null;
    }
    if (!$page || (!$includeDrafts && ($page['status'] ?? '') !== 'published')) {
        return null;
    }
    return $page;
}

/* --------------------------------------------------------------- history */

function history_add(array $page): void
{
    $id = $page['id'] ?? '';
    if (!valid_id($id)) {
        return;
    }
    $stamp = (new DateTime())->format('Ymd-His-u');
    json_write(content_path('_history', $id, $stamp . '.json'), $page);
    $files = history_files($id);
    foreach (array_slice($files, HISTORY_KEEP) as $old) {
        @unlink($old);
    }
}

/** History files, newest first. */
function history_files(string $id): array
{
    if (!valid_id($id)) {
        return [];
    }
    $files = glob(content_path('_history', $id, '*.json')) ?: [];
    rsort($files, SORT_STRING);
    return $files;
}

/** [['version' => '20261118-101500-123456', 'updated' => …, 'title' => …], …] newest first. */
function history_list(string $id): array
{
    $out = [];
    foreach (history_files($id) as $file) {
        $data = json_read($file, []);
        $out[] = [
            'version' => basename($file, '.json'),
            'updated' => $data['updated'] ?? null,
            'title'   => $data['title'] ?? '',
            'status'  => $data['status'] ?? '',
        ];
    }
    return $out;
}

function history_load(string $id, string $version): ?array
{
    if (!valid_id($id) || !preg_match('/^\d{8}-\d{6}-\d{6}$/', $version)) {
        return null;
    }
    return json_read(content_path('_history', $id, $version . '.json'));
}

/** Put an old version back. The current version goes into history first. */
function history_restore(string $id, string $version): array
{
    $old = history_load($id, $version);
    if (!$old) {
        throw new ContentError(['That version no longer exists.']);
    }
    $old['id'] = $id;
    return page_save($old);
}

/* ----------------------------------------------------------------- trash */

function page_trash(string $id): string
{
    $file = page_file($id);
    if (!is_file($file)) {
        throw new ContentError(['That page does not exist.']);
    }
    $name = $id . '__' . (new DateTime())->format('Ymd-His-u') . '.json';
    $dest = content_path('_trash', $name);
    ensure_dir(dirname($dest));
    if (!@rename($file, $dest)) {
        throw new ContentError(['Could not move the page to the trash.']);
    }
    index_rebuild();
    cache_clear();
    return $name;
}

function trash_list(): array
{
    $out = [];
    $files = glob(content_path('_trash', '*.json')) ?: [];
    rsort($files, SORT_STRING);
    foreach ($files as $file) {
        $data = json_read($file, []);
        $out[] = [
            'file'    => basename($file),
            'id'      => $data['id'] ?? '',
            'title'   => $data['title'] ?? '',
            'path'    => $data['path'] ?? '',
            'deleted' => date('c', (int) filemtime($file)),
        ];
    }
    return $out;
}

function trash_file(string $name): string
{
    if (!preg_match('/^[a-z0-9-]+__\d{8}-\d{6}-\d{6}\.json$/', $name)) {
        throw new ContentError(['Unknown trash item.']);
    }
    return content_path('_trash', $name);
}

function trash_restore(string $name): array
{
    $file = trash_file($name);
    $page = json_read($file);
    if (!is_array($page)) {
        throw new ContentError(['Unknown trash item.']);
    }
    if (is_file(page_file((string) $page['id']))) {
        throw new ContentError(['A page with the same id exists again. Rename or delete it first.']);
    }
    $saved = page_save($page);
    @unlink($file);
    return $saved;
}

function trash_delete(string $name): void
{
    $file = trash_file($name);
    if (is_file($file)) {
        unlink($file);
    }
}

function trash_empty(): int
{
    $n = 0;
    foreach (glob(content_path('_trash', '*.json')) ?: [] as $file) {
        $n += @unlink($file) ? 1 : 0;
    }
    return $n;
}

/* ------------------------------------------------------------- redirects */

function redirects_load(): array
{
    $data = json_read(content_path('redirects.json'), ['redirects' => []]);
    return array_values(array_filter($data['redirects'] ?? [], 'is_array'));
}

/** Returns problems with a redirect list (empty = valid). */
function redirects_validate(array $list): array
{
    $errors = [];
    $from = [];
    foreach ($list as $i => $r) {
        $n = $i + 1;
        $f = (string) ($r['from'] ?? '');
        $t = (string) ($r['to'] ?? '');
        $code = (int) ($r['code'] ?? 301);
        if ($f === '' || $f[0] !== '/' || preg_match('/\s/', $f)) {
            $errors[] = "Redirect $n: the old address must start with /.";
        }
        if (!in_array($code, [301, 410], true)) {
            $errors[] = "Redirect $n: type must be 301 (moved) or 410 (gone).";
        }
        if ($code === 301) {
            if ($t === '' || ($t[0] !== '/' && !preg_match('#^https?://#', $t))) {
                $errors[] = "Redirect $n: the new address must start with / or https://.";
            }
            if ($t === $f) {
                $errors[] = "Redirect $n points to itself.";
            }
        }
        if (isset($from[$f])) {
            $errors[] = "Redirect $n: the address $f is listed twice.";
        }
        $from[$f] = $code === 301 ? $t : null;
    }
    // Chains (A→B→C) and loops (A→B→A): a target may not itself be redirected.
    foreach ($from as $f => $t) {
        if ($t !== null && array_key_exists($t, $from)) {
            $errors[] = "Redirect from $f goes to $t, which is itself redirected. Point it straight at the final address.";
        }
    }
    return $errors;
}

function redirects_save(array $list): void
{
    $clean = [];
    foreach ($list as $r) {
        $code = (int) ($r['code'] ?? 301);
        $clean[] = [
            'from'    => trim((string) ($r['from'] ?? '')),
            'to'      => $code === 410 ? '' : trim((string) ($r['to'] ?? '')),
            'code'    => $code,
            'created' => $r['created'] ?? now_iso(),
        ];
    }
    $errors = redirects_validate($clean);
    $paths = index_get()['paths'];
    foreach ($clean as $r) {
        if (isset($paths[$r['from']]) && $paths[$r['from']]['status'] === 'published') {
            $errors[] = 'The address ' . $r['from'] . ' belongs to a live page, so it cannot be redirected.';
        }
    }
    if ($errors) {
        throw new ContentError($errors);
    }
    json_write(content_path('redirects.json'), ['redirects' => $clean]);
    cache_clear();
}

function redirect_find(string $path): ?array
{
    foreach (redirects_load() as $r) {
        if (($r['from'] ?? null) === $path) {
            return $r;
        }
    }
    return null;
}

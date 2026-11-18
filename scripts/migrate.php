<?php
/**
 * Migration: static HTML site → content/ (site.json + pages/*.json).
 *
 *   php scripts/migrate.php              write the snapshot to ./content
 *   php scripts/migrate.php --out=DIR    write somewhere else
 *   php scripts/migrate.php --dry-run    report only, write nothing
 *   php scripts/migrate.php --verbose    explain why a section stayed as HTML
 *
 * Safe to re-run until go-live: it rebuilds the snapshot from the .html files each
 * time (pages it generates are overwritten; history, trash and redirects are left alone).
 * DO NOT run it after go-live – the dashboard is then the only source of truth.
 *
 * Fidelity rule: every section is first converted into editable fields, rendered
 * back through the real templates and compared with the original. Only an exact
 * match is kept; anything else is stored as an "html" section, unchanged.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/lib/html-compare.php';
require __DIR__ . '/lib/migrate-lib.php';

$opts = getopt('', ['out:', 'dry-run', 'verbose', 'force']);

// After go-live the dashboard is the only source of truth: re-migrating would throw away edits.
if (config('enabled') && !isset($opts['dry-run']) && !isset($opts['force'])) {
    fwrite(STDERR, "The new website is switched on (app/config.local.php: enabled => true).\n"
        . "Running the migration now would replace pages edited in the dashboard with the old .html files.\n"
        . "Nothing was changed. (Use --dry-run to only look, or --force if you really mean it.)\n");
    exit(1);
}
$out = rtrim((string) ($opts['out'] ?? ROOT_DIR . '/content'), '/');
$dry = isset($opts['dry-run']);
$verbose = isset($opts['verbose']);

// Work in a scratch folder, then copy into place, so a failed run never leaves a half snapshot.
$work = sys_get_temp_dir() . '/jaibaba-migrate-' . bin2hex(random_bytes(4));
ensure_dir($work . '/pages');
config_set(['content_dir' => $work, 'cache' => false, 'enabled' => true]);

try {
    $report = migrate_site(ROOT_DIR, $work, $verbose);
} catch (Throwable $t) {
    rm_tree($work);
    fwrite(STDERR, 'Migration failed: ' . $t->getMessage() . "\n");
    exit(1);
}

echo $report['text'];

if ($dry) {
    echo "\nDry run: nothing written.\n";
    rm_tree($work);
    exit($report['problems'] ? 1 : 0);
}

ensure_dir($out . '/pages');
// Remove pages a previous run generated that no longer exist as .html files.
foreach (glob($out . '/pages/*.json') ?: [] as $old) {
    $data = json_decode((string) file_get_contents($old), true);
    if (($data['source'] ?? null) && !is_file($work . '/pages/' . basename($old))) {
        unlink($old);
        echo "Removed stale page " . basename($old) . "\n";
    }
}
copy($work . '/site.json', $out . '/site.json');
foreach (glob($work . '/pages/*.json') as $f) {
    copy($f, $out . '/pages/' . basename($f));
}
config_set(['content_dir' => $out]);
index_rebuild();
cache_clear();
rm_tree($work);
echo "\nWrote " . count($report['pages']) . " pages + site.json to $out\n";
exit($report['problems'] ? 1 : 0);

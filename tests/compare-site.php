<?php
/**
 * Parity test: every static .html page vs the same address served by the PHP site.
 *
 *   php tests/compare-site.php                 compare against ./content
 *   php tests/compare-site.php --content=DIR   compare against another content folder
 *
 * Must end with "all N pages identical" before go-live. After go-live the static
 * files are gone; use tests/verify-live.php instead.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT_DIR . '/scripts/lib/parity.php';

$opts = getopt('', ['content:']);
config_set(['enabled' => true, 'cache' => false] + (isset($opts['content']) ? ['content_dir' => $opts['content']] : []));

if (!is_file(ROOT_DIR . '/index.html')) {
    echo "The static .html files are gone (site is live). Run: php tests/verify-live.php https://www.jaibabainterlinings.com\n";
    exit(0);
}

set_error_handler(function ($no, $msg, $file, $line) {
    throw new ErrorException($msg, 0, $no, $file, $line);
});

$r = parity_run(ROOT_DIR);
foreach ($r['pages'] as $path => $diffs) {
    $accepted = $diffs['_accepted'] ?? [];
    unset($diffs['_accepted']);
    echo ($diffs ? 'DIFF ' : 'same ') . $path . "\n";
    foreach ($diffs as $check => $desc) {
        echo "  ✗ $check\n        $desc\n";
    }
    foreach ($accepted as $check => $why) {
        echo "  ~ $check – $why\n";
    }
}
echo "\n";
if ($r['identical'] === $r['total']) {
    echo "All {$r['total']} pages identical" . (parity_accepted() ? ' (apart from the accepted improvements listed)' : '') . ".\n";
    exit(0);
}
echo ($r['total'] - $r['identical']) . " of {$r['total']} pages differ.\n";
exit(1);

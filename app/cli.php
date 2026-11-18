<?php
/**
 * Command line tools. Run from the project folder:
 *
 *   php app/cli.php render /about.html     print the page as the PHP site would serve it
 *   php app/cli.php check                  render every page, report errors and status codes
 *   php app/cli.php clear-cache            delete stored rendered pages
 *   php app/cli.php rebuild-index          rebuild the address → page list
 *
 * Add --content=/path/to/content to use another content folder.
 * Rendering always works as if the site were switched on, so it can be checked before go-live.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

$args = array_slice($argv, 1);
$opts = [];
foreach ($args as $i => $a) {
    if (strpos($a, '--') === 0) {
        [$k, $v] = array_pad(explode('=', substr($a, 2), 2), 2, true);
        $opts[$k] = $v;
        unset($args[$i]);
    }
}
$args = array_values($args);
if (isset($opts['content'])) {
    config_set(['content_dir' => (string) $opts['content']]);
}
$cmd = $args[0] ?? 'help';

switch ($cmd) {
    case 'render':
        config_set(['enabled' => true, 'cache' => false]);
        $resp = front_dispatch('GET', $args[1] ?? '/');
        if (isset($opts['status'])) {
            fwrite(STDERR, $resp['status'] . ' ' . json_encode($resp['headers']) . "\n");
        }
        echo $resp['body'];
        exit($resp['status'] < 400 ? 0 : 1);

    case 'check':
        config_set(['enabled' => true, 'cache' => false]);
        $fail = 0;
        $pages = pages_all();
        foreach ($pages as $id => $page) {
            if (($page['status'] ?? '') !== 'published') {
                echo "  draft  {$page['path']}\n";
                continue;
            }
            $errors = [];
            set_error_handler(function ($no, $str, $file, $line) use (&$errors) {
                $errors[] = "$str ($file:$line)";
                return true;
            });
            try {
                $resp = front_dispatch('GET', $page['path']);
            } catch (Throwable $t) {
                $resp = ['status' => 500, 'body' => ''];
                $errors[] = $t->getMessage();
            }
            restore_error_handler();
            $h1 = preg_match_all('/<h1[\s>]/i', $resp['body']);
            $ok = $resp['status'] === 200 && !$errors && $h1 === 1;
            $fail += $ok ? 0 : 1;
            printf("  %s %d  %s%s\n", $ok ? 'ok  ' : 'FAIL', $resp['status'], $page['path'], $h1 !== 1 ? "  (H1 count: $h1)" : '');
            foreach ($errors as $err) {
                echo "         $err\n";
            }
        }
        echo count($pages) . " pages, $fail problems\n";
        exit($fail ? 1 : 0);

    case 'clear-cache':
        cache_clear();
        echo "Cache cleared.\n";
        exit(0);

    case 'rebuild-index':
        $index = index_rebuild();
        echo count($index['paths']) . " addresses indexed.\n";
        exit(0);

    default:
        echo "Usage: php app/cli.php render <path> [--status] | check | clear-cache | rebuild-index [--content=DIR]\n";
        exit($cmd === 'help' ? 0 : 1);
}

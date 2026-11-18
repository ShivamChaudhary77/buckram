<?php
/**
 * Command line tools. Run from the project folder:
 *
 *   php app/cli.php render /about.html     print the page as the PHP site would serve it
 *   php app/cli.php check                  render every page, report errors and status codes
 *   php app/cli.php clear-cache            delete stored rendered pages
 *   php app/cli.php rebuild-index          rebuild the address → page list
 *   php app/cli.php setup-link             print a one-time link to choose the first dashboard password
 *   php app/cli.php set-password           set or reset the dashboard password (typed, or piped in)
 *   php app/cli.php seo-report             list SEO problems on every page
 *   php app/cli.php apply-fixes F.json     bulk-fix titles/descriptions (dry run; add --apply)
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

    case 'setup-link':
        require_once APP_DIR . '/security.php';
        if (password_is_set()) {
            echo "A dashboard password already exists. To replace it, run: php app/cli.php set-password\n";
            exit(1);
        }
        $token = setup_token_create();
        echo "Open this link within 24 hours to choose the dashboard password (it works once):\n";
        echo config('base_url') . '/admin/setup.php?token=' . $token . "\n";
        exit(0);

    case 'set-password':
        require_once APP_DIR . '/security.php';
        // Reads the password from the keyboard (hidden) or from piped input, never from the command line.
        if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
            echo 'New dashboard password: ';
            system('stty -echo');
            $pw = rtrim((string) fgets(STDIN), "\r\n");
            echo "\nType it again: ";
            $pw2 = rtrim((string) fgets(STDIN), "\r\n");
            system('stty echo');
            echo "\n";
        } else {
            $pw = rtrim((string) fgets(STDIN), "\r\n");
            $pw2 = $pw;
        }
        $problem = password_problem($pw, $pw2);
        if ($problem) {
            fwrite(STDERR, $problem . "\n");
            exit(1);
        }
        password_set($pw);
        echo "Password saved.\n";
        exit(0);

    case 'seo-report':
        $report = seo_report();
        $pages = pages_all();
        $n = 0;
        foreach ($report as $id => $checks) {
            if (!$checks) {
                continue;
            }
            echo ($pages[$id]['path'] ?? $id) . "\n";
            foreach ($checks as $c) {
                echo '  ' . ($c['level'] === 'bad' ? '✗' : '!') . ' ' . $c['text'] . "\n";
                $n++;
            }
        }
        echo $n ? "$n things to look at.\n" : "No problems.\n";
        exit(0);

    case 'apply-fixes':
        // php app/cli.php apply-fixes fixes.json [--apply]
        // fixes.json: [{"page": "about", "field": "seo.title", "from": "current text", "to": "new text"}, …]
        // Dry run by default. Only changes fields that still hold "from". History is kept.
        $file = (string) ($args[1] ?? '');
        if ($file === '' || !is_file($file)) {
            fwrite(STDERR, "Usage: php app/cli.php apply-fixes <fixes.json> [--apply]\n");
            exit(1);
        }
        foreach (seo_apply_fixes($file, isset($opts['apply'])) as $line) {
            echo $line . "\n";
        }
        echo isset($opts['apply']) ? "Done.\n" : "Dry run: nothing changed. Add --apply to save.\n";
        exit(0);

    case 'clear-cache':
        cache_clear();
        echo "Cache cleared.\n";
        exit(0);

    case 'rebuild-index':
        $index = index_rebuild();
        echo count($index['paths']) . " addresses indexed.\n";
        exit(0);

    default:
        echo "Usage: php app/cli.php render <path> [--status] | check | clear-cache | rebuild-index | setup-link | set-password | seo-report | apply-fixes <file> [--apply] [--content=DIR]\n";
        exit($cmd === 'help' ? 0 : 1);
}

<?php
/**
 * Live checker: run after every deploy.
 *
 *   php tests/verify-live.php https://www.jaibabananakinterlinings.com
 *
 * Finds the pages through the live sitemap.xml (so it also covers pages added or
 * renamed in the dashboard) and checks:
 *   - every page answers 200 with a title, a description and a canonical pointing to itself
 *   - sitemap.xml, robots.txt and llms.txt are served correctly
 *   - address clean-up (uppercase → 301), unknown address → real 404
 *   - private folders and files are not reachable
 *   - /admin/ shows the login page and is not indexable
 * Prints the pass count; exit code 1 when anything fails.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
$base = rtrim((string) ($argv[1] ?? ''), '/');
if (!preg_match('#^https?://[^/]+$#', $base)) {
    fwrite(STDERR, "Usage: php tests/verify-live.php https://www.example.com\n");
    exit(1);
}
if (!function_exists('curl_init')) {
    fwrite(STDERR, "This checker needs the PHP curl extension.\n");
    exit(1);
}

$pass = 0;
$fail = [];
function ok(string $label, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
    } else {
        $fail[] = $label;
        echo "  FAIL  $label" . ($detail !== '' ? "  ($detail)" : '') . "\n";
    }
}

function fetch(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'site-live-check/1.0',
    ]);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = [];
    foreach (explode("\r\n", substr($raw, 0, $hsize)) as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return [$status, $headers, substr($raw, $hsize)];
}

echo "Checking $base\n\n• generated files\n";
[$s, $h, $sitemap] = fetch("$base/sitemap.xml");
$xml = $s === 200 ? @simplexml_load_string($sitemap) : false;
ok('sitemap.xml is served and valid', $xml !== false, "HTTP $s");
[$s, , $robots] = fetch("$base/robots.txt");
ok('robots.txt lists the sitemap and blocks /admin/', $s === 200 && strpos($robots, "Sitemap: $base/sitemap.xml") !== false && strpos($robots, 'Disallow: /admin/') !== false);
[$s, , $llms] = fetch("$base/llms.txt");
ok('llms.txt is served', $s === 200 && strpos($llms, '# ') === 0);

echo "• pages\n";
$urls = [];
if ($xml) {
    foreach ($xml->url as $u) {
        $urls[] = (string) $u->loc;
    }
}
ok('sitemap lists pages', count($urls) > 0, count($urls) . ' pages');
foreach ($urls as $url) {
    [$s, $h, $body] = fetch($url);
    $path = (string) parse_url($url, PHP_URL_PATH);
    $title = preg_match('#<title>([^<]+)</title>#', $body, $m) ? trim($m[1]) : '';
    $canon = preg_match('#<link rel="canonical" href="([^"]+)"#', $body, $m) ? html_entity_decode($m[1]) : '';
    $h1 = preg_match_all('#<h1[\s>]#i', $body);
    ok("$path answers 200", $s === 200, "HTTP $s");
    ok("$path has a title and a description", $title !== '' && strpos($body, '<meta name="description"') !== false);
    ok("$path canonical points to itself", $canon === $url, $canon);
    ok("$path has one main heading", $h1 === 1, "$h1 found");
    ok("$path is indexable", strpos($body, 'content="index,follow') !== false);
}

echo "• addresses\n";
$first = $urls[0] ?? "$base/about.html";
$p = (string) parse_url($first, PHP_URL_PATH);
if ($p !== '/' && $p !== '') {
    [$s, $h] = fetch($base . strtoupper($p));
    ok('uppercase address redirects (301) to the official one', $s === 301 && ($h['location'] ?? '') === $p, "HTTP $s → " . ($h['location'] ?? ''));
}
[$s, , $b] = fetch("$base/this-page-does-not-exist-" . bin2hex(random_bytes(3)) . '.html');
ok('unknown address gives a real 404 page', $s === 404 && stripos($b, 'noindex') !== false, "HTTP $s");
[$s] = fetch("$base/");
ok('home page answers', in_array($s, [200, 301], true), "HTTP $s");

echo "• private files\n";
foreach (['/app/config.php', '/app/config.local.php', '/content/site.json', '/content/.auth.json', '/templates/layout.php',
          '/scripts/go-live.sh', '/tests/run.php', '/DASHBOARD-PLAN.md', '/.git/config', '/admin/_init.php', '/.htaccess'] as $priv) {
    [$s] = fetch($base . $priv);
    ok("not reachable: $priv", in_array($s, [403, 404], true), "HTTP $s");
}

echo "• dashboard\n";
[$s, $h] = fetch("$base/admin/");
ok('/admin/ sends to login (or setup)', $s === 303 && preg_match('#/admin/(login|setup)\.php#', $h['location'] ?? '') === 1, "HTTP $s");
$loc = (string) ($h['location'] ?? '/admin/login.php');
[$s, $h2] = fetch($base . (strpos($loc, 'http') === 0 ? (string) parse_url($loc, PHP_URL_PATH) : $loc));
ok('login/setup page is not indexable', $s === 200 && stripos($h2['x-robots-tag'] ?? '', 'noindex') !== false, "HTTP $s");
ok('dashboard is served over HTTPS', strpos($base, 'https://') === 0 || preg_match('#^http://(localhost|127\.0\.0\.1)#', $base) === 1);

echo "\n$pass passed, " . count($fail) . " failed\n";
exit($fail ? 1 : 0);

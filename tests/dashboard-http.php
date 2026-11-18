<?php
/**
 * Dashboard end-to-end test over real HTTP.
 *   php tests/dashboard-http.php
 *
 * Starts PHP's built-in server on a free port with a temporary copy of the content
 * (never the real one), then drives /admin like a browser: setup, login, create,
 * preview, publish, edit, history, rename, upload, delete/restore, settings,
 * CSRF rejection, logout and the login lockout.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_DIR . '/security.php';
require_once ROOT_DIR . '/scripts/lib/html-compare.php';
require_once ROOT_DIR . '/scripts/lib/migrate-lib.php';

$passed = 0;
$failed = [];
function ok(string $label, bool $cond): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
    } else {
        $failed[] = $label;
        echo "  FAIL  $label\n";
    }
}

// ---- temporary content + uploads, migrated from the static site
$tmp = sys_get_temp_dir() . '/jaibaba-http-' . bin2hex(random_bytes(4));
$content = "$tmp/content";
$uploads = ROOT_DIR . '/uploads-test-' . bin2hex(random_bytes(3)); // must be under the web root to be served
ensure_dir("$content/pages");
config_set(['content_dir' => $content, 'cache' => false, 'enabled' => true, 'uploads_dir' => $uploads]);
migrate_site(ROOT_DIR, $content);

// ---- server
$sock = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) explode(':', stream_socket_get_name($sock, false))[1];
fclose($sock);
$env = array_merge(getenv(), [
    'JAIBABA_ENABLED' => '1', 'JAIBABA_PREVIEW_PHP' => '1', 'JAIBABA_CONTENT_DIR' => $content,
    'JAIBABA_UPLOADS_DIR' => $uploads, 'JAIBABA_CACHE' => '1',
]);
$log = "$tmp/server.log";
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', ROOT_DIR, ROOT_DIR . '/deploy/dev-router.php'], [1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, ROOT_DIR, $env);
$base = "http://127.0.0.1:$port";
for ($i = 0; $i < 50; $i++) {
    if (@file_get_contents("$base/assets/css/style.css", false, stream_context_create(['http' => ['timeout' => 1]])) !== false) {
        break;
    }
    usleep(100000);
}

$jar = "$tmp/cookies.txt";
/** HTTP request. Returns [status, headers(lowercase), body]. */
function http(string $method, string $path, array $fields = [], bool $multipart = false): array
{
    global $base, $jar;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 20,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $fields : http_build_query($fields));
    }
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

function csrf_from(string $html): string
{
    return preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? html_entity_decode($m[1]) : '';
}

/** All form values of the first form matching $formSelector-ish id, like a browser submits them. */
function form_values(string $html, string $formId): array
{
    $doc = dom_document($html);
    $form = $doc->getElementById($formId);
    $x = new DOMXPath($doc);
    $pairs = [];
    foreach ($x->query('.//input[@name] | .//textarea[@name] | .//select[@name]', $form) as $el) {
        if ($el->parentNode && $x->query('ancestor::template', $el)->length) {
            continue;
        }
        $n = $el->getAttribute('name');
        if ($el->nodeName === 'input') {
            $t = strtolower($el->getAttribute('type') ?: 'text');
            if (in_array($t, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
                continue;
            }
            if (in_array($t, ['submit', 'button', 'file'], true)) {
                continue;
            }
            $pairs[] = [$n, $el->getAttribute('value')];
        } elseif ($el->nodeName === 'textarea') {
            $pairs[] = [$n, $el->textContent];
        } else {
            $sel = $x->query('.//option[@selected]', $el)->item(0) ?? $x->query('.//option', $el)->item(0);
            $pairs[] = [$n, $sel ? $sel->getAttribute('value') : ''];
        }
    }
    parse_str(implode('&', array_map(function ($p) {
        return rawurlencode($p[0]) . '=' . rawurlencode($p[1]);
    }, $pairs)), $out);
    return $out;
}

try {
    echo "• setup and login\n";
    [$s, $h] = http('GET', '/admin/');
    ok('dashboard sends to setup when no password exists', $s === 303 && strpos($h['location'] ?? '', '/admin/setup.php') === 0);
    [$s, , $b] = http('GET', '/admin/setup.php');
    ok('setup without a token only explains', $s === 200 && strpos($b, 'setup-link') !== false && strpos($b, 'name="password"') === false);
    $token = setup_token_create();
    [$s, , $b] = http('GET', '/admin/setup.php?token=' . $token);
    ok('setup link shows the password form', $s === 200 && strpos($b, 'name="password"') !== false);
    [$s, , $b2] = http('POST', '/admin/setup.php', ['_csrf' => csrf_from($b), 'token' => $token, 'password' => 'short', 'confirm' => 'short']);
    ok('too-short password refused', $s === 200 && strpos($b2, 'at least 10') !== false);
    [$s, $h] = http('POST', '/admin/setup.php', ['_csrf' => csrf_from($b), 'token' => $token, 'password' => 'Interlining 1977!', 'confirm' => 'Interlining 1977!']);
    ok('password saved, logged in', $s === 303 && ($h['location'] ?? '') === '/admin/');
    [$s, , $b] = http('GET', '/admin/setup.php?token=' . $token);
    ok('setup link dead after use', $s === 303);
    [$s, , $b] = http('GET', '/admin/');
    ok('page list shows all pages', $s === 200 && substr_count($b, 'class="page-row"') === 18);
    ok('dashboard is noindex', strpos($b, 'noindex') !== false);
    [$s, $h] = http('POST', '/admin/logout.php', ['_csrf' => csrf_from($b)]);
    ok('logout', $s === 303 && strpos($h['location'], 'login.php') !== false);
    [$s, $h] = http('GET', '/admin/edit.php?id=home');
    ok('logged-out editor sends to login', $s === 303 && strpos($h['location'], '/admin/login.php') === 0);
    [, , $b] = http('GET', '/admin/login.php');
    [$s, , $b2] = http('POST', '/admin/login.php', ['_csrf' => csrf_from($b), 'password' => 'wrong password']);
    ok('wrong password refused', $s === 200 && strpos($b2, 'not right') !== false);
    [$s, $h] = http('POST', '/admin/login.php', ['_csrf' => csrf_from($b2), 'password' => 'Interlining 1977!', 'next' => '/admin/settings.php']);
    ok('login works and returns to the asked page', $s === 303 && ($h['location'] ?? '') === '/admin/settings.php');
    [$s, $h] = http('POST', '/admin/login.php', ['_csrf' => 'x', 'password' => 'Interlining 1977!', 'next' => 'https://evil.example/']);

    echo "• create, preview, publish\n";
    [, , $b] = http('GET', '/admin/new.php?type=product');
    [$s, $h] = http('POST', '/admin/new.php', ['_csrf' => csrf_from($b), 'title' => 'Non Woven Interlining', 'type' => 'product']);
    ok('draft created', $s === 303 && strpos($h['location'], 'edit.php?id=non-woven-interlining') !== false);
    [$s] = http('GET', '/product/non-woven-interlining.html');
    ok('draft is not on the website', $s === 404);
    [$s, , $edit] = http('GET', '/admin/edit.php?id=non-woven-interlining');
    ok('editor opens', $s === 200 && strpos($edit, 'data-block-template="faq"') !== false);
    $v = form_values($edit, 'editor');
    $v['blocks'][0]['intro'] = 'A light, soft interlining for shirts.';
    $v['page']['seo']['title'] = 'Non Woven Interlining Manufacturer | Jaibaba Interlinings';
    [$s, $h, $pv] = http('POST', '/admin/preview.php', $v + ['action' => 'preview']);
    ok('preview shows unsaved changes, noindex', $s === 200 && strpos($pv, 'A light, soft interlining for shirts.') !== false && strpos($pv, 'noindex,nofollow') !== false && strpos($pv, 'not saved yet') !== false);
    [$s] = http('GET', '/product/non-woven-interlining.html');
    ok('preview saved nothing', $s === 404);
    [$s, $h] = http('POST', '/admin/edit.php?id=non-woven-interlining', $v + ['action' => 'publish']);
    ok('publish', $s === 303);
    [$s, , $live] = http('GET', '/product/non-woven-interlining.html');
    ok('published page is live', $s === 200 && strpos($live, 'A light, soft interlining for shirts.') !== false && strpos($live, '<title>Non Woven Interlining Manufacturer | Jaibaba Interlinings</title>') !== false);

    echo "• edit, history, rename\n";
    [, , $edit] = http('GET', '/admin/edit.php?id=tatron');
    $v = form_values($edit, 'editor');
    $old = $v['blocks'][0]['heading'];
    $v['blocks'][0]['heading'] = 'Tatron Lining';
    [$s] = http('POST', '/admin/edit.php?id=tatron', $v + ['action' => 'save']);
    [, , $live] = http('GET', '/product/tatron.html');
    ok('edit is live at once (cache cleared)', $s === 303 && strpos($live, '<h1>Tatron Lining</h1>') !== false);
    [$s, , $b] = http('POST', '/admin/edit.php?id=tatron', $v + ['action' => 'save']);
    ok('stale form (saved elsewhere meanwhile) is refused', $s === 200 && strpos($b, 'another window') !== false);
    [, , $hist] = http('GET', '/admin/history.php?id=tatron');
    preg_match('/name="version" value="([^"]+)"/', $hist, $m);
    [$s] = http('POST', '/admin/history.php?id=tatron', ['_csrf' => csrf_from($hist), 'version' => $m[1] ?? '']);
    [, , $live] = http('GET', '/product/tatron.html');
    ok('history restore brings the old heading back', $s === 303 && strpos($live, '<h1>' . e($old) . '</h1>') !== false);
    [, , $edit] = http('GET', '/admin/edit.php?id=tatron');
    $v = form_values($edit, 'editor');
    $v['page']['path'] = '/product/tatron-lining.html';
    [$s] = http('POST', '/admin/edit.php?id=tatron', $v + ['action' => 'save']);
    [$s1] = http('GET', '/product/tatron-lining.html');
    [$s2, $h2] = http('GET', '/product/tatron.html');
    ok('rename: new address live, old address 301 → new', $s1 === 200 && $s2 === 301 && ($h2['location'] ?? '') === '/product/tatron-lining.html');

    echo "• pictures, delete, trash, redirects\n";
    [, , $media] = http('GET', '/admin/media.php');
    ok('pictures screen lists site images', substr_count($media, 'class="media-item"') > 50);
    $png = "$tmp/test.png";
    imagepng(imagecreatetruecolor(30, 20), $png);
    [$s] = http('POST', '/admin/media.php', ['_csrf' => csrf_from($media), 'action' => 'upload', 'images[0]' => new CURLFile($png, 'image/png', 'Factory Photo.png')], true);
    [, , $media] = http('GET', '/admin/media.php');
    ok('upload appears in the list', $s === 303 && preg_match('#/uploads-test-[0-9a-f]+/\d{4}/\d{2}/factory-photo-[0-9a-f]{6}\.png#', $media) !== 1 && preg_match('#/uploads/\d{4}/\d{2}/factory-photo-[0-9a-f]{6}\.png#', $media) === 1);
    $evil = "$tmp/evil.png";
    file_put_contents($evil, '<?php echo "hacked";');
    [$s] = http('POST', '/admin/media.php', ['_csrf' => csrf_from($media), 'action' => 'upload', 'images[0]' => new CURLFile($evil, 'image/png', 'evil.php')], true);
    [, , $media2] = http('GET', '/admin/media.php');
    ok('fake picture (PHP code) refused', strpos($media2, 'Only JPG, PNG') !== false && strpos($media2, 'evil') === false);
    [, , $del] = http('GET', '/admin/delete.php?id=eva-foam');
    [$s] = http('POST', '/admin/delete.php?id=eva-foam', ['_csrf' => csrf_from($del), 'make_redirect' => '1', 'redirect_to' => '/products.html']);
    [$s2, $h2] = http('GET', '/product/eva-foam.html');
    ok('deleted page redirects to the product list', $s === 303 && $s2 === 301 && ($h2['location'] ?? '') === '/products.html');
    [, , $tr] = http('GET', '/admin/trash.php');
    preg_match('/name="file" value="([^"]+)"/', $tr, $m);
    [$s] = http('POST', '/admin/trash.php', ['_csrf' => csrf_from($tr), 'file' => $m[1] ?? '', 'action' => 'restore']);
    [$s2] = http('GET', '/product/eva-foam.html');
    ok('restore from trash removes its redirect, page live again', $s === 303 && $s2 === 200);
    [, , $rd] = http('GET', '/admin/redirects.php');
    [$s] = http('POST', '/admin/redirects.php', ['_csrf' => csrf_from($rd), 'action' => 'add', 'from' => '/old-catalogue.html', 'to' => '/products.html', 'code' => '301']);
    [$s2, $h2] = http('GET', '/old-catalogue.html');
    ok('manual redirect works', $s === 303 && $s2 === 301 && $h2['location'] === '/products.html');
    [$s, , $b] = http('POST', '/admin/redirects.php', ['_csrf' => csrf_from($rd), 'action' => 'add', 'from' => '/products.html', 'to' => '/old-catalogue.html', 'code' => '301']);
    ok('redirect loop / live page refused', $s === 200 && strpos($b, 'Nothing was saved') !== false);

    echo "• settings\n";
    [, , $set] = http('GET', '/admin/settings.php');
    $v = form_values($set, 'settings-form') ?: [];
    $doc = dom_document($set);
    $sv = form_values(str_replace('class="settings-form"', 'id="settings-form" class="settings-form"', $set), 'settings-form');
    $sv['site']['hours'] = 'Mon – Sat, 10:00 AM – 7:00 PM';
    [$s] = http('POST', '/admin/settings.php', $sv);
    [, , $home] = http('GET', '/');
    ok('settings change shows on every page', $s === 303 && strpos($home, 'Mon – Sat, 10:00 AM – 7:00 PM') !== false);
    $sv['site']['tracking']['ga4'] = 'not-an-id';
    [$s, , $b] = http('POST', '/admin/settings.php', $sv);
    ok('wrong tracking ID refused', $s === 200 && strpos($b, 'G-XXXXXXXXXX') !== false);

    echo "• safety\n";
    foreach (['/admin/edit.php?id=home' => ['action' => 'save'], '/admin/settings.php' => [], '/admin/redirects.php' => ['action' => 'add', 'from' => '/x.html', 'to' => '/'],
        '/admin/trash.php' => ['action' => 'empty'], '/admin/new.php' => ['title' => 'X'], '/admin/media.php' => ['action' => 'delete', 'url' => '/uploads/x.png'],
        '/admin/history.php?id=home' => ['version' => 'x'], '/admin/delete.php?id=about' => [], '/admin/logout.php' => []] as $url => $fields) {
        [$s] = http('POST', $url, $fields + ['_csrf' => 'forged']);
        ok("POST without a valid CSRF token is refused: $url", $s === 400);
    }
    [$s] = http('GET', '/about.html');
    ok('about page untouched by forged requests', $s === 200);
    foreach (['/admin/_init.php', '/content/site.json', '/app/config.php', '/DASHBOARD-PLAN.md', '/tests/run.php'] as $private) {
        [$s] = http('GET', $private);
        ok("private file blocked: $private", $s === 403);
    }
    [$s, , $b] = http('GET', '/admin/login.php');
    ok('logged-in user is sent on from login', $s === 303);

    echo "• login lockout\n";
    [, , $b] = http('POST', '/admin/logout.php', ['_csrf' => csrf_from(http('GET', '/admin/')[2])]);
    [, , $b] = http('GET', '/admin/login.php');
    for ($i = 0; $i < 5; $i++) {
        [, , $b] = http('POST', '/admin/login.php', ['_csrf' => csrf_from($b), 'password' => "wrong $i"]);
    }
    [$s, , $b] = http('POST', '/admin/login.php', ['_csrf' => csrf_from($b), 'password' => 'Interlining 1977!']);
    ok('after 5 wrong passwords even the right one waits 15 minutes', $s === 200 && strpos($b, 'Too many wrong passwords') !== false);

    $errs = (string) @file_get_contents($log);
    ok('no PHP warnings or errors in the server log', !preg_match('/(Warning|Notice|Deprecated|Fatal|Parse error)/', $errs));
    if (preg_match_all('/.*(Warning|Notice|Deprecated|Fatal).*/', $errs, $mm)) {
        echo '      ' . implode("\n      ", array_slice(array_unique($mm[0]), 0, 8)) . "\n";
    }
} finally {
    proc_terminate($proc);
    proc_close($proc);
    rm_tree($tmp);
    rm_tree($uploads);
}

echo "\n$passed passed, " . count($failed) . " failed\n";
exit($failed ? 1 : 0);

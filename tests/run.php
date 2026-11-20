<?php
/**
 * Unit tests. Run: php tests/run.php
 * Uses a temporary content folder built from tests/fixtures – never the real content.
 * Any PHP warning or notice counts as a failure.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = [];

function check(string $label, bool $ok): void
{
    if ($ok) {
        $GLOBALS['passed']++;
    } else {
        $GLOBALS['failed'][] = $label;
        echo "  FAIL  $label\n";
    }
}

function expect_error(string $label, callable $fn): void
{
    try {
        $fn();
        check($label, false);
    } catch (ContentError $e) {
        check($label, true);
    }
}

set_error_handler(function ($no, $msg, $file, $line) {
    throw new ErrorException($msg, 0, $no, $file, $line);
});

function fresh_content(): string
{
    $dir = sys_get_temp_dir() . '/jaibaba-test-' . bin2hex(random_bytes(4));
    ensure_dir($dir . '/pages');
    copy(__DIR__ . '/fixtures/site.json', $dir . '/site.json');
    foreach (glob(__DIR__ . '/fixtures/pages/*.json') as $f) {
        copy($f, $dir . '/pages/' . basename($f));
    }
    config_set(['content_dir' => $dir, 'enabled' => true, 'cache' => true, 'home_path' => '/index.html', 'static_fallback' => true]);
    clearstatcache();
    return $dir;
}

function get(string $uri, string $method = 'GET'): array
{
    return front_dispatch($method, $uri);
}

$dirs = [];
$section = function (string $name) {
    echo "• $name\n";
};

try {
    /* ------------------------------------------------------------ helpers */
    $section('helpers');
    $dirs[] = $dir = fresh_content();
    check('e() escapes quotes and tags', e('<a href="x">&\'') === '&lt;a href=&quot;x&quot;&gt;&amp;&apos;');
    check('e() handles null', e(null) === '');
    check('valid_id accepts slug', valid_id('buckram-fabric'));
    foreach (['../x', 'A', 'a_b', '', 'a--b', '-a', 'a/b'] as $bad) {
        check("valid_id rejects '$bad'", !valid_id($bad));
    }
    check('slugify', slugify('Belt Roll & Placket (Patti)') === 'belt-roll-and-placket-patti');
    check('absolute_url', absolute_url('/about.html') === 'https://www.jaibabananakinterlinings.com/about.html');
    check('absolute_url keeps full URLs', absolute_url('https://x.com/a') === 'https://x.com/a');
    json_write("$dir/t/a.json", ['ü' => 'é/x']);
    check('json round trip', json_read("$dir/t/a.json") === ['ü' => 'é/x']);
    check('json_write leaves no temp files', count(glob("$dir/t/*.tmp")) === 0);
    check('json_read default when missing', json_read("$dir/none.json", 'd') === 'd');
    file_put_contents("$dir/t/bad.json", '{broken');
    try {
        json_read("$dir/t/bad.json");
        check('broken JSON throws', false);
    } catch (RuntimeException $e) {
        check('broken JSON throws', true);
    }
    check('array_get dot path', array_get(['a' => ['b' => 1]], 'a.b') === 1 && array_get([], 'x.y', 7) === 7);

    /* ------------------------------------------------------------ content */
    $section('content store');
    check('pages_all loads fixtures', count(pages_all()) === 3);
    $idx = index_get();
    check('index built with version', $idx['version'] === INDEX_VERSION && isset($idx['paths']['/index.html']));
    check('find published page by path', (page_find_by_path('/product/buckram-fabric.html')['id'] ?? '') === 'buckram-fabric');
    check('draft hidden from public lookup', page_find_by_path('/product/draft-product.html') === null);
    check('draft visible with includeDrafts', page_find_by_path('/product/draft-product.html', true) !== null);

    $new = ['id' => 'tatron', 'type' => 'product', 'status' => 'published', 'path' => '/product/Tatron.html', 'title' => 'Tatron', 'blocks' => []];
    $saved = page_save($new);
    check('save normalizes path to lowercase', $saved['path'] === '/product/tatron.html');
    check('save sets created/updated', !empty($saved['created']) && !empty($saved['updated']));
    check('saved page is findable', page_find_by_path('/product/tatron.html') !== null);

    expect_error('reject bad id', function () use ($new) { page_save(['id' => '../evil'] + $new); });
    expect_error('reject duplicate path', function () use ($new) { page_save(['id' => 'other', 'path' => '/product/tatron.html'] + $new); });
    expect_error('reject reserved path', function () use ($new) { page_save(['id' => 'x', 'path' => '/admin/x.html'] + $new); });
    expect_error('reject bad path', function () use ($new) { page_save(['id' => 'x', 'path' => '/a b.php'] + $new); });
    expect_error('reject unknown type', function () use ($new) { page_save(['type' => 'blog'] + $new); });
    expect_error('reject empty title', function () use ($new) { page_save(['title' => ' '] + $new); });
    expect_error('reject damaged block', function () use ($new) { page_save(['blocks' => [['type' => '<x>']]] + $new); });
    check('folder style path is valid', path_is_valid('/products/'));
    check('root path is not a page path', !path_is_valid('/'));

    // history
    for ($i = 1; $i <= 35; $i++) {
        $p = page_load('tatron');
        $p['title'] = "Tatron v$i";
        page_save($p);
    }
    $hist = history_list('tatron');
    check('history keeps the last 30', count($hist) === HISTORY_KEEP);
    check('history newest first', $hist[0]['title'] === 'Tatron v34');
    $restoreFrom = $hist[5]['version'];
    $restored = history_restore('tatron', $restoreFrom);
    check('history restore brings old title back', $restored['title'] === 'Tatron v29');
    check('restore keeps current version in history', history_list('tatron')[0]['title'] === 'Tatron v35');
    check('history_load rejects bad version', history_load('tatron', '../../site') === null);

    // trash
    $name = page_trash('tatron');
    check('trashed page gone from site', page_find_by_path('/product/tatron.html') === null && page_load('tatron') === null);
    check('trash lists it', (trash_list()[0]['id'] ?? '') === 'tatron');
    $back = trash_restore($name);
    check('trash restore', $back['id'] === 'tatron' && page_find_by_path('/product/tatron.html') !== null);
    $name2 = page_trash('tatron');
    page_save($new);
    expect_error('restore refuses when id exists again', function () use ($name2) { trash_restore($name2); });
    expect_error('trash rejects bad file name', function () { trash_restore('../site.json'); });
    check('trash empty', trash_empty() === 1 && trash_list() === []);

    // redirects
    redirects_save([['from' => '/old.html', 'to' => '/about.html', 'code' => 301], ['from' => '/gone.html', 'code' => 410]]);
    check('redirects saved', count(redirects_load()) === 2 && redirect_find('/gone.html')['code'] === 410);
    expect_error('redirect loop rejected', function () {
        redirects_save([['from' => '/a.html', 'to' => '/b.html'], ['from' => '/b.html', 'to' => '/a.html']]);
    });
    expect_error('redirect chain rejected', function () {
        redirects_save([['from' => '/a.html', 'to' => '/b.html'], ['from' => '/b.html', 'to' => '/c.html']]);
    });
    expect_error('redirect to itself rejected', function () { redirects_save([['from' => '/a.html', 'to' => '/a.html']]); });
    expect_error('redirect from a live page rejected', function () { redirects_save([['from' => '/index.html', 'to' => '/x.html']]); });
    expect_error('redirect without slash rejected', function () { redirects_save([['from' => 'a.html', 'to' => '/x.html']]); });
    expect_error('page on a redirected address rejected', function () use ($new) {
        page_save(['id' => 'old', 'path' => '/old.html'] + $new);
    });

    /* ----------------------------------------------------------- rendering */
    $section('rendering');
    $dirs[] = $dir = fresh_content();
    $warnings = [];
    $home = render_page(page_load('home'));
    check('home has exactly one H1', preg_match_all('/<h1[\s>]/', $home) === 1);
    check('home title tag', strpos($home, '<title>Jaibaba Interlinings | Garment Interlining Manufacturer Since 1977, Delhi</title>') !== false);
    check('home canonical is /index.html', strpos($home, '<link rel="canonical" href="https://www.jaibabananakinterlinings.com/index.html">') !== false);
    check('live page: robots index,follow', strpos($home, '<meta name="robots" content="index,follow,max-image-preview:large">') !== false);
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $home, $m);
    $ld = json_decode($m[1][0] ?? '', true);
    check('JSON-LD graph parses', is_array($ld) && in_array('Organization', array_column($ld['@graph'] ?? [], '@type'), true));
    check('extra page JSON-LD kept after the graph', (json_decode($m[1][1] ?? '', true)['@type'] ?? '') === 'Organization');
    check('menu is rendered on the server', strpos($home, 'href="/product/buckram-fabric.html">Buckram Fabric</a>') !== false);
    check('no client-side include placeholders', strpos($home, 'data-include') === false);
    check('phone in footer', strpos($home, '<a href="tel:+918750555033">+91 8750555033</a>') !== false);
    check('social URL escaped', strpos($home, 'utm_source=qr&amp;igsh=') !== false);
    check('WhatsApp float with message', strpos($home, 'https://wa.me/919910555033?text=Hello%2C%20I%20would%20like') !== false);
    check('product card from product page data', strpos($home, 'Stiff, shape-holding fabric for caps &amp; belts.') !== false);
    check('draft product card hidden', strpos($home, 'Not public yet') === false);
    check('FAQ ids', strpos($home, 'id="faq1" class="accordion-collapse collapse show" data-bs-parent="#homeFaq"') !== false
        && strpos($home, 'data-bs-target="#faq2"') !== false);
    check('FAQ answer keeps its link', strpos($home, '<a href="/exports.html">Exports page</a>') !== false);
    check('CTA default WhatsApp button', strpos($home, '<i class="fab fa-whatsapp me-2"></i>Chat on WhatsApp</a>') !== false);
    check('scripts at the end', strpos($home, '<script src="/assets/js/main.js"></script>') !== false);

    $prod = render_page(page_load('buckram-fabric'));
    check('product has one H1', preg_match_all('/<h1[\s>]/', $prod) === 1);
    check('breadcrumb rendered', strpos($prod, '<li><a href="/products.html">Products</a></li>') !== false
        && strpos($prod, '<li class="active">Buckram Fabric</li>') !== false);
    check('hero background', strpos($prod, "style=\"background-image:url('/assets/images/products/buckram-fabric/buckram-fabric-branded-rolls-04.jpg');\"") !== false);
    check('card title escaped', strpos($prod, '<h3>Caps &amp; Cap Peaks</h3>') !== false);
    check('card animation delays', strpos($prod, 'data-aos="fade-up" data-aos-delay="100"') !== false);
    check('centred value card', strpos($prod, '<div class="value-card text-center">') !== false);
    check('variant card h4 + swatch', strpos($prod, 'class="variant-swatch">') !== false && strpos($prod, '<h4>Narrow</h4>') !== false);
    check('spec table rows', strpos($prod, '<td>Composition</td>') !== false);
    check('media-text button', strpos($prod, '<a href="/contact.html" class="btn btn-navy">Request a Quote <i class="fas fa-arrow-right ms-2"></i></a>') !== false);

    $draftHtml = render_page(page_load('draft-product'), ['preview' => true]);
    check('preview is noindex,nofollow', strpos($draftHtml, '<meta name="robots" content="noindex,nofollow">') !== false);
    $unknown = render_page(['title' => 'X', 'blocks' => [['type' => 'nope'], ['type' => 'html', 'html' => '<p>ok</p>', 'hidden' => true]]]);
    check('unknown and hidden blocks render nothing', strpos($unknown, '<main>') !== false && strpos($unknown, '<p>ok</p>') === false);
    check('title fallback adds brand', strpos($unknown, '<title>X | Jaibaba Interlinings</title>') !== false);

    /* ------------------------------------------------------------- routing */
    $section('routing');
    $r = get('/');
    check('/ serves home', $r['status'] === 200 && strpos($r['body'], 'Interlining Fabric That Holds Its Shape') !== false);
    check('/index.html serves home', get('/index.html')['status'] === 200);
    $r = get('/product/buckram-fabric.html');
    check('product page 200 with headers', $r['status'] === 200 && isset($r['headers']['ETag'], $r['headers']['Last-Modified']));
    check('page is cached after first view', is_file(cache_file('/product/buckram-fabric.html')));
    $p = page_load('buckram-fabric');
    $p['title'] = 'Buckram Fabric (edited)';
    page_save($p);
    check('saving clears the cache', !is_file(cache_file('/product/buckram-fabric.html')));
    check('edit is live', strpos(get('/product/buckram-fabric.html')['body'], 'Buckram Fabric (edited)') !== false);
    check('draft is 404', get('/product/draft-product.html')['status'] === 404);
    $r = get('/no-such-page.html');
    check('unknown address is a real 404', $r['status'] === 404 && strpos($r['body'], 'Page Not Found') !== false);
    check('404 page is noindex', strpos($r['body'], '<meta name="robots" content="noindex,follow">') !== false);
    check('404 page is not cached', count(glob(cache_dir() . '/*.html') ?: []) <= 1);
    $r = get('/Product/Buckram-Fabric.html?x=1');
    check('uppercase → 301 lowercase (keeps query)', $r['status'] === 301 && $r['headers']['Location'] === '/product/buckram-fabric.html?x=1');
    $r = get('/product/buckram-fabric.html/');
    check('trailing slash after .html → 301', $r['status'] === 301 && $r['headers']['Location'] === '/product/buckram-fabric.html');
    $r = get('/product/buckram-fabric');
    check('missing .html → 301', $r['status'] === 301 && $r['headers']['Location'] === '/product/buckram-fabric.html');
    $r = get('//product//buckram-fabric.html');
    check('double slashes → 301', $r['status'] === 301);
    redirects_save([['from' => '/old-buckram.html', 'to' => '/product/buckram-fabric.html', 'code' => 301], ['from' => '/removed.html', 'code' => 410]]);
    $r = get('/old-buckram.html');
    check('dashboard redirect 301', $r['status'] === 301 && $r['headers']['Location'] === '/product/buckram-fabric.html');
    check('dashboard 410', get('/removed.html')['status'] === 410);
    check('POST → 405', get('/', 'POST')['status'] === 405);
    $r = get('/', 'HEAD');
    check('HEAD has no body', $r['status'] === 200 && $r['body'] === '');
    check('.. in path → 400', get('/a/../site.json')['status'] === 400);

    config_set(['home_path' => '/']);
    cache_clear();
    $r = get('/index.html');
    check("home_path '/' : /index.html → 301 /", $r['status'] === 301 && $r['headers']['Location'] === '/');
    $r = get('/');
    check("home_path '/' : canonical is /", strpos($r['body'], 'href="https://www.jaibabananakinterlinings.com/">') !== false);
    check("home_path '/' : logo links to /", strpos($r['body'], '<a class="navbar-brand" href="/">') !== false);
    config_set(['home_path' => '/index.html']);

    // Feature switch off: static site keeps working.
    config_set(['enabled' => false]);
    $r = get('/');
    check('switched off: / serves static index.html', $r['status'] === 200 && $r['body'] === file_get_contents(ROOT_DIR . '/index.html'));
    check('switched off: static product file', get('/product/buckram-fabric.html')['body'] === file_get_contents(ROOT_DIR . '/product/buckram-fabric.html'));
    check('switched off: unknown → 404', get('/nothing.html')['status'] === 404);
    check('switched off: never serves templates or includes', get('/includes/footer.html')['status'] === 404);
    check('switched off: never serves PHP source', get('/app/config.php')['status'] === 404);
    config_set(['enabled' => true]);

    /* ------------------------------------------------------------ migration */
    $section('migration of the static site');
    require_once ROOT_DIR . '/scripts/lib/html-compare.php';
    require_once ROOT_DIR . '/scripts/lib/migrate-lib.php';
    $dirs[] = $mig = sys_get_temp_dir() . '/jaibaba-mig-' . bin2hex(random_bytes(4));
    ensure_dir($mig . '/pages');
    config_set(['content_dir' => $mig, 'cache' => false]);
    $rep = migrate_site(ROOT_DIR, $mig);
    $static = migrate_source_files(ROOT_DIR);
    check('migration reports no problems', $rep['problems'] === 0);
    check('one page per static file', count($rep['pages']) === count($static) && count(glob("$mig/pages/*.json")) === count($static));
    check('header/menu/footer identical to includes', strpos($rep['text'], 'DIFF') === false && substr_count($rep['text'], '  same  ') === 3);
    foreach ($rep['pages'] as $id => $pg) {
        check("$id: valid", page_validate($pg, []) === []);
        if ($pg['type'] === 'product') {
            $htmlBlocks = array_filter($pg['blocks'], function ($b) { return $b['type'] === 'html'; });
            check("$id: product page fully structured", !$htmlBlocks);
            check("$id: has product card", !empty($pg['card']['title']) && !empty($pg['card']['image']));
        }
    }
    $homeMig = $rep['pages']['home'];
    $siteMig = json_read("$mig/site.json");
    check('home Organization facts moved to Settings (built for every page)', ($siteMig['org']['foundingDate'] ?? '') === '1977'
        && ($siteMig['org']['address']['postalCode'] ?? '') === '110007' && count($siteMig['org']['sameAs'] ?? []) === 4
        && !array_filter($homeMig['jsonld'], function ($d) { return ($d['@type'] ?? '') === 'Organization'; }));
    check('share picture: real logo instead of the SVG placeholder', ($siteMig['defaultOgImage'] ?? '') === '/assets/images/logo.jpeg'
        && !isset($homeMig['seo']['ogImage']));
    check('hidden (commented-out) sections are kept', (bool) array_filter($homeMig['blocks'], function ($b) {
        return $b['type'] === 'html' && strpos($b['html'], '<!--') !== false && strpos($b['html'], 'STATISTICS') === false && strpos($b['html'], 'counter') !== false;
    }));
    $site = json_read("$mig/site.json");
    check('site settings: 2 phones, 5 social links, 2 addresses', count($site['phones']) === 2 && count($site['social']) === 5 && count($site['addresses']) === 2);
    check('site settings: products menu has 10 products', count(array_filter($site['nav'][3]['children'] ?? [], function ($c) {
        return strpos($c['href'] ?? '', '/product/') === 0;
    })) === 10);
    // Re-running gives the same content (apart from timestamps).
    $strip = function ($p) { unset($p['created'], $p['updated']); return $p; };
    $first = array_map($strip, $rep['pages']);
    rm_tree($mig);
    ensure_dir($mig . '/pages');
    $rep2 = migrate_site(ROOT_DIR, $mig);
    check('migration is repeatable', array_map($strip, $rep2['pages']) === $first);
    // Every migrated page renders through the router with one H1.
    config_set(['enabled' => true]);
    foreach ($rep2['pages'] as $id => $pg) {
        $resp = get($pg['path']);
        check("$id: renders 200 with one H1", $resp['status'] === 200 && preg_match_all('/<h1[\s>]/', $resp['body']) === 1);
    }
    $chunks = migrate_split_main("<main>\n  <!-- LABEL -->\n  <!-- <section>\n  hidden\n  </section> -->\n  <section class=\"x\">\n    <p>a</p>\n  </section>\n</main>", 't');
    check('splitter: label skipped, comment kept, section found', array_column($chunks, 'kind') === ['label', 'comment', 'element']);

    /* --------------------------------------------------------------- parity */
    $section('parity: static pages vs PHP site');
    require_once ROOT_DIR . '/scripts/lib/parity.php';
    config_set(['content_dir' => $mig, 'enabled' => true, 'cache' => false]);
    $par = parity_run(ROOT_DIR);
    check("all {$par['total']} pages identical to the static site", $par['total'] === count($static) && $par['identical'] === $par['total']);
    // The checker must notice real changes.
    $mutate = function (string $id, callable $fn) use ($mig) {
        $p = json_read("$mig/pages/$id.json");
        $fn($p);
        json_write("$mig/pages/$id.json", $p);
    };
    $mutate('about', function (&$p) { $p['seo']['title'] .= ' X'; });
    $mutate('tatron', function (&$p) { $p['seo']['description'] = 'changed'; });
    $sm = json_read("$mig/site.json");
    $sm['org']['foundingDate'] = '1978';
    json_write("$mig/site.json", $sm);
    clearstatcache();
    $mutate('eva-foam', function (&$p) { $p['blocks'][1]['imageAlt'] = 'changed'; });
    $mutate('quality', function (&$p) { $p['blocks'][1]['bg'] = 'bg-soft'; });
    $mutate('belt-roll', function (&$p) {
        foreach ($p['blocks'] as &$b) {
            if ($b['type'] === 'cta') {
                $b['buttons'][0]['href'] = '/elsewhere.html';
            }
        }
    });
    index_rebuild();
    $par = parity_run(ROOT_DIR);
    $has = function ($path, $check) use ($par) {
        return isset($par['pages'][$path][$check]);
    };
    check('parity notices a changed title', $has('/about.html', 'title'));
    check('parity notices a changed description', $has('/product/tatron.html', 'description'));
    check('parity notices changed JSON-LD', $has('/index.html', 'JSON-LD'));
    check('parity notices a changed image alt', $has('/product/eva-foam.html', 'images'));
    check('parity notices a changed CSS class', $has('/quality.html', 'body markup'));
    check('parity notices a changed link', $has('/product/belt-roll.html', 'links'));
    check('parity leaves untouched pages alone', $par['identical'] === $par['total'] - 6);

    /* ------------------------------------------------------------ dashboard */
    $section('dashboard: security');
    require_once APP_DIR . '/security.php';
    require_once APP_DIR . '/sanitize.php';
    require_once APP_DIR . '/media.php';
    require_once APP_DIR . '/admin_fields.php';
    require_once APP_DIR . '/admin_form.php';
    $dirs[] = $sec = fresh_content();
    check('no password at first', !password_is_set());
    check('short password rejected', password_problem('short', 'short') !== null);
    check('mismatch rejected', password_problem('long enough pw', 'long enough pX') !== null);
    $tok = setup_token_create();
    check('setup token valid once created', setup_token_valid($tok));
    check('wrong setup token rejected', !setup_token_valid('nope') && !setup_token_valid(''));
    password_set('correct horse battery');
    check('password set and checked', password_is_set() && password_check('correct horse battery') && !password_check('wrong'));
    check('password file not readable by others', (fileperms(content_path('.auth.json')) & 0077) === 0);
    check('password stored as a hash', strpos((string) file_get_contents(content_path('.auth.json')), 'correct horse') === false);
    check('setup token dead after password set', !setup_token_valid($tok));
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
    for ($i = 0; $i < LOGIN_MAX_FAILURES - 1; $i++) {
        login_record_failure();
    }
    check('not locked before 5 failures', login_locked_for() === 0);
    login_record_failure();
    check('locked after 5 failures', login_locked_for() > 800);
    $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    check('other visitors not locked', login_locked_for() === 0);
    login_clear_failures();
    check('csrf: missing / wrong token rejected', !csrf_valid(null) && !csrf_valid('x'));
    check('csrf: right token accepted', csrf_valid(csrf_token()));

    $section('dashboard: HTML cleaning');
    $s = sanitize_html('<p onclick="x()">Hi <script>alert(1)</script><a href="javascript:alert(1)">a</a><a href="/ok.html" target="_blank">b</a><img src="x" onerror="y"><iframe src="https://evil.example/"></iframe><iframe src="https://www.google.com/maps?q=x&output=embed"></iframe><span style="background:url(javascript:x)">s</span></p>');
    check('script removed', stripos($s, 'script') === false && stripos($s, 'alert') === false);
    check('on* attributes removed', stripos($s, 'onclick') === false && stripos($s, 'onerror') === false);
    check('javascript: links removed', stripos($s, 'javascript') === false);
    check('new-tab links get rel=noopener', strpos($s, 'href="/ok.html" target="_blank" rel="noopener"') !== false);
    check('only allowed iframes kept', strpos($s, 'evil.example') === false && strpos($s, 'www.google.com/maps') !== false);
    check('dangerous inline style removed', strpos($s, 'style=') === false);
    check('site markup kept (data-aos, classes, form)', sanitize_html('<div class="col-lg-6" data-aos="fade-up" data-aos-delay="100"><form id="f" novalidate><input type="tel" pattern="[0-9]{10}" required></form></div>')
        === '<div class="col-lg-6" data-aos="fade-up" data-aos-delay="100"><form id="f" novalidate><input type="tel" pattern="[0-9]{10}" required></form></div>');
    $htmlValues = 0;
    $changed = 0;
    foreach (glob("$mig/pages/*.json") as $f) {
        $pgData = json_read($f);
        array_walk_recursive($pgData, function ($v) use (&$htmlValues, &$changed) {
            if (is_string($v) && strpos($v, '<') !== false) {
                $htmlValues++;
                $changed += token_diff(fragment_tokens($v), fragment_tokens(sanitize_html($v))) === null ? 0 : 1;
            }
        });
    }
    check("cleaning keeps all $htmlValues HTML values of the site unchanged", $htmlValues > 50 && $changed === 0);

    $section('dashboard: editor form round trip (save without changes = same website)');
    config_set(['content_dir' => $mig, 'enabled' => true, 'cache' => false]);
    rm_tree($mig);
    ensure_dir($mig . '/pages');
    migrate_site(ROOT_DIR, $mig);
    $post_from_form = function (string $html): array {
        $doc = dom_fragment($html);
        $x = new DOMXPath($doc);
        $pairs = [];
        foreach ($x->query('//input[@name] | //textarea[@name] | //select[@name]') as $el) {
            $n = $el->getAttribute('name');
            if ($el->nodeName === 'input') {
                $t = strtolower($el->getAttribute('type') ?: 'text');
                if ($t === 'checkbox' && !$el->hasAttribute('checked')) {
                    continue;
                }
                $pairs[] = rawurlencode($n) . '=' . rawurlencode($el->getAttribute('value'));
            } elseif ($el->nodeName === 'textarea') {
                $pairs[] = rawurlencode($n) . '=' . rawurlencode($el->textContent);
            } else {
                $sel = $x->query('.//option[@selected]', $el)->item(0) ?? $x->query('.//option', $el)->item(0);
                $pairs[] = rawurlencode($n) . '=' . rawurlencode($sel ? $sel->getAttribute('value') : '');
            }
        }
        parse_str(implode('&', $pairs), $out);
        return $out;
    };
    $roundOk = 0;
    $total = 0;
    foreach (pages_all() as $pid => $pg) {
        $total++;
        $html = form_fields(page_fields($pg['type']), $pg, ['page']);
        foreach (array_values($pg['blocks']) as $i => $b) {
            $html .= form_block($b, $i);
        }
        $errs = [];
        $back = form_collect_page($post_from_form($html), $pg, $errs);
        $a = parity_extract(render_page($pg));
        $b2 = parity_extract(render_page($back));
        $diff = parity_compare($a, $b2);
        if (!$errs && !$diff) {
            $roundOk++;
        } else {
            echo "      $pid: " . implode('; ', $errs) . ' ' . implode(' | ', array_map(function ($k, $v) { return "$k: $v"; }, array_keys($diff), $diff)) . "\n";
        }
    }
    check("all $total pages: editor form → save renders the identical page", $roundOk === $total);
    $site0 = site_load();
    $errs = [];
    $siteBack = form_collect(settings_fields(), $post_from_form(form_fields(settings_fields(), $site0, ['site']))['site'] ?? [], $site0, $errs);
    $partials = function ($s) {
        return render_template('partials/topbar', ['site' => $s]) . render_template('partials/navbar', ['site' => $s, 'page' => []]) . render_template('partials/footer', ['site' => $s]);
    };
    check('settings form → save renders identical header, menu and footer', !$errs && token_diff(fragment_tokens($partials($site0)), fragment_tokens($partials($siteBack))) === null);
    // Edits do arrive.
    $pg = page_load('tatron');
    $html = form_fields(page_fields('product'), $pg, ['page']) . implode('', array_map('form_block', $pg['blocks'], array_keys($pg['blocks'])));
    $post = $post_from_form($html);
    $post['page']['seo']['title'] = 'New <b>title</b>';
    $post['blocks'][1]['html'] = '<p>Hello <script>x()</script><strong>world</strong></p>';
    $post['blocks'][2]['hidden'] = '1';
    $errs = [];
    $edited = form_collect_page($post, $pg, $errs);
    check('edited text field saved as plain text', $edited['seo']['title'] === 'New <b>title</b>');
    check('edited rich text cleaned', $edited['blocks'][1]['html'] === '<p>Hello <strong>world</strong></p>');
    check('hide a section', !empty($edited['blocks'][2]['hidden'])
        && substr_count(render_blocks($edited, site_load()), '<section') === substr_count(render_blocks($pg, site_load()), '<section') - 1);
    check('text is escaped on the page', strpos(render_page($edited), '<title>New &lt;b&gt;title&lt;/b&gt;</title>') !== false);
    $post['page']['jsonld'] = '{not json';
    $errs = [];
    form_collect_page($post, $pg, $errs);
    check('broken JSON is reported, not saved', count($errs) === 1);
    $post['page']['jsonld'] = '[]';
    $post['page']['path'] = '/product/tatron-lining.html';
    $errs = [];
    [$saved, $msgs] = page_save_from_editor(form_collect_page($post, $pg, $errs), $pg);
    check('renaming a live page creates a 301 from the old address', redirect_find('/product/tatron.html')['to'] === '/product/tatron-lining.html' && $msgs);
    check('the new address works, the old one redirects', get('/product/tatron-lining.html')['status'] === 200 && get('/product/tatron.html')['status'] === 301);
    $post['page']['path'] = '/product/tatron-fabric.html';
    page_save_from_editor(form_collect_page($post, $saved, $errs), $saved);
    check('renaming again updates the old redirect (no chains)', redirect_find('/product/tatron.html')['to'] === '/product/tatron-fabric.html'
        && redirect_find('/product/tatron-lining.html')['to'] === '/product/tatron-fabric.html');
    $post['page']['path'] = '/product/tatron.html';
    [$back3] = page_save_from_editor(form_collect_page($post, page_load('tatron'), $errs), page_load('tatron'));
    check('moving back to the original address removes its redirect', redirect_find('/product/tatron.html') === null && get('/product/tatron.html')['status'] === 200);

    $section('dashboard: pictures');
    $dirs[] = $up = sys_get_temp_dir() . '/jaibaba-up-' . bin2hex(random_bytes(3));
    config_set(['uploads_dir' => $up, 'uploads_url' => 'uploads']);
    $png = sys_get_temp_dir() . '/jb-test-' . bin2hex(random_bytes(3)) . '.png';
    $im = imagecreatetruecolor(2400, 10);
    imagepng($im, $png);
    $url = media_upload(['name' => 'My Photo!.png', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => filesize($png)]);
    check('upload saved with a safe name', preg_match('#^/uploads/\d{4}/\d{2}/my-photo-[0-9a-f]{6}\.png$#', $url) === 1);
    check('wide picture made smaller', getimagesize($up . substr($url, strlen('/uploads')))[0] === MEDIA_MAX_WIDTH);
    check('uploads folder blocks scripts', strpos((string) file_get_contents("$up/.htaccess"), 'Require all denied') !== false);
    $fake = sys_get_temp_dir() . '/jb-test-' . bin2hex(random_bytes(3)) . '.jpg';
    file_put_contents($fake, '<?php echo 1;');
    try {
        media_upload(['name' => 'shell.jpg', 'tmp_name' => $fake, 'error' => UPLOAD_ERR_OK, 'size' => 13]);
        check('non-picture upload refused', false);
    } catch (ContentError $e) {
        check('non-picture upload refused', true);
    }
    check('uploaded picture listed', (bool) array_filter(media_list(), function ($m) use ($url) { return $m['url'] === $url; }));
    $pg = page_load('about');
    $pg['seo']['ogImage'] = $url;
    page_save($pg);
    try {
        media_delete($url);
        check('used picture cannot be deleted', false);
    } catch (ContentError $e) {
        check('used picture cannot be deleted', true);
    }
    $pg['seo']['ogImage'] = '';
    page_save($pg);
    media_delete($url);
    check('unused upload deleted', !is_file($up . substr($url, strlen('/uploads'))));
    try {
        media_delete('/assets/images/logo.jpeg');
        check('site pictures cannot be deleted', false);
    } catch (ContentError $e) {
        check('site pictures cannot be deleted', is_file(ROOT_DIR . '/assets/images/logo.jpeg'));
    }
    @unlink($png);
    @unlink($fake);
    config_set(['uploads_dir' => null, 'uploads_url' => null]);

    /* ------------------------------------------------------------------ SEO */
    $section('SEO, AEO, GEO');
    $dirs[] = $seoDir = sys_get_temp_dir() . '/jaibaba-seo-' . bin2hex(random_bytes(4));
    ensure_dir("$seoDir/pages");
    config_set(['content_dir' => $seoDir, 'enabled' => true, 'cache' => false]);
    migrate_site(ROOT_DIR, $seoDir);
    $sm = get('/sitemap.xml');
    $xml = @simplexml_load_string($sm['body']);
    check('sitemap.xml is valid XML', $sm['status'] === 200 && $xml !== false && strpos($sm['headers']['Content-Type'], 'xml') !== false);
    check('sitemap lists all 18 live pages with lastmod', $xml && count($xml->url) === 18 && (string) $xml->url[0]->lastmod !== '');
    $p = page_load('quality');
    $p['seo']['noindex'] = true;
    page_save($p);
    $p = page_load('tatron');
    $p['status'] = 'draft';
    page_save($p);
    $xml = simplexml_load_string(get('/sitemap.xml')['body']);
    $locs = array_map('strval', iterator_to_array($xml->url, false) ? array_map(function ($u) { return $u->loc; }, iterator_to_array($xml->url, false)) : []);
    check('hidden-from-Google and draft pages left out of the sitemap', count($locs) === 16 && !preg_grep('/quality|tatron/', $locs));
    check('noindex page says so in its robots tag', strpos(get('/quality.html')['body'], '<meta name="robots" content="noindex,follow">') !== false);
    $rb = get('/robots.txt');
    check('robots.txt allows the site, blocks /admin/, lists the sitemap', $rb['status'] === 200 && strpos($rb['body'], "Disallow: /admin/") !== false
        && strpos($rb['body'], 'Sitemap: https://www.jaibabananakinterlinings.com/sitemap.xml') !== false && strpos($rb['body'], 'GPTBot') === false);
    $s = site_load();
    $s['robots']['blockAI'] = true;
    site_save($s);
    check('blocking AI tools adds them to robots.txt', strpos(get('/robots.txt')['body'], "User-agent: GPTBot\nDisallow: /") !== false);
    $s['robots']['blockAI'] = false;
    site_save($s);
    $ll = get('/llms.txt')['body'];
    check('llms.txt: name, summary, products, contact', strpos($ll, '# Jaibaba Interlinings') === 0 && strpos($ll, '> Manufacturer, supplier') !== false
        && strpos($ll, '## Products') !== false && strpos($ll, '[Buckram Fabric](https://www.jaibabananakinterlinings.com/product/buckram-fabric.html)') !== false
        && strpos($ll, 'Phone: +91 9910555033') !== false && strpos($ll, '[Tatron](') === false);
    $graphOf = function (string $path) {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', get($path)['body'], $m);
        $d = json_decode($m[1] ?? '', true);
        $types = [];
        foreach ($d['@graph'] ?? [] as $n) {
            $types[$n['@type']] = $n;
        }
        return $types;
    };
    $g = $graphOf('/product/buckram-fabric.html');
    check('product page graph: Organization, WebSite, WebPage, BreadcrumbList, FAQPage', array_keys($g) == ['Organization', 'WebSite', 'BreadcrumbList', 'WebPage', 'FAQPage']);
    check('breadcrumb schema matches the visible breadcrumb', array_column($g['BreadcrumbList']['itemListElement'], 'name') === ['Home', 'Products', 'Buckram Fabric']);
    check('FAQ schema built from the visible questions', count($g['FAQPage']['mainEntity']) === 4 && $g['FAQPage']['mainEntity'][0]['name'] === 'What widths does buckram come in?');
    check('no fake ratings or prices anywhere', strpos(json_encode($g), 'aggregateRating') === false && strpos(json_encode($g), '"offers"') === false);
    check('organisation facts: address, phone, founding year', $g['Organization']['address']['postalCode'] === '110007' && $g['Organization']['foundingDate'] === '1977' && $g['Organization']['telephone'] === '+919910555033');
    check('each social profile once, without tracking parameters', count($g['Organization']['sameAs']) === 4 && !preg_grep('/\?/', $g['Organization']['sameAs']));
    $p = page_load('buckram-fabric');
    foreach ($p['blocks'] as &$b) {
        if ($b['type'] === 'faq') {
            $b['hidden'] = true;
        }
    }
    unset($b);
    page_save($p);
    check('hidden questions → no FAQ schema', !isset($graphOf('/product/buckram-fabric.html')['FAQPage']));
    $g = $graphOf('/products.html');
    check('product list: CollectionPage with an ItemList of the live products (draft left out)', isset($g['CollectionPage']) && $g['CollectionPage']['mainEntity']['numberOfItems'] === 9);
    check('home: no breadcrumb schema; about page is an AboutPage', !isset($graphOf('/index.html')['BreadcrumbList']) && isset($graphOf('/about.html')['AboutPage']) && isset($graphOf('/contact.html')['ContactPage']));
    $h = get('/about.html')['body'];
    check('og:url, og:site_name and Twitter tags', strpos($h, '<meta property="og:url" content="https://www.jaibabananakinterlinings.com/about.html">') !== false
        && strpos($h, '<meta property="og:site_name" content="Jaibaba Interlinings">') !== false && strpos($h, '<meta name="twitter:card"') !== false);
    check('share picture is a full address with its size', strpos($h, '<meta property="og:image" content="https://www.jaibabananakinterlinings.com/assets/images/logo.jpeg">') !== false
        && strpos($h, '<meta property="og:image:width" content="1250">') !== false);
    check('every local picture has width and height', preg_match_all('#<img\b(?![^>]*\swidth=)[^>]*src="/[^"]+"#', $h) === 0);
    check('no tracking tags while no IDs are set', strpos($h, 'googletagmanager') === false && strpos($h, 'fbq(') === false);
    $s = site_load();
    $s['tracking'] = ['ga4' => 'G-ABC123XYZ', 'gtm' => 'GTM-AB12CD', 'metaPixel' => '123456789012'];
    site_save($s);
    $h = get('/about.html')['body'];
    check('tracking tags on public pages once set', strpos($h, "gtag('config','G-ABC123XYZ')") !== false && strpos($h, 'GTM-AB12CD') !== false && strpos($h, "fbq('init','123456789012')") !== false);
    check('…but never in previews', strpos(render_page(page_load('about'), ['preview' => true]), 'googletagmanager') === false);
    $s['tracking'] = ['ga4' => '"><script>alert(1)</script>', 'gtm' => '', 'metaPixel' => ''];
    site_save($s);
    check('a malformed tracking ID prints nothing', strpos(get('/about.html')['body'], 'alert(1)') === false);

    $all = pages_all();
    $titles = function ($id) use ($all) {
        return array_column(seo_checks($all[$id], $all), 'text');
    };
    check('checks: title and description length', (bool) preg_grep('/Google title is 77 characters/', $titles('about')) && (bool) preg_grep('/description is \d{3} characters/', $titles('about')));
    $p = page_load('exports');
    $p['seo']['title'] = $all['about']['seo']['title'];
    $p['seo']['focusKeyword'] = 'buckram exporter';
    $p['blocks'][] = ['type' => 'html', 'html' => '<section><p><a href="/missing-page.html">x</a> <a href="/product/tatron.html">t</a> <a href="/old-catalogue.html">o</a></p></section>'];
    page_save($p);
    redirects_save([['from' => '/old-catalogue.html', 'to' => '/products.html', 'code' => 301]]);
    $q = page_load('about');
    $q['seo']['focusKeyword'] = 'Buckram Exporter';
    page_save($q);
    $all = pages_all();
    $c = implode("\n", array_column(seo_checks($all['exports'], $all), 'text'));
    check('checks: duplicate Google title', strpos($c, 'has the same Google title') !== false);
    check('checks: broken link', strpos($c, 'Broken link: /missing-page.html (page not found)') !== false);
    check('checks: link to a draft', strpos($c, 'Broken link: /product/tatron.html (goes to a draft page)') !== false);
    check('checks: link to a redirected address', strpos($c, 'The link /old-catalogue.html is redirected. Link straight to /products.html.') !== false);
    check('checks: two pages targeting one search phrase', strpos($c, 'targets the same search phrase') !== false);
    check('checks: phrase missing from title', strpos($c, 'Put the main search phrase “buckram exporter” in the Google title.') !== false);
    $p = page_load('home');
    $p['blocks'][] = ['type' => 'page-hero', 'heading' => 'Second H1'];
    page_save($p);
    check('checks: more than one main heading', (bool) preg_grep('/2 main headings/', array_column(seo_checks(page_load('home')), 'text')));
    check('site report lists problems per page', count(seo_report()) === count(pages_all()) && seo_report()['exports'] !== []);

    $fx = "$seoDir/fixes.json";
    $oldT = page_load('about')['seo']['title'];
    json_write($fx, [
        ['page' => 'about', 'field' => 'seo.title', 'from' => $oldT, 'to' => 'About Jaibaba Interlinings | Since 1977, Delhi'],
        ['page' => 'about', 'field' => 'seo.description', 'from' => 'not the current text', 'to' => 'x'],
        ['page' => 'about', 'field' => 'path', 'from' => '/about.html', 'to' => '/x.html'],
    ]);
    $dry = seo_apply_fixes($fx, false);
    check('bulk fixes: dry run changes nothing', page_load('about')['seo']['title'] === $oldT && strpos($dry[0], 'would fix') === 0);
    $n = count(history_list('about'));
    $res = seo_apply_fixes($fx, true);
    check('bulk fixes: applied only where the old text still matches', page_load('about')['seo']['title'] === 'About Jaibaba Interlinings | Since 1977, Delhi'
        && strpos($res[1], 'skip') === 0 && strpos($res[2], 'skip') === 0 && page_load('about')['path'] === '/about.html');
    check('bulk fixes: saved with history', count(history_list('about')) === $n + 1);

    /* ------------------------------------------------------- whole fixtures */
    config_set(['content_dir' => $dirs[1], 'enabled' => true]);
    $section('every fixture page renders');
    foreach (pages_all() as $id => $page) {
        if (($page['status'] ?? '') !== 'published') {
            continue;
        }
        $html = get($page['path'])['body'];
        check("$id: 200 and one H1", preg_match_all('/<h1[\s>]/', $html) === 1);
        check("$id: canonical present", strpos($html, 'rel="canonical"') !== false);
    }
} catch (Throwable $t) {
    check('unexpected error: ' . get_class($t) . ': ' . $t->getMessage() . ' at ' . $t->getFile() . ':' . $t->getLine(), false);
} finally {
    foreach ($dirs as $d) {
        rm_tree($d);
    }
}

$failed = count($GLOBALS['failed']);
echo "\n{$GLOBALS['passed']} passed, $failed failed\n";
exit($failed ? 1 : 0);

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
    check('absolute_url', absolute_url('/about.html') === 'https://www.jaibabainterlinings.com/about.html');
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
    check('home canonical is /index.html', strpos($home, '<link rel="canonical" href="https://www.jaibabainterlinings.com/index.html">') !== false);
    check('no robots meta on published page', strpos($home, 'name="robots"') === false);
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $home, $m);
    $ld = json_decode($m[1][0] ?? '', true);
    check('JSON-LD parses', is_array($ld) && $ld['@type'] === 'Organization');
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
    check("home_path '/' : canonical is /", strpos($r['body'], 'href="https://www.jaibabainterlinings.com/">') !== false);
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
    check('home keeps Organization JSON-LD', ($homeMig['jsonld'][0]['@type'] ?? '') === 'Organization');
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

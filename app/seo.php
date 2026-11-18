<?php
/**
 * SEO, AEO and GEO, generated from the content so editors can't break it:
 *   - the JSON-LD graph of every page (Organization, WebSite, WebPage, BreadcrumbList,
 *     FAQPage, ItemList)
 *   - sitemap.xml, robots.txt, llms.txt
 *   - internal link checks and the per-page SEO checks shown in the dashboard
 *   - the site-wide report and the bulk-fix tool (CLI)
 *
 * Nothing here invents facts: everything comes from site.json and the pages.
 */

const AI_CRAWLERS = ['GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'anthropic-ai', 'PerplexityBot', 'Google-Extended', 'CCBot'];

function seo_indexable(array $page): bool
{
    if (($page['status'] ?? '') !== 'published' || !empty($page['seo']['noindex'])) {
        return false;
    }
    $canon = trim((string) ($page['seo']['canonical'] ?? ''));
    return $canon === '' || absolute_url($canon) === absolute_url(page_canonical_path($page));
}

/** [width, height] of a picture on this site, or null. */
function local_image_size(string $url): ?array
{
    static $memo = [];
    if (!preg_match('~^/(?!/)[^?#]+\.(jpe?g|png|webp|gif)$~i', $url)) {
        return null;
    }
    if (!array_key_exists($url, $memo)) {
        $file = ROOT_DIR . $url;
        if (strpos($url, uploads_url() . '/') === 0) {
            $file = uploads_dir() . substr($url, strlen(uploads_url()));
        }
        $real = realpath($file);
        $inSite = false;
        foreach ([ROOT_DIR, uploads_dir()] as $dir) {
            $d = realpath($dir);
            if ($real && $d && strpos($real, $d . DIRECTORY_SEPARATOR) === 0) {
                $inSite = true;
            }
        }
        $size = $inSite ? @getimagesize($real) : false;
        $memo[$url] = $size ? [(int) $size[0], (int) $size[1]] : null;
    }
    return $memo[$url];
}

/**
 * Give local <img> tags width/height attributes (from the real file) so the page doesn't
 * jump while pictures load. The site CSS has "img { height: auto }", so nothing is stretched.
 */
function add_image_dimensions(string $html): string
{
    return (string) preg_replace_callback('#<img\b([^>]*)>#i', function ($m) {
        $attrs = $m[1];
        if (preg_match('/\swidth\s*=/i', $attrs) || !preg_match('/\ssrc="([^"]+)"/i', $attrs, $s)) {
            return $m[0];
        }
        $size = local_image_size(html_entity_decode($s[1]));
        if (!$size) {
            return $m[0];
        }
        $close = substr(rtrim($attrs), -1) === '/' ? ' /' : '';
        $attrs = rtrim(rtrim($attrs), '/');
        return '<img' . $attrs . ' width="' . $size[0] . '" height="' . $size[1] . '"' . $close . '>';
    }, $html);
}

/* =============================================================== JSON-LD */

function seo_image_object(string $src): ?array
{
    if ($src === '') {
        return null;
    }
    $o = ['@type' => 'ImageObject', 'url' => absolute_url($src)];
    if ($size = local_image_size($src)) {
        $o['width'] = $size[0];
        $o['height'] = $size[1];
    }
    return $o;
}

function seo_organization(array $site): array
{
    $base = config('base_url');
    $org = $site['org'] ?? [];
    $node = [
        '@type' => 'Organization',
        '@id' => $base . '/#organization',
        'name' => (string) ($site['name'] ?? ''),
        'url' => $base . '/',
    ];
    if (!empty($site['brand']) && $site['brand'] !== ($site['name'] ?? '')) {
        $node['alternateName'] = $site['brand'];
    }
    if ($logo = seo_image_object((string) ($site['logo']['src'] ?? ''))) {
        $node['logo'] = $logo;
        $node['image'] = $logo['url'];
    }
    foreach (['description', 'foundingDate'] as $k) {
        if (trim((string) ($org[$k] ?? '')) !== '') {
            $node[$k] = trim((string) $org[$k]);
        }
    }
    $addr = array_filter((array) ($org['address'] ?? []), function ($v) {
        return is_string($v) && trim($v) !== '';
    });
    if ($addr) {
        $node['address'] = ['@type' => 'PostalAddress'] + $addr;
    }
    $phones = array_values(array_filter(array_map(function ($p) {
        return (string) ($p['tel'] ?? '');
    }, $site['phones'] ?? [])));
    if ($phones) {
        $node['telephone'] = $phones[0];
    }
    if (!empty($site['email'])) {
        $node['email'] = $site['email'];
    }
    if ($phones || !empty($site['email'])) {
        $cp = ['@type' => 'ContactPoint', 'contactType' => 'sales'];
        if ($phones) {
            $cp['telephone'] = $phones;
        }
        if (!empty($site['email'])) {
            $cp['email'] = $site['email'];
        }
        $node['contactPoint'] = [$cp];
    }
    // Official profiles: the list in Settings, plus the social links (without tracking
    // parameters), each profile once.
    $same = [];
    $seen = [];
    $candidates = array_merge((array) ($org['sameAs'] ?? []), array_map(function ($s) {
        $u = (string) ($s['url'] ?? '');
        return preg_match('#^https://(www\.)?(facebook|instagram|x|twitter|youtube|linkedin)\.com/#', $u) ? strtok($u, '?') : '';
    }, $site['social'] ?? []));
    foreach ($candidates as $u) {
        if (!is_string($u) || !preg_match('#^https?://#', $u)) {
            continue;
        }
        $key = strtolower(rtrim((string) preg_replace('#^https?://(www\.)?#', '', strtok($u, '?')), '/'));
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $same[] = $u;
        }
    }
    if ($same) {
        $node['sameAs'] = $same;
    }
    return $node;
}

/** Visible FAQ items of a page (hidden sections don't count). */
function seo_faq_items(array $page): array
{
    $items = [];
    foreach ($page['blocks'] ?? [] as $b) {
        if (($b['type'] ?? '') === 'faq' && empty($b['hidden'])) {
            foreach ($b['items'] ?? [] as $it) {
                $q = trim((string) ($it['q'] ?? ''));
                $a = trim(collapse_space(strip_tags((string) ($it['a'] ?? ''))));
                if ($q !== '' && $a !== '') {
                    $items[] = ['q' => $q, 'a' => html_entity_decode($a, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
                }
            }
        }
    }
    return $items;
}

function collapse_space(string $s): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $s));
}

/** The page's main picture: the top banner image, else the share picture. */
function seo_page_image(array $page, array $site): string
{
    foreach ($page['blocks'] ?? [] as $b) {
        if (($b['type'] ?? '') === 'page-hero' && empty($b['hidden']) && !empty($b['image'])) {
            return (string) $b['image'];
        }
    }
    if (($page['type'] ?? '') === 'product' && !empty($page['card']['image'])) {
        return (string) $page['card']['image'];
    }
    return (string) (($page['seo']['ogImage'] ?? '') ?: ($site['defaultOgImage'] ?? ''));
}

function seo_has_breadcrumb(array $page): bool
{
    foreach ($page['blocks'] ?? [] as $b) {
        if (($b['type'] ?? '') === 'page-hero' && empty($b['hidden']) && ($b['breadcrumb'] ?? true)) {
            return count(breadcrumb_items($page)) > 1;
        }
    }
    return false;
}

/** The full JSON-LD graph for a page. */
function seo_graph(array $page, array $site, array $head): array
{
    $base = config('base_url');
    $url = $head['canonical'];
    $type = (string) ($page['type'] ?? 'page');
    $pageType = ['about' => 'AboutPage', 'contact' => 'ContactPage', 'listing' => 'CollectionPage'][$type] ?? 'WebPage';
    $graph = [seo_organization($site)];
    $graph[] = [
        '@type' => 'WebSite', '@id' => $base . '/#website', 'url' => $base . '/',
        'name' => (string) (($site['brand'] ?? '') ?: ($site['name'] ?? '')),
        'publisher' => ['@id' => $base . '/#organization'], 'inLanguage' => $head['lang'],
    ];
    $wp = [
        '@type' => $pageType, '@id' => $url . '#webpage', 'url' => $url, 'name' => $head['title'],
        'isPartOf' => ['@id' => $base . '/#website'], 'inLanguage' => $head['lang'],
    ];
    if ($head['description'] !== '') {
        $wp['description'] = $head['description'];
    }
    if (in_array($type, ['home', 'about', 'contact'], true)) {
        $wp['about'] = ['@id' => $base . '/#organization'];
    }
    if ($img = seo_image_object(seo_page_image($page, $site))) {
        $wp['primaryImageOfPage'] = $img;
    }
    if (!empty($page['updated']) && ($t = strtotime((string) $page['updated']))) {
        $wp['dateModified'] = date('c', $t);
    }
    if (seo_has_breadcrumb($page)) {
        $wp['breadcrumb'] = ['@id' => $url . '#breadcrumb'];
        $items = [];
        foreach (breadcrumb_items($page) as $i => $c) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['label'], 'item' => $c['href'] !== '' ? absolute_url($c['href']) : $url];
        }
        $graph[] = ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $items];
    }
    if ($type === 'listing') {
        $list = [];
        foreach ($page['blocks'] ?? [] as $b) {
            if (($b['type'] ?? '') === 'product-cards' && empty($b['hidden'])) {
                foreach ($b['items'] ?? [] as $ref) {
                    $card = is_array($ref) ? product_card($ref) : null;
                    if ($card && !isset($list[$card['href']])) {
                        $list[$card['href']] = $card['title'];
                    }
                }
            }
        }
        if ($list) {
            $n = 0;
            $wp['mainEntity'] = ['@type' => 'ItemList', 'numberOfItems' => count($list), 'itemListElement' => array_map(function ($href, $name) use (&$n) {
                return ['@type' => 'ListItem', 'position' => ++$n, 'url' => absolute_url($href), 'name' => $name];
            }, array_keys($list), $list)];
        }
    }
    $graph[] = $wp;
    $faq = seo_faq_items($page);
    if ($faq) {
        $graph[] = [
            '@type' => 'FAQPage', '@id' => $url . '#faq', 'url' => $url, 'isPartOf' => ['@id' => $url . '#webpage'],
            'mainEntity' => array_map(function ($it) {
                return ['@type' => 'Question', 'name' => $it['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $it['a']]];
            }, $faq),
        ];
    }
    return ['@context' => 'https://schema.org', '@graph' => $graph];
}

/* ===================================================== generated files */

function seo_sitemap_xml(): string
{
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (pages_all() as $p) {
        if (!seo_indexable($p)) {
            continue;
        }
        $out .= '  <url><loc>' . htmlspecialchars(absolute_url(page_canonical_path($p)), ENT_XML1) . '</loc>';
        if (!empty($p['updated']) && ($t = strtotime((string) $p['updated']))) {
            $out .= '<lastmod>' . date('Y-m-d', $t) . '</lastmod>';
        }
        $out .= "</url>\n";
    }
    return $out . "</urlset>\n";
}

function seo_robots_txt(): string
{
    $site = site_load();
    $out = "# Made by the website. Change the AI setting in the dashboard (Settings).\n";
    $out .= "User-agent: *\nAllow: /\nDisallow: /admin/\n";
    if (!empty($site['robots']['blockAI'])) {
        foreach (AI_CRAWLERS as $bot) {
            $out .= "\nUser-agent: $bot\nDisallow: /\n";
        }
    }
    return $out . "\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
}

/** llms.txt: a plain summary of the business and its key pages for AI tools (llmstxt.org). */
function seo_llms_txt(): string
{
    $site = site_load();
    $brand = (string) (($site['brand'] ?? '') ?: ($site['name'] ?? ''));
    $org = $site['org'] ?? [];
    $out = '# ' . $brand . "\n\n";
    if (!empty($org['description'])) {
        $out .= '> ' . collapse_space((string) $org['description']) . "\n\n";
    }
    $facts = [];
    if (!empty($site['name']) && $site['name'] !== $brand) {
        $facts[] = 'Legal name: ' . rtrim((string) $site['name'], '.') . '.';
    }
    if (!empty($org['foundingDate'])) {
        $facts[] = 'Founded: ' . $org['foundingDate'] . '.';
    }
    $city = trim(implode(', ', array_filter([(string) ($org['address']['addressLocality'] ?? ''), (string) ($org['address']['addressCountry'] ?? '') === 'IN' ? 'India' : ''])));
    if ($city !== '') {
        $facts[] = 'Based in ' . $city . '.';
    }
    if ($facts) {
        $out .= implode(' ', $facts) . "\n\n";
    }
    $groups = ['product' => 'Products', 'listing' => 'Products', 'home' => 'Company', 'about' => 'Company', 'page' => 'Company', 'contact' => 'Contact'];
    $sections = [];
    foreach (pages_all() as $p) {
        if (!seo_indexable($p)) {
            continue;
        }
        $title = (string) (($p['type'] ?? '') === 'product' ? ($p['title'] ?? '') : (($p['seo']['title'] ?? '') ?: ($p['title'] ?? '')));
        $desc = collapse_space((string) ($p['seo']['description'] ?? ''));
        $sections[$groups[$p['type']] ?? 'Company'][] = '- [' . $title . '](' . absolute_url(page_canonical_path($p)) . ')' . ($desc !== '' ? ': ' . $desc : '');
    }
    foreach (['Products', 'Company', 'Contact'] as $g) {
        if (!empty($sections[$g])) {
            $out .= "## $g\n\n" . implode("\n", $sections[$g]) . "\n\n";
        }
    }
    $contact = [];
    foreach ($site['phones'] ?? [] as $ph) {
        $contact[] = '- Phone: ' . ($ph['display'] ?? '');
    }
    if (!empty($site['email'])) {
        $contact[] = '- Email: ' . $site['email'];
    }
    if ($wa = whatsapp_url($site)) {
        $contact[] = '- WhatsApp: ' . $wa;
    }
    foreach ($site['addresses'] ?? [] as $a) {
        $contact[] = '- ' . (($a['label'] ?? '') !== '' ? $a['label'] . ': ' : 'Address: ') . ($a['text'] ?? '');
    }
    if (!empty($site['hours'])) {
        $contact[] = '- Hours: ' . $site['hours'];
    }
    if ($contact) {
        $out .= "## Contact details\n\n" . implode("\n", $contact) . "\n";
    }
    return $out;
}

/* ============================================================== link check */

/**
 * Status of a link on this site: ['ok'] | ['broken', why] | ['redirect', finalTarget] | ['external'].
 */
function seo_link_status(string $href, ?array $index = null): array
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $base = config('base_url');
    if (stripos($href, $base . '/') === 0 || $href === $base) {
        $href = substr($href, strlen($base)) ?: '/';
    }
    if ($href === '' || $href[0] === '#' || preg_match('#^(mailto:|tel:|javascript:|data:)#i', $href)) {
        return ['ok'];
    }
    if (preg_match('#^(https?:)?//#i', $href)) {
        return ['external'];
    }
    if ($href[0] !== '/') {
        return ['ok']; // relative link inside a page: not checked
    }
    $path = (string) parse_url($href, PHP_URL_PATH);
    $path = rawurldecode($path === '' ? '/' : $path);
    $index = $index ?? index_get()['paths'];
    $lookup = $path === '/' ? '/index.html' : $path;
    if (isset($index[$lookup])) {
        if ($index[$lookup]['status'] !== 'published') {
            return ['broken', 'goes to a draft page'];
        }
        if ($path === '/index.html' && config('home_path') === '/') {
            return ['redirect', '/'];
        }
        return ['ok'];
    }
    $r = redirect_find($path);
    if ($r) {
        return (int) $r['code'] === 410 ? ['broken', 'goes to an address marked as gone'] : ['redirect', (string) $r['to']];
    }
    if (in_array($path, ['/sitemap.xml', '/robots.txt', '/llms.txt'], true)) {
        return ['ok'];
    }
    $file = realpath(ROOT_DIR . $path);
    if ($file && is_file($file) && strpos($file, (string) realpath(ROOT_DIR)) === 0) {
        return ['ok'];
    }
    if (strpos($path, uploads_url() . '/') === 0 && is_file(uploads_dir() . substr($path, strlen(uploads_url())))) {
        return ['ok'];
    }
    return ['broken', 'page not found'];
}

/** Links found in the page's own sections (not the shared menu/footer). */
function seo_page_links(array $page): array
{
    $html = render_blocks($page, site_load());
    preg_match_all('#<a\b[^>]*\shref="([^"]*)"#i', $html, $m);
    return array_values(array_unique($m[1]));
}

/* ================================================================ checks */

/**
 * SEO checks for one page: [['level' => good|warn|bad|info, 'text' => …], …].
 * $all = all pages (for duplicates).
 */
function seo_checks(array $page, ?array $all = null): array
{
    $all = $all ?? pages_all();
    $site = site_load();
    $c = [];
    $add = function (string $level, string $text) use (&$c) {
        $c[] = ['level' => $level, 'text' => $text];
    };
    if (($page['status'] ?? '') !== 'published') {
        $add('info', 'This page is a draft: it is not on the website yet.');
    } elseif (!empty($page['seo']['noindex'])) {
        $add('warn', 'This page is hidden from Google (noindex).');
    }
    $others = array_filter($all, function ($p) use ($page) {
        return ($p['id'] ?? '') !== ($page['id'] ?? '') && ($p['status'] ?? '') === 'published';
    });
    $head = page_head($page, $site);
    $title = trim((string) ($page['seo']['title'] ?? ''));
    $desc = trim((string) ($page['seo']['description'] ?? ''));
    $len = mb_strlen($title);
    if ($title === '') {
        $add('bad', 'No title for Google yet. Google will show “' . $head['title'] . '”.');
    } elseif ($len > 60) {
        $add('warn', "The Google title is $len characters. Google shows about 60, so the end may be cut off.");
    } elseif ($len < 30) {
        $add('warn', "The Google title is short ($len characters). 30–60 works best.");
    } else {
        $add('good', 'Google title length is good.');
    }
    $len = mb_strlen($desc);
    if ($desc === '') {
        $add('bad', 'No description for Google yet. Google will pick some text from the page.');
    } elseif ($len > 160) {
        $add('warn', "The Google description is $len characters. Google shows about 160, so the end may be cut off.");
    } elseif ($len < 120) {
        $add('warn', "The Google description is short ($len characters). Aim for 120–160.");
    } else {
        $add('good', 'Google description length is good.');
    }
    foreach ($others as $o) {
        if ($title !== '' && strcasecmp($title, trim((string) ($o['seo']['title'] ?? ''))) === 0) {
            $add('bad', 'The page “' . ($o['title'] ?? '') . '” has the same Google title. Each page needs its own.');
        }
        if ($desc !== '' && strcasecmp($desc, trim((string) ($o['seo']['description'] ?? ''))) === 0) {
            $add('bad', 'The page “' . ($o['title'] ?? '') . '” has the same Google description.');
        }
    }

    $body = render_blocks($page, $site);
    $h1 = preg_match_all('/<h1[\s>]/i', $body);
    $h1Text = preg_match('#<h1[^>]*>(.*?)</h1>#is', $body, $hm) ? collapse_space(html_entity_decode(strip_tags($hm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    if ($h1 === 0) {
        $add('bad', 'The page has no main heading. Add a “Top banner” section.');
    } elseif ($h1 > 1) {
        $add('warn', "The page has $h1 main headings. Use only one.");
    } else {
        $add('good', 'One main heading.');
    }

    // AEO: a short direct answer right under the main heading.
    $intro = '';
    foreach ($page['blocks'] ?? [] as $b) {
        if (($b['type'] ?? '') === 'page-hero' && empty($b['hidden'])) {
            $intro = collapse_space(html_entity_decode(strip_tags((string) ($b['intro'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            break;
        }
    }
    $words = $intro === '' ? 0 : count(preg_split('/\s+/u', $intro));
    if (($page['type'] ?? '') !== 'home') {
        if ($words === 0) {
            $add('info', 'Tip: add an intro sentence under the main heading that answers what this page is about. AI answer tools quote it.');
        } elseif ($words > 80) {
            $add('warn', "The intro under the main heading is $words words. Keep it under 60 so it can be quoted as a direct answer.");
        }
    }

    $kw = mb_strtolower(trim((string) ($page['seo']['focusKeyword'] ?? '')));
    if ($kw === '') {
        $add('info', 'Tip: set the main search phrase (Google & sharing tab) to get advice about it.');
    } else {
        $first = mb_strtolower($intro . ' ' . collapse_space(html_entity_decode(strip_tags(preg_match('#<p[^>]*>(.*?)</p>#is', $body, $pm) ? $pm[1] : ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $add(mb_strpos(mb_strtolower($head['title']), $kw) !== false ? 'good' : 'warn', mb_strpos(mb_strtolower($head['title']), $kw) !== false ? 'The main search phrase is in the Google title.' : 'Put the main search phrase “' . $kw . '” in the Google title.');
        $add(mb_strpos(mb_strtolower($h1Text), $kw) !== false ? 'good' : 'warn', mb_strpos(mb_strtolower($h1Text), $kw) !== false ? 'The main search phrase is in the main heading.' : 'Put the main search phrase in the main heading.');
        if (mb_strpos($first, $kw) === false) {
            $add('warn', 'Use the main search phrase in the first sentences of the page.');
        }
        foreach ($others as $o) {
            if (mb_strtolower(trim((string) ($o['seo']['focusKeyword'] ?? ''))) === $kw) {
                $add('warn', 'The page “' . ($o['title'] ?? '') . '” targets the same search phrase. Two pages competing for one phrase both rank lower.');
            }
        }
    }

    preg_match_all('#<img\b[^>]*>#i', $body, $imgs);
    $noAlt = 0;
    foreach ($imgs[0] as $tag) {
        if (!preg_match('/\salt="[^"]+"/i', $tag)) {
            $noAlt++;
        }
    }
    if ($noAlt) {
        $add('warn', "$noAlt picture" . ($noAlt > 1 ? 's have' : ' has') . ' no description (alt text).');
    }

    $index = index_get()['paths'];
    foreach (seo_page_links($page) as $href) {
        $st = seo_link_status($href, $index);
        if ($st[0] === 'broken') {
            $add('bad', 'Broken link: ' . $href . ' (' . $st[1] . ').');
        } elseif ($st[0] === 'redirect') {
            $add('warn', 'The link ' . $href . ' is redirected. Link straight to ' . $st[1] . '.');
        }
    }
    if (!empty($page['_pathChanged'])) {
        $add('info', 'You changed the web address: the old one will redirect to the new one.');
    }
    return $c;
}

/** Problems across the whole site: [pageId => checks (warn/bad only)]. */
function seo_report(): array
{
    $all = pages_all();
    $out = [];
    foreach ($all as $id => $p) {
        $out[$id] = array_values(array_filter(seo_checks($p, $all), function ($c) {
            return in_array($c['level'], ['bad', 'warn'], true);
        }));
    }
    return $out;
}

/**
 * Bulk fixes from a JSON file: [{"page": "about", "field": "seo.title", "from": "old", "to": "new"}, …].
 * Dry run unless $apply. A fix is only applied when the field still holds "from",
 * and it is saved through the normal save (history kept). Returns report lines.
 */
function seo_apply_fixes(string $file, bool $apply): array
{
    $fixes = json_read($file, []);
    if (!is_array($fixes)) {
        throw new RuntimeException('The fixes file must be a JSON list.');
    }
    $allowed = ['seo.title', 'seo.description', 'seo.focusKeyword', 'seo.ogTitle', 'seo.ogDescription', 'title', 'card.summary', 'card.imageAlt'];
    $lines = [];
    $byPage = [];
    foreach ($fixes as $n => $f) {
        $id = (string) ($f['page'] ?? '');
        $field = (string) ($f['field'] ?? '');
        $page = $byPage[$id] ?? page_load($id);
        if (!$page) {
            $lines[] = "skip #$n: page “{$id}” not found";
            continue;
        }
        if (!in_array($field, $allowed, true)) {
            $lines[] = "skip #$n: field “{$field}” cannot be bulk-edited";
            continue;
        }
        $current = (string) array_get($page, $field, '');
        if ($current !== (string) ($f['from'] ?? '')) {
            $lines[] = "skip #$n: $id $field was changed since (now “{$current}”)";
            continue;
        }
        array_set($page, $field, clean_text($f['to'] ?? ''));
        $byPage[$id] = $page;
        $lines[] = ($apply ? 'fix' : 'would fix') . " #$n: $id $field → “" . clean_text($f['to'] ?? '') . '”';
    }
    if ($apply) {
        foreach ($byPage as $page) {
            page_save($page);
        }
    }
    return $lines;
}

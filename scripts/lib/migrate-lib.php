<?php
/**
 * Migration internals (used by scripts/migrate.php and tests).
 */

/* ============================================================ inventory */

/** Static pages: [['file' => 'about.html', 'path' => '/about.html', 'id' => 'about', 'type' => 'about'], …] */
function migrate_source_files(string $root): array
{
    $files = array_merge(glob($root . '/*.html') ?: [], glob($root . '/product/*.html') ?: []);
    $types = ['index.html' => 'home', 'products.html' => 'listing', 'about.html' => 'about', 'contact.html' => 'contact'];
    $out = [];
    foreach ($files as $abs) {
        $rel = ltrim(substr($abs, strlen($root)), '/');
        $base = basename($rel, '.html');
        $isProduct = strpos($rel, 'product/') === 0;
        $id = $rel === 'index.html' ? 'home' : $base;
        $out[] = [
            'file' => $rel,
            'path' => '/' . $rel,
            'id'   => $id,
            'type' => $isProduct ? 'product' : ($types[$rel] ?? 'page'),
        ];
    }
    usort($out, function ($a, $b) {
        return strcmp($a['path'], $b['path']);
    });
    $ids = array_column($out, 'id');
    if (count($ids) !== count(array_unique($ids))) {
        throw new RuntimeException('Two static pages would get the same id.');
    }
    return $out;
}

/** Split the <main> content into top-level chunks, keeping the original text. */
function migrate_split_main(string $html, string $file): array
{
    $lines = preg_split('/\r?\n/', $html);
    $start = array_search('<main>', array_map('trim', $lines), true);
    $end = array_search('</main>', array_map('trim', $lines), true);
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException("$file: no <main> … </main> found");
    }
    $chunks = [];
    $i = $start + 1;
    while ($i < $end) {
        $line = $lines[$i];
        if (trim($line) === '') {
            $i++;
            continue;
        }
        if (preg_match('/^  <!--/', $line)) {
            $buf = [$line];
            while (strpos($lines[$i], '-->') === false) {
                $i++;
                if ($i >= $end) {
                    throw new RuntimeException("$file: unclosed comment in <main>");
                }
                $buf[] = $lines[$i];
            }
            $i++;
            if (count($buf) > 1) {
                $chunks[] = ['kind' => 'comment', 'raw' => implode("\n", $buf)];
            } else {
                $chunks[] = ['kind' => 'label', 'raw' => $buf[0]];
            }
            continue;
        }
        if (!preg_match('/^  <([a-z]+)[\s>]/', $line, $m)) {
            throw new RuntimeException("$file line " . ($i + 1) . ': unexpected top-level content: ' . trim($line));
        }
        $tag = $m[1];
        $buf = [$line];
        while (!preg_match('#^  </' . $tag . '>\s*$#', $lines[$i])) {
            $i++;
            if ($i >= $end) {
                throw new RuntimeException("$file: <$tag> starting at a top-level line is never closed at the same indent");
            }
            $buf[] = $lines[$i];
        }
        $i++;
        $chunks[] = ['kind' => 'element', 'raw' => implode("\n", $buf)];
    }
    return $chunks;
}

/* ======================================================= site settings */

function migrate_site_settings(string $root): array
{
    $header = dom_root(dom_fragment((string) file_get_contents($root . '/includes/header.html')));
    $navbar = dom_root(dom_fragment((string) file_get_contents($root . '/includes/navbar.html')));
    $footer = dom_root(dom_fragment((string) file_get_contents($root . '/includes/footer.html')));
    $home = dom_document((string) file_get_contents($root . '/index.html'));
    $hx = new DOMXPath($header->ownerDocument);
    $nx = new DOMXPath($navbar->ownerDocument);
    $fx = new DOMXPath($footer->ownerDocument);
    $homeX = new DOMXPath($home);

    $first = function (DOMXPath $x, string $q, ?DOMNode $ctx = null) {
        $n = $x->query($q, $ctx);
        return $n && $n->length ? $n->item(0) : null;
    };

    $site = [];
    $org = [];
    foreach ($homeX->query('//script[@type="application/ld+json"]') as $s) {
        $d = json_decode($s->textContent, true);
        if (($d['@type'] ?? '') === 'Organization') {
            $org = $d;
        }
    }
    $site['name'] = $org['name'] ?? 'Jai Baba Nanak Cloth Trading Co.';
    $site['brand'] = $org['alternateName'] ?? 'Jaibaba Interlinings';
    $site['lang'] = $home->documentElement->getAttribute('lang') ?: 'en';

    // Phones: footer lists all of them; the top bar shows the first.
    $site['phones'] = [];
    foreach ($fx->query('//ul[contains(@class,"footer-contact")]//a[starts-with(@href,"tel:")]') as $a) {
        $site['phones'][] = ['display' => collapse_ws($a->textContent), 'tel' => substr($a->getAttribute('href'), 4)];
    }
    $mail = $first($hx, '//a[starts-with(@href,"mailto:")]');
    $site['email'] = $mail ? substr($mail->getAttribute('href'), 7) : '';
    $clock = $first($hx, '//i[contains(@class,"fa-clock")]/..');
    $site['hours'] = $clock ? collapse_ws($clock->textContent) : '';
    $loc = $first($hx, '//i[contains(@class,"fa-map-marker-alt")]/..');
    $site['topbarLocation'] = $loc ? collapse_ws($loc->textContent) : '';
    $site['mapUrl'] = $loc ? $loc->getAttribute('href') : '';

    $site['addresses'] = [];
    foreach ($fx->query('//ul[contains(@class,"footer-contact")]//i[contains(@class,"fa-map-marker-alt")]/following-sibling::span[1]/a') as $a) {
        $text = collapse_ws($a->textContent);
        $addr = strpos($text, ': ') !== false
            ? ['label' => substr($text, 0, strpos($text, ': ')), 'text' => substr($text, strpos($text, ': ') + 2)]
            : ['label' => '', 'text' => $text];
        if ($a->getAttribute('href') !== $site['mapUrl']) {
            $addr['mapUrl'] = $a->getAttribute('href');
        }
        $site['addresses'][] = $addr;
    }

    $wa = $first($fx, '//a[contains(@class,"whatsapp-float")]');
    $site['whatsapp'] = ['number' => '', 'message' => ''];
    if ($wa && preg_match('#wa\.me/(\d+)(?:\?text=(.*))?$#', $wa->getAttribute('href'), $m)) {
        $site['whatsapp'] = ['number' => $m[1], 'message' => rawurldecode($m[2] ?? '')];
    }

    $site['social'] = [];
    foreach ($hx->query('//div[contains(@class,"topbar-social")]/a') as $a) {
        $i = $first($hx, './i', $a);
        $site['social'][] = ['label' => $a->getAttribute('aria-label'), 'icon' => $i ? $i->getAttribute('class') : '', 'url' => $a->getAttribute('href')];
    }

    $logo = $first($nx, '//a[contains(@class,"navbar-brand")]/img');
    $site['logo'] = ['src' => $logo ? $logo->getAttribute('src') : '', 'alt' => $logo ? $logo->getAttribute('alt') : ''];
    $icon = $first($homeX, '//link[@rel="icon"]');
    $site['favicon'] = $icon ? $icon->getAttribute('href') : '';
    $site['defaultOgImage'] = migrate_most_common_og_image($root);

    // Menu
    $site['nav'] = [];
    foreach ($nx->query('//ul[contains(@class,"navbar-nav")]/li') as $li) {
        $a = $first($nx, './a', $li);
        if (!$a || has_class($a, 'btn')) {
            continue;
        }
        $item = ['label' => collapse_ws($a->textContent), 'href' => $a->getAttribute('href')];
        if ($a->getAttribute('data-page') !== '') {
            $item['page'] = $a->getAttribute('data-page');
        }
        $menu = $first($nx, './ul[contains(@class,"dropdown-menu")]', $li);
        if ($menu) {
            $item['dropdownId'] = $a->getAttribute('id');
            $item['children'] = [];
            foreach (el_children($menu) as $cli) {
                if ($first($nx, './hr', $cli)) {
                    $item['children'][] = ['divider' => true];
                    continue;
                }
                $ca = $first($nx, './a', $cli);
                $ci = $ca ? $first($nx, './i', $ca) : null;
                $child = ['label' => collapse_ws($ca ? $ca->textContent : ''), 'href' => $ca ? $ca->getAttribute('href') : ''];
                if ($ci) {
                    $child['icon'] = $ci->getAttribute('class');
                }
                $item['children'][] = $child;
            }
        }
        $site['nav'][] = $item;
    }
    $cta = $first($nx, '//ul[contains(@class,"navbar-nav")]/li/a[contains(@class,"btn")]');
    $site['navCta'] = $cta ? ['label' => collapse_ws($cta->textContent), 'href' => $cta->getAttribute('href'), 'class' => $cta->getAttribute('class')] : null;

    // Newsletter bar
    $nb = $first($fx, '//div[contains(@class,"newsletter-bar")]');
    $site['newsletter'] = ['enabled' => (bool) $nb];
    if ($nb) {
        $site['newsletter'] += [
            'heading'     => collapse_ws($first($fx, './/h3', $nb)->textContent ?? ''),
            'text'        => collapse_ws($first($fx, './/p', $nb)->textContent ?? ''),
            'placeholder' => $first($fx, './/input', $nb)->getAttribute('placeholder'),
            'button'      => collapse_ws($first($fx, './/button', $nb)->textContent ?? ''),
        ];
    }

    // Footer
    $f = [];
    $badge = $first($fx, '//span[contains(@class,"footer-logo-badge")]/img');
    if ($badge && $badge->getAttribute('src') !== $site['logo']['src']) {
        $f['logo'] = $badge->getAttribute('src');
    }
    $f['logoAlt'] = $badge ? $badge->getAttribute('alt') : '';
    $about = $first($fx, '//div[contains(@class,"footer-about")]/p');
    $f['about'] = $about ? collapse_ws($about->textContent) : '';
    $f['columns'] = [];
    $cols = $fx->query('//footer//div[contains(@class,"row")]/div');
    foreach ($cols as $col) {
        if ($first($fx, './/div[contains(@class,"footer-about")]', $col)) {
            continue;
        }
        $h5 = $first($fx, './h5', $col);
        if ($first($fx, './ul[contains(@class,"footer-contact")]', $col)) {
            $f['contactTitle'] = collapse_ws($h5 ? $h5->textContent : '');
            $person = $first($fx, './/i[contains(@class,"fa-user")]/following-sibling::span[1]', $col);
            $f['contactPerson'] = $person ? collapse_ws($person->textContent) : '';
            continue;
        }
        $links = [];
        foreach ($fx->query('./ul/li/a', $col) as $a) {
            $links[] = ['label' => collapse_ws($a->textContent), 'href' => $a->getAttribute('href')];
        }
        $f['columns'][] = ['title' => collapse_ws($h5 ? $h5->textContent : ''), 'class' => $col->getAttribute('class'), 'links' => $links];
    }
    $bottom = $fx->query('//div[contains(@class,"footer-bottom")]/p');
    if ($bottom->length >= 2) {
        $copy = collapse_ws($bottom->item(0)->textContent);
        $f['copyright'] = trim((string) preg_replace('/^©\s*\d{4}\s*/u', '', $copy));
        $f['tagline'] = collapse_ws($bottom->item(1)->textContent);
    }
    $site['footer'] = $f;
    $site['tracking'] = ['ga4' => '', 'gtm' => '', 'metaPixel' => ''];
    return $site;
}

function migrate_most_common_og_image(string $root): string
{
    $count = [];
    foreach (migrate_source_files($root) as $src) {
        if (preg_match('/<meta property="og:image" content="([^"]*)"/', (string) file_get_contents($root . '/' . $src['file']), $m)) {
            $count[$m[1]] = ($count[$m[1]] ?? 0) + 1;
        }
    }
    arsort($count);
    return (string) (array_key_first($count) ?? '');
}

/** Render header/menu/footer from settings and compare with the include files. */
function migrate_verify_partials(string $root, array $site): array
{
    $pairs = [
        'top bar (includes/header.html)' => ['partials/topbar', 'includes/header.html'],
        'menu (includes/navbar.html)'    => ['partials/navbar', 'includes/navbar.html'],
        'footer (includes/footer.html)'  => ['partials/footer', 'includes/footer.html'],
    ];
    $out = [];
    foreach ($pairs as $label => [$tpl, $file]) {
        $rendered = render_template($tpl, ['site' => $site, 'page' => []]);
        $out[$label] = token_diff(fragment_tokens((string) file_get_contents($root . '/' . $file)), fragment_tokens($rendered));
    }
    return $out;
}

/* ===================================================== product cards */

/** Parse one .product-card column. */
function migrate_parse_product_card(DOMElement $col): ?array
{
    $x = new DOMXPath($col->ownerDocument);
    $card = $x->query('./div[contains(@class,"product-card")]', $col)->item(0);
    if (!$card) {
        return null;
    }
    $img = $x->query('.//div[contains(@class,"product-img")]/img', $card)->item(0);
    $tag = $x->query('.//span[contains(@class,"product-tag")]', $card)->item(0);
    $h3 = $x->query('.//h3', $card)->item(0);
    $p = $x->query('.//div[contains(@class,"product-body")]/p', $card)->item(0);
    $a = $x->query('.//a[contains(@class,"product-link")]', $card)->item(0);
    if (!$img || !$h3 || !$a) {
        return null;
    }
    return [
        'href'     => $a->getAttribute('href'),
        'title'    => plain_text($h3),
        'tag'      => $tag ? plain_text($tag) : '',
        'summary'  => $p ? plain_text($p) : '',
        'image'    => $img->getAttribute('src'),
        'imageAlt' => $img->getAttribute('alt'),
    ];
}

/**
 * Card fields for each product: the most common value of each field across
 * every listing (Home, Products page, Related products). Listings that differ
 * keep their own override, so nothing changes on screen.
 */
function migrate_card_map(string $root, array $files): array
{
    $seen = [];
    foreach ($files as $src) {
        $doc = dom_document((string) file_get_contents($root . '/' . $src['file']));
        $x = new DOMXPath($doc);
        foreach ($x->query('//div[contains(@class,"product-card")]/..') as $col) {
            $c = migrate_parse_product_card($col);
            if ($c && $c['title'] !== null && $c['summary'] !== null && $c['tag'] !== null) {
                $seen[$c['href']][] = $c;
            }
        }
    }
    $map = [];
    foreach ($seen as $href => $list) {
        $card = [];
        foreach (['title', 'tag', 'summary', 'image', 'imageAlt'] as $field) {
            $count = [];
            foreach ($list as $c) {
                $count[$c[$field]] = ($count[$c[$field]] ?? 0) + 1;
            }
            arsort($count); // stable: ties keep first-seen order
            $card[$field] = (string) array_key_first($count);
        }
        $map[$href] = $card;
    }
    return $map;
}

/* ============================================================ helpers */

/** Parse an <a> used as a button. null if it holds anything but text and <i> icons. */
function migrate_parse_button(?DOMElement $a): ?array
{
    if (!$a || $a->nodeName !== 'a') {
        return null;
    }
    $before = '';
    $after = '';
    $label = '';
    $kids = [];
    foreach ($a->childNodes as $c) {
        if ($c instanceof DOMText && collapse_ws($c->nodeValue) === '') {
            continue;
        }
        $kids[] = $c;
    }
    foreach ($kids as $k => $c) {
        if ($c instanceof DOMElement) {
            if ($c->nodeName !== 'i' || el_children($c)) {
                return null;
            }
            if ($k === 0) {
                $before = $c->getAttribute('class');
            } elseif ($k === count($kids) - 1) {
                $after = $c->getAttribute('class');
            } else {
                return null;
            }
        } elseif ($c instanceof DOMText) {
            $label .= $c->nodeValue;
        }
    }
    $btn = [
        'label'      => collapse_ws($label),
        'href'       => $a->getAttribute('href'),
        'class'      => $a->getAttribute('class'),
        'iconBefore' => $before,
        'iconAfter'  => $after,
    ];
    if ($a->getAttribute('target') === '_blank') {
        $btn['newTab'] = true;
    }
    return $btn['label'] !== '' ? $btn : null;
}

/** Centred section heading: eyebrow, heading, subtitle. null if the markup differs. */
function migrate_parse_section_header(?DOMElement $el): ?array
{
    if (!$el || !has_class($el, 'section-header')) {
        return null;
    }
    $h = [];
    foreach (el_children($el) as $c) {
        if ($c->nodeName === 'span' && has_class($c, 'section-eyebrow')) {
            $h['eyebrow'] = plain_text($c);
        } elseif ($c->nodeName === 'h2') {
            $h['heading'] = plain_text($c);
            if (has_class($c, 'text-white')) {
                $h['dark'] = true;
            }
        } elseif ($c->nodeName === 'p') {
            $h['subtitle'] = inner_html($c);
        } else {
            return null;
        }
    }
    foreach ($h as $v) {
        if ($v === null) {
            return null;
        }
    }
    return $h;
}

/** The single element child of a section's container, or its children. */
function migrate_container(DOMElement $section): ?DOMElement
{
    $kids = el_children($section);
    if (count($kids) !== 1 || !has_class($kids[0], 'container-custom')) {
        return null;
    }
    return $kids[0];
}

/**
 * How a grid staggers its animation delays. Returns ['delayMode' => …] (plus 'delays'
 * for hand-set values) or [] for the default "row" pattern.
 */
function migrate_delay_fields(array $cols, string $colClass): array
{
    $mode = migrate_delay_mode($cols, $colClass);
    if ($mode === 'row') {
        return [];
    }
    if ($mode === 'linear') {
        return ['delayMode' => 'linear'];
    }
    return ['delayMode' => 'custom', 'delays' => array_map(function ($c) {
        return (int) $c->getAttribute('data-aos-delay');
    }, $cols)];
}

function migrate_delay_mode(array $cols, string $colClass): ?string
{
    $per = cards_per_row($colClass);
    foreach (['row', 'linear'] as $mode) {
        $ok = true;
        foreach ($cols as $i => $col) {
            $want = aos_delay($i, $per, $mode);
            $have = $col->getAttribute('data-aos-delay');
            if ($want !== ($have !== '' ? ' data-aos-delay="' . $have . '"' : '')) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return $mode;
        }
    }
    return null;
}

function migrate_bg(DOMElement $section): string
{
    return trim($section->getAttribute('class'));
}

/* ========================================================= converters */

function convert_page_hero(DOMElement $s, array &$page): ?array
{
    if ($s->nodeName !== 'section' || $s->getAttribute('class') !== 'page-hero' || !($c = migrate_container($s))) {
        return null;
    }
    $b = ['type' => 'page-hero', 'icon' => '', 'eyebrow' => '', 'heading' => '', 'intro' => '', 'image' => '', 'breadcrumb' => false];
    if (preg_match("/background-image:\s*url\('([^']*)'\)/", $s->getAttribute('style'), $m)) {
        $b['image'] = $m[1];
    }
    $crumbs = null;
    foreach (el_children($c) as $el) {
        if ($el->nodeName === 'span' && has_class($el, 'hero-eyebrow')) {
            $i = el_children($el);
            if (count($i) > 1 || ($i && $i[0]->nodeName !== 'i')) {
                return null;
            }
            $b['icon'] = $i ? $i[0]->getAttribute('class') : '';
            $b['eyebrow'] = collapse_ws($el->textContent);
        } elseif ($el->nodeName === 'h1') {
            $b['heading'] = plain_text($el);
        } elseif ($el->nodeName === 'p') {
            $b['intro'] = inner_html($el);
        } elseif (has_class($el, 'breadcrumb-bar')) {
            $crumbs = [];
            $active = null;
            foreach ($el->getElementsByTagName('li') as $li) {
                if (has_class($li, 'sep')) {
                    continue;
                }
                $a = $li->getElementsByTagName('a')->item(0);
                if ($a) {
                    $crumbs[] = ['label' => collapse_ws($a->textContent), 'href' => $a->getAttribute('href')];
                } else {
                    $active = collapse_ws($li->textContent);
                }
            }
            $b['breadcrumb'] = true;
            $page['breadcrumb'] = $crumbs;
            $page['title'] = (string) $active;
        } else {
            return null;
        }
    }
    if ($b['heading'] === null || $b['heading'] === '') {
        return null;
    }
    if ($crumbs === null) {
        unset($b['breadcrumb']);
        $b['breadcrumb'] = false;
    }
    return $b;
}

function convert_media_text(DOMElement $s): ?array
{
    if ($s->nodeName !== 'section' || !($c = migrate_container($s))) {
        return null;
    }
    $rows = el_children($c);
    if (count($rows) !== 1 || $rows[0]->getAttribute('class') !== 'row align-items-center g-5') {
        return null;
    }
    $cols = el_children($rows[0]);
    if (count($cols) !== 2) {
        return null;
    }
    $mediaIdx = null;
    foreach ($cols as $k => $col) {
        $kids = el_children($col);
        if (count($kids) === 1 && (has_class($kids[0], 'img-frame') || has_class($kids[0], 'product-hero-img'))) {
            $mediaIdx = $k;
        }
    }
    if ($mediaIdx === null) {
        return null;
    }
    $media = $cols[$mediaIdx];
    $text = $cols[1 - $mediaIdx];
    $b = [
        'type' => 'media-text', 'bg' => migrate_bg($s), 'textFirst' => $mediaIdx === 1,
        'mediaAos' => $media->getAttribute('data-aos'), 'textAos' => $text->getAttribute('data-aos'),
        'image' => '', 'imageAlt' => '', 'video' => '',
        'eyebrow' => '', 'heading' => '', 'html' => '', 'miniFeatures' => [], 'checklist' => [], 'afterHtml' => '', 'button' => null,
    ];
    $frame = el_children($media)[0];
    if (!in_array($frame->getAttribute('class'), ['img-frame', 'product-hero-img'], true)) {
        return null;
    }
    $defaultMedia = 'col-lg-6' . ($mediaIdx === 1 ? ' order-lg-1' : '');
    $defaultText = 'col-lg-6' . ($mediaIdx === 1 ? ' order-lg-2' : '');
    if ($media->getAttribute('class') !== $defaultMedia) {
        $b['mediaCol'] = $media->getAttribute('class');
    }
    if ($text->getAttribute('class') !== $defaultText) {
        $b['textCol'] = $text->getAttribute('class');
    }
    if ($media->getAttribute('data-aos-delay') !== '') {
        $b['mediaDelay'] = (int) $media->getAttribute('data-aos-delay');
    }
    if ($text->getAttribute('data-aos-delay') !== '') {
        $b['textDelay'] = (int) $text->getAttribute('data-aos-delay');
    }
    if ($frame->getAttribute('class') !== 'img-frame') {
        $b['frame'] = $frame->getAttribute('class');
    }
    $fk = el_children($frame);
    if (count($fk) !== 1) {
        return null;
    }
    if ($fk[0]->nodeName === 'img') {
        $b['image'] = $fk[0]->getAttribute('src');
        $b['imageAlt'] = $fk[0]->getAttribute('alt');
    } elseif ($fk[0]->nodeName === 'video') {
        $src = $fk[0]->getElementsByTagName('source')->item(0);
        $b['video'] = $src ? $src->getAttribute('src') : '';
    } else {
        return null;
    }

    $kids = el_children($text);
    if ($kids && $kids[0]->nodeName === 'span' && has_class($kids[0], 'section-eyebrow')) {
        $b['eyebrow'] = plain_text(array_shift($kids));
    }
    if ($kids && $kids[0]->nodeName === 'h2' && has_class($kids[0], 'section-title')) {
        $b['heading'] = plain_text(array_shift($kids));
    }
    if ($b['eyebrow'] === null || $b['heading'] === null) {
        return null;
    }
    $last = end($kids);
    if ($last && $last->nodeName === 'a' && has_class($last, 'btn')) {
        $b['button'] = migrate_parse_button($last);
        if (!$b['button']) {
            return null;
        }
        array_pop($kids);
    }
    $before = [];
    $after = [];
    $doneList = false;
    foreach ($kids as $el) {
        if ($el->nodeName === 'div' && $el->getElementsByTagName('div')->length && has_class($el, 'g-3')) {
            if ($doneList || $after) {
                return null;
            }
            foreach (el_children($el) as $fcol) {
                if ($fcol->getAttribute('class') !== 'col-sm-6') {
                    $b['featureCol'] = $fcol->getAttribute('class');
                }
                $fm = el_children($fcol)[0] ?? null;
                if (!$fm || !has_class($fm, 'feature-mini')) {
                    return null;
                }
                $parts = el_children($fm);
                $i = $parts[0]->getElementsByTagName('i')->item(0);
                $h4 = $parts[1]->getElementsByTagName('h4')->item(0);
                $p = $parts[1]->getElementsByTagName('p')->item(0);
                $b['miniFeatures'][] = ['icon' => $i ? $i->getAttribute('class') : '', 'title' => plain_text($h4), 'text' => plain_text($p)];
            }
            $doneList = true;
        } elseif ($el->nodeName === 'ul' && has_class($el, 'check-list')) {
            if ($b['checklist'] || $after) {
                return null;
            }
            foreach (el_children($el) as $li) {
                $b['checklist'][] = trim((string) preg_replace('#^<i class="fas fa-check-circle"></i>#', '', inner_html($li)));
            }
            $doneList = true;
        } elseif ($doneList) {
            $after[] = outer_html($el);
        } else {
            $before[] = outer_html($el);
        }
    }
    foreach ($b['miniFeatures'] as $f) {
        if ($f['title'] === null || $f['text'] === null) {
            return null;
        }
    }
    $b['html'] = implode("\n          ", $before);
    $b['afterHtml'] = implode("\n          ", $after);
    return $b;
}

function convert_card_grid(DOMElement $s): ?array
{
    if ($s->nodeName !== 'section' || !($c = migrate_container($s))) {
        return null;
    }
    $kids = el_children($c);
    $b = ['type' => 'card-grid', 'bg' => migrate_bg($s)];
    if ($kids && has_class($kids[0], 'section-header')) {
        $h = migrate_parse_section_header(array_shift($kids));
        if ($h === null) {
            return null;
        }
        $b += $h;
    }
    $row = array_shift($kids);
    if (!$row || !in_array($row->getAttribute('class'), ['row g-4', 'row g-4 justify-content-center'], true)) {
        return null;
    }
    if (has_class($row, 'justify-content-center')) {
        $b['center'] = true;
    }
    if ($kids) {
        $link = array_shift($kids);
        if ($kids || $link->getAttribute('class') !== 'text-center mt-5') {
            return null;
        }
        $b['link'] = migrate_parse_button(el_children($link)[0] ?? null);
        if (!$b['link']) {
            return null;
        }
    }
    $b['items'] = [];
    $kind = null;
    $b += migrate_delay_fields(el_children($row), el_children($row) ? el_children($row)[0]->getAttribute('class') : '');
    foreach (el_children($row) as $col) {
        $b['col'] = $b['col'] ?? $col->getAttribute('class');
        $card = el_children($col)[0] ?? null;
        if (!$card || count(el_children($col)) !== 1) {
            return null;
        }
        $isVariant = has_class($card, 'variant-card');
        if (!$isVariant && !has_class($card, 'value-card')) {
            return null;
        }
        $kind = $kind ?? ($isVariant ? 'variant' : 'value');
        if ($kind !== ($isVariant ? 'variant' : 'value')) {
            return null;
        }
        $item = [];
        foreach (el_children($card) as $el) {
            if ($el->nodeName === 'img') {
                $item['image'] = $el->getAttribute('src');
                $item['imageAlt'] = $el->getAttribute('alt');
            } elseif ($el->nodeName === 'i' && $isVariant) {
                $item['icon'] = $el->getAttribute('class');
            } elseif (has_class($el, 'value-icon')) {
                $i = $el->getElementsByTagName('i')->item(0);
                $item['icon'] = $i ? $i->getAttribute('class') : '';
            } elseif (in_array($el->nodeName, ['h3', 'h4'], true)) {
                $b['titleTag'] = $el->nodeName;
                $item['title'] = plain_text($el);
            } elseif ($el->nodeName === 'p') {
                $item['text'] = inner_html($el);
            } else {
                return null;
            }
        }
        if (($item['title'] ?? null) === null) {
            return null;
        }
        if (!$isVariant) {
            $b['centered'] = has_class($card, 'text-center');
        }
        $b['items'][] = $item;
    }
    $b['card'] = $kind ?? 'value';
    return $b['items'] ? $b : null;
}

function convert_spec_table(DOMElement $s): ?array
{
    if ($s->nodeName !== 'section' || !($c = migrate_container($s))) {
        return null;
    }
    $kids = el_children($c);
    $b = ['type' => 'spec-table', 'bg' => migrate_bg($s)];
    if ($kids && has_class($kids[0], 'section-header')) {
        $h = migrate_parse_section_header(array_shift($kids));
        if ($h === null) {
            return null;
        }
        $b += $h;
    }
    if (count($kids) === 2 && $kids[1]->nodeName === 'p') {
        $b['note'] = inner_html(array_pop($kids));
    }
    if (count($kids) !== 1 || !has_class($kids[0], 'table-responsive-custom')) {
        return null;
    }
    $table = $kids[0]->getElementsByTagName('table')->item(0);
    if (!$table || !in_array($table->getAttribute('class'), ['spec-table', 'compare-table'], true)) {
        return null;
    }
    if ($table->getAttribute('class') !== 'spec-table') {
        $b['style'] = $table->getAttribute('class');
    }
    $b['columns'] = [];
    foreach ($table->getElementsByTagName('th') as $th) {
        $b['columns'][] = plain_text($th);
    }
    $b['rows'] = [];
    $tbody = $table->getElementsByTagName('tbody')->item(0);
    foreach ($tbody ? el_children($tbody) : [] as $tr) {
        $row = [];
        foreach (el_children($tr) as $td) {
            $row[] = inner_html($td);
        }
        $b['rows'][] = $row;
    }
    return in_array(null, $b['columns'], true) ? null : $b;
}

function convert_faq(DOMElement $s): ?array
{
    if ($s->nodeName !== 'section' || !($c = migrate_container($s))) {
        return null;
    }
    $rows = el_children($c);
    if (count($rows) !== 1 || $rows[0]->getAttribute('class') !== 'row g-5') {
        return null;
    }
    $cols = el_children($rows[0]);
    if (count($cols) !== 2) {
        return null;
    }
    $acc = el_children($cols[1])[0] ?? null;
    if (!$acc || !has_class($acc, 'faq-accordion')) {
        return null;
    }
    $b = ['type' => 'faq', 'bg' => migrate_bg($s), 'eyebrow' => '', 'heading' => '', 'intro' => '', 'button' => null,
        'accordionId' => $acc->getAttribute('id'), 'itemPrefix' => '', 'items' => []];
    foreach (el_children($cols[0]) as $el) {
        if ($el->nodeName === 'span') {
            $b['eyebrow'] = plain_text($el);
        } elseif ($el->nodeName === 'h2') {
            $b['heading'] = plain_text($el);
        } elseif ($el->nodeName === 'p') {
            $b['intro'] = inner_html($el);
        } elseif ($el->nodeName === 'a') {
            $b['button'] = migrate_parse_button($el);
        } else {
            return null;
        }
    }
    foreach (el_children($acc) as $n => $item) {
        $btn = $item->getElementsByTagName('button')->item(0);
        $body = null;
        foreach ($item->getElementsByTagName('div') as $d) {
            if (has_class($d, 'accordion-body')) {
                $body = $d;
            } elseif (has_class($d, 'accordion-collapse')) {
                $cid = $d->getAttribute('id');
            }
        }
        if (!$btn || !$body || !isset($cid)) {
            return null;
        }
        if ($n === 0) {
            $b['itemPrefix'] = (string) preg_replace('/1$/', '', $cid);
        }
        if ($cid !== $b['itemPrefix'] . ($n + 1)) {
            return null;
        }
        $b['items'][] = ['q' => plain_text($btn), 'a' => inner_html($body)];
        unset($cid);
    }
    return $b['items'] ? $b : null;
}

function convert_product_cards(DOMElement $s, array $ctx): ?array
{
    if ($s->nodeName !== 'section' || !($c = migrate_container($s))) {
        return null;
    }
    $kids = el_children($c);
    $b = ['type' => 'product-cards', 'bg' => migrate_bg($s)];
    if ($kids && has_class($kids[0], 'section-header')) {
        $h = migrate_parse_section_header(array_shift($kids));
        if ($h === null) {
            return null;
        }
        $b += $h;
    }
    $row = array_shift($kids);
    if (!$row || !in_array($row->getAttribute('class'), ['row g-4', 'row g-4 justify-content-center'], true)) {
        return null;
    }
    $b['center'] = has_class($row, 'justify-content-center');
    if ($kids) {
        $link = array_shift($kids);
        if ($kids || $link->getAttribute('class') !== 'text-center mt-5') {
            return null;
        }
        $b['link'] = migrate_parse_button(el_children($link)[0] ?? null);
        if (!$b['link']) {
            return null;
        }
    }
    $b['items'] = [];
    $cols = el_children($row);
    $b += $cols ? migrate_delay_fields($cols, $cols[0]->getAttribute('class')) : [];
    foreach ($cols as $k => $col) {
        $card = migrate_parse_product_card($col);
        if (!$card) {
            // Optional dark "talk to us" tile, only as the last column.
            $tile = el_children($col)[0] ?? null;
            if ($k !== count($cols) - 1 || !$tile || !has_class($tile, 'card-custom')) {
                return null;
            }
            $parts = el_children($tile);
            if (count($parts) !== 4) {
                return null;
            }
            $b['promo'] = [
                'icon'   => trim(str_replace(['fa-2x', 'text-gold', 'mb-3'], '', $parts[0]->getAttribute('class'))),
                'title'  => plain_text($parts[1]),
                'text'   => plain_text($parts[2]),
                'button' => migrate_parse_button($parts[3]),
            ];
            continue;
        }
        $b['col'] = $b['col'] ?? $col->getAttribute('class');
        $id = $ctx['pathToId'][$card['href']] ?? null;
        if (!$id || in_array(null, $card, true)) {
            return null;
        }
        $base = $ctx['cards'][$card['href']] ?? [];
        $item = ['page' => $id];
        foreach (['title', 'tag', 'summary', 'image', 'imageAlt'] as $f) {
            if (($base[$f] ?? null) !== $card[$f]) {
                $item[$f] = $card[$f];
            }
        }
        $b['items'][] = $item;
    }
    return $b['items'] ? $b : null;
}

function convert_cta(DOMElement $s): ?array
{
    if ($s->nodeName !== 'section' || $s->getAttribute('class') !== 'section-tight' || !($c = migrate_container($s))) {
        return null;
    }
    $sec = el_children($c)[0] ?? null;
    $wrap = $sec ? (el_children($sec)[0] ?? null) : null;
    if (!$sec || !has_class($sec, 'cta-section') || !$wrap || !has_class($wrap, 'cta-wrap')) {
        return null;
    }
    $b = ['type' => 'cta', 'heading' => '', 'text' => '', 'buttons' => []];
    foreach (el_children($wrap) as $el) {
        if ($el->nodeName === 'h2') {
            $b['heading'] = plain_text($el);
        } elseif ($el->nodeName === 'p') {
            $b['text'] = inner_html($el);
        } elseif ($el->nodeName === 'div') {
            foreach (el_children($el) as $a) {
                $btn = migrate_parse_button($a);
                if (!$btn) {
                    return null;
                }
                $b['buttons'][] = $btn;
            }
        } else {
            return null;
        }
    }
    return $b['heading'] ? $b : null;
}

/* ============================================================ pages */

/** Tokens of the <main> element of a full HTML document. */
function main_tokens(string $html): array
{
    $doc = dom_document($html);
    $main = $doc->getElementsByTagName('main')->item(0);
    return $main ? html_tokens($main) : [];
}

function migrate_head(DOMDocument $doc, array $site): array
{
    $x = new DOMXPath($doc);
    $meta = function (string $q) use ($x) {
        $n = $x->query($q)->item(0);
        return $n ? collapse_ws($n->nodeValue) : '';
    };
    $seo = [
        'title'         => $meta('//title'),
        'description'   => $meta('//meta[@name="description"]/@content'),
        'canonical'     => $meta('//link[@rel="canonical"]/@href'),
        'ogTitle'       => $meta('//meta[@property="og:title"]/@content'),
        'ogDescription' => $meta('//meta[@property="og:description"]/@content'),
        'ogType'        => $meta('//meta[@property="og:type"]/@content'),
        'ogImage'       => $meta('//meta[@property="og:image"]/@content'),
        'noindex'       => stripos($meta('//meta[@name="robots"]/@content'), 'noindex') !== false,
    ];
    $jsonld = [];
    foreach ($x->query('//script[@type="application/ld+json"]') as $s) {
        $d = json_decode($s->textContent, true);
        if (!is_array($d)) {
            throw new RuntimeException('Broken JSON-LD in a page head');
        }
        $jsonld[] = $d;
    }
    $body = $x->query('//body')->item(0);
    return ['seo' => $seo, 'jsonld' => $jsonld, 'bodyClass' => $body ? $body->getAttribute('class') : ''];
}

/** Verify that a converted block renders exactly like the original section. */
function migrate_verify_block(array $block, string $raw, array $page, array $site): ?string
{
    $rendered = render_block($block, ['page' => $page, 'site' => $site, 'index' => 0]);
    return token_diff(fragment_tokens($raw), fragment_tokens($rendered));
}

/**
 * Run the whole migration into $work (a content folder). Returns
 * ['text' => report, 'pages' => [...], 'problems' => int].
 */
function migrate_site(string $root, string $work, bool $verbose = false): array
{
    $log = [];
    $problems = 0;
    $files = migrate_source_files($root);
    $pathToId = [];
    foreach ($files as $f) {
        $pathToId[$f['path']] = $f['id'];
    }

    // 1. Site settings (header, menu, footer) – verified against the include files.
    $site = migrate_site_settings($root);
    json_write($work . '/site.json', $site);
    $log[] = 'Site settings (site.json)';
    foreach (migrate_verify_partials($root, $site) as $label => $diff) {
        $log[] = sprintf('  %s %s', $diff === null ? 'same ' : 'DIFF ', $label) . ($diff ? "\n      $diff" : '');
        $problems += $diff === null ? 0 : 1;
    }

    // 2. Product cards, then stub product pages so card lookups work while verifying.
    $cards = migrate_card_map($root, $files);
    foreach ($files as $f) {
        if ($f['type'] === 'product') {
            json_write($work . '/pages/' . $f['id'] . '.json', [
                'id' => $f['id'], 'type' => 'product', 'status' => 'published', 'path' => $f['path'],
                'title' => $cards[$f['path']]['title'] ?? $f['id'], 'card' => $cards[$f['path']] ?? [],
            ]);
        }
    }
    index_rebuild();
    $ctx = ['pathToId' => $pathToId, 'cards' => $cards];
    $converters = ['convert_media_text', 'convert_card_grid', 'convert_spec_table', 'convert_faq', 'convert_cta'];

    // 3. Pages
    $log[] = '';
    $log[] = 'Pages (structured sections / kept as HTML)';
    $pages = [];
    $now = now_iso();
    foreach ($files as $f) {
        $raw = (string) file_get_contents($root . '/' . $f['file']);
        $doc = dom_document($raw);
        $head = migrate_head($doc, $site);
        $page = [
            'id' => $f['id'], 'type' => $f['type'], 'status' => 'published', 'path' => $f['path'],
            'title' => '', 'source' => $f['file'], 'breadcrumb' => [], 'seo' => [], 'jsonld' => $head['jsonld'], 'blocks' => [],
        ];
        $notes = [];
        $structured = 0;
        $html = 0;
        foreach (migrate_split_main($raw, $f['file']) as $chunk) {
            if ($chunk['kind'] === 'label') {
                continue; // "<!-- HERO -->" style labels
            }
            if ($chunk['kind'] === 'comment') {
                $page['blocks'][] = ['type' => 'html', 'html' => $chunk['raw'], 'note' => 'Hidden (commented-out) section kept from the old site.'];
                $notes[] = 'kept a hidden (commented-out) section';
                continue;
            }
            $el = el_children(dom_root(dom_fragment($chunk['raw'])))[0] ?? null;
            $block = null;
            $why = [];
            if ($el && strpos($chunk['raw'], '<!--') === false) {
                $candidates = [];
                if (($b = convert_page_hero($el, $page)) !== null) {
                    $candidates[] = $b;
                }
                if (($b = convert_product_cards($el, $ctx)) !== null) {
                    $candidates[] = $b;
                }
                foreach ($converters as $fn) {
                    if (($b = $fn($el)) !== null) {
                        $candidates[] = $b;
                    }
                }
                foreach ($candidates as $cand) {
                    $diff = migrate_verify_block($cand, $chunk['raw'], $page, $site);
                    if ($diff === null) {
                        $block = $cand;
                        break;
                    }
                    $why[] = $cand['type'] . ' ' . $diff;
                }
            } elseif ($el) {
                $why[] = 'contains a hidden comment, kept as HTML so it is not lost';
            }
            if ($block) {
                $page['blocks'][] = $block;
                $structured++;
            } else {
                $label = $el ? ($el->nodeName . ($el->getAttribute('class') ? '.' . str_replace(' ', '.', $el->getAttribute('class')) : '')) : '?';
                $h2 = $el ? $el->getElementsByTagName('h2')->item(0) : null;
                $title = $h2 ? collapse_ws($h2->textContent) : ($el && $el->getElementsByTagName('h1')->item(0) ? collapse_ws($el->getElementsByTagName('h1')->item(0)->textContent) : '');
                $page['blocks'][] = ['type' => 'html', 'html' => $chunk['raw']] + ($title !== '' ? ['label' => $title] : []);
                $html++;
                if ($verbose) {
                    $notes[] = "HTML: $label \"$title\"" . ($why ? "\n      " . implode("\n      ", $why) : ' (no matching section type)');
                }
            }
        }

        if ($page['title'] === '') {
            $h1 = $doc->getElementsByTagName('h1')->item(0);
            $page['title'] = $f['type'] === 'home' ? 'Home' : ($h1 ? collapse_ws($h1->textContent) : $f['id']);
        }
        if (!$page['breadcrumb']) {
            unset($page['breadcrumb']);
        }
        if ($head['bodyClass'] !== 'header-fixed-offset') {
            $page['bodyClass'] = $head['bodyClass'];
        }

        // SEO: store only what differs from what the templates would generate anyway.
        $seo = $head['seo'];
        $auto = page_head(['title' => $page['title'], 'path' => $page['path'], 'status' => 'published', 'seo' => ['title' => $seo['title'], 'description' => $seo['description']]], $site);
        $page['seo'] = ['title' => $seo['title'], 'description' => $seo['description']];
        if ($seo['canonical'] !== $auto['canonical']) {
            $page['seo']['canonical'] = $seo['canonical'];
        }
        if ($seo['ogTitle'] !== $seo['title']) {
            $page['seo']['ogTitle'] = $seo['ogTitle'];
        }
        if ($seo['ogDescription'] !== $seo['description']) {
            $page['seo']['ogDescription'] = $seo['ogDescription'];
        }
        if ($seo['ogType'] !== '' && $seo['ogType'] !== 'website') {
            $page['seo']['ogType'] = $seo['ogType'];
        }
        if ($seo['ogImage'] !== ($site['defaultOgImage'] ?? '')) {
            $page['seo']['ogImage'] = $seo['ogImage'];
        }
        if ($seo['noindex']) {
            $page['seo']['noindex'] = true;
        }
        if ($f['type'] === 'product') {
            $page['card'] = $cards[$f['path']] ?? [];
            if (!$page['card']) {
                $notes[] = 'no product card found on any listing';
            }
        }
        $page['created'] = $now;
        $page['updated'] = $now;

        $errors = page_validate($page, []);
        if ($errors) {
            $problems++;
            $notes[] = 'INVALID: ' . implode(' ', $errors);
        }
        json_write($work . '/pages/' . $f['id'] . '.json', $page);
        $pages[$f['id']] = $page;

        // Whole-page check: the <main> content rendered by PHP must equal the original.
        $mainDiff = token_diff(main_tokens($raw), main_tokens(render_page($page)));
        if ($mainDiff !== null) {
            $problems++;
            $notes[] = "MAIN CONTENT DIFFERS $mainDiff";
        }
        $log[] = sprintf('  %-40s %2d / %d', $f['path'], $structured, $html) . ($notes ? "\n    - " . implode("\n    - ", $notes) : '');
    }
    index_rebuild();

    $totalS = 0;
    $totalH = 0;
    foreach ($pages as $p) {
        foreach ($p['blocks'] as $b) {
            $b['type'] === 'html' ? $totalH++ : $totalS++;
        }
    }
    $log[] = '';
    $log[] = sprintf('%d pages: %d structured sections, %d kept as HTML. %s', count($pages), $totalS, $totalH, $problems ? "$problems PROBLEM(S)" : 'No problems.');
    return ['text' => implode("\n", $log) . "\n", 'pages' => $pages, 'problems' => $problems];
}

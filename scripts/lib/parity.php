<?php
/**
 * Parity: compare each static .html file with the same address rendered by the PHP site.
 *
 * Baseline = the static page as a browser shows it: the <div data-include="…">
 * placeholders replaced by the include files (what assets/js/main.js does).
 *
 * Checks per page:
 *   title, meta description, canonical, robots, Open Graph / Twitter tags,
 *   every other <head> tag, JSON-LD (decoded and compared as data), H1 list,
 *   link targets, image src/alt, visible text, and the full <body> markup.
 *
 * Accepted differences are listed in parity_accepted() with the reason, so a
 * deliberate improvement never hides an accidental change.
 */

require_once __DIR__ . '/html-compare.php';

/** Static HTML with client-side includes inlined, as the browser ends up showing it. */
function parity_static_baseline(string $root, string $html): string
{
    return (string) preg_replace_callback('#<div data-include="([^"]+)"></div>#', function ($m) use ($root) {
        $file = $root . '/' . ltrim($m[1], '/');
        return is_file($file) ? (string) file_get_contents($file) : $m[0];
    }, $html);
}

/**
 * Differences that are intentional. Each entry: check name => [reason, callable($static, $php) => bool]
 * returning true when the difference is exactly the accepted one.
 */
function parity_accepted(): array
{
    return [];
}

/**
 * The footer year is typed as "2026" in the static footer, while PHP prints the
 * current year (main.js overwrites it in the browser either way). Not a difference.
 */
function parity_normalize(string $html): string
{
    return (string) preg_replace('#(<span data-current-year>)\d{4}(</span>)#', '$1YEAR$2', $html);
}

/** Everything we compare, extracted from one full HTML document. */
function parity_extract(string $html): array
{
    $doc = dom_document($html);
    $x = new DOMXPath($doc);
    $one = function (string $q) use ($x) {
        $n = $x->query($q)->item(0);
        return $n ? collapse_ws($n->nodeValue) : null;
    };
    $metaProps = [];
    foreach ($x->query('//head/meta[@property or @name]') as $m) {
        $key = $m->getAttribute('property') ?: $m->getAttribute('name');
        $metaProps[$key] = collapse_ws($m->getAttribute('content'));
    }
    ksort($metaProps);
    $headTags = [];
    foreach ($x->query('//head/*') as $el) {
        if ($el->nodeName === 'script' && $el->getAttribute('type') === 'application/ld+json') {
            continue; // compared as data below
        }
        $tok = html_tokens(dom_root(dom_fragment(outer_html($el))));
        $headTags[] = implode(' ', $tok);
    }
    $jsonld = [];
    foreach ($x->query('//script[@type="application/ld+json"]') as $s) {
        $jsonld[] = json_decode($s->textContent, true);
    }
    $h1 = [];
    foreach ($x->query('//h1') as $h) {
        $h1[] = collapse_ws($h->textContent);
    }
    $links = [];
    foreach ($x->query('//body//a[@href]') as $a) {
        $links[] = $a->getAttribute('href');
    }
    $images = [];
    foreach ($x->query('//body//img') as $img) {
        $images[] = $img->getAttribute('src') . ' | alt=' . $img->getAttribute('alt');
    }
    $body = $x->query('//body')->item(0);
    $text = '';
    if ($body) {
        $clone = $body->cloneNode(true);
        foreach (iterator_to_array($clone->getElementsByTagName('script')) as $s) {
            $s->parentNode->removeChild($s);
        }
        $text = collapse_ws($clone->textContent);
    }
    return [
        'lang'        => $doc->documentElement ? $doc->documentElement->getAttribute('lang') : '',
        'title'       => $one('//head/title'),
        'description' => $metaProps['description'] ?? null,
        'canonical'   => $one('//head/link[@rel="canonical"]/@href'),
        'robots'      => $metaProps['robots'] ?? null,
        'meta'        => $metaProps,
        'head'        => $headTags,
        'jsonld'      => $jsonld,
        'h1'          => $h1,
        'links'       => $links,
        'images'      => $images,
        'text'        => $text,
        'body'        => $body ? html_tokens($body) : [],
        'bodyClass'   => $body ? $body->getAttribute('class') : '',
    ];
}

/** Compare two extracts. Returns [check => description] for every difference. */
function parity_compare(array $a, array $b): array
{
    $diffs = [];
    foreach (['lang', 'title', 'description', 'canonical', 'robots', 'bodyClass'] as $k) {
        if ($a[$k] !== $b[$k]) {
            $diffs[$k] = 'static: ' . var_export($a[$k], true) . "\n        php:    " . var_export($b[$k], true);
        }
    }
    foreach (['meta' => 'meta tags', 'head' => 'other head tags', 'h1' => 'H1', 'links' => 'links', 'images' => 'images'] as $k => $label) {
        if ($a[$k] !== $b[$k]) {
            $diffs[$label] = parity_list_diff($a[$k], $b[$k]);
        }
    }
    if ($a['jsonld'] !== $b['jsonld']) {
        $diffs['JSON-LD'] = 'static: ' . json_encode($a['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\n        php:    " . json_encode($b['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    if ($a['text'] !== $b['text']) {
        $diffs['visible text'] = parity_text_diff($a['text'], $b['text']);
    }
    $d = token_diff($a['body'], $b['body']);
    if ($d !== null) {
        $diffs['body markup'] = $d;
    }
    return $diffs;
}

function parity_list_diff(array $a, array $b): string
{
    $out = [];
    if (array_values($a) === array_values($b)) {
        return 'same items, different keys';
    }
    $missing = array_diff_assoc_multiset($a, $b);
    $extra = array_diff_assoc_multiset($b, $a);
    foreach ($missing as $m) {
        $out[] = '- only in static: ' . (is_string($m) ? $m : json_encode($m));
    }
    foreach ($extra as $m) {
        $out[] = '+ only in PHP:    ' . (is_string($m) ? $m : json_encode($m));
    }
    if (!$out) {
        $out[] = 'same items in a different order';
    }
    return implode("\n        ", array_slice($out, 0, 12)) . (count($out) > 12 ? "\n        … " . (count($out) - 12) . ' more' : '');
}

/** Items of $a not matched one-for-one in $b. */
function array_diff_assoc_multiset(array $a, array $b): array
{
    $counts = [];
    foreach ($b as $v) {
        $k = is_string($v) ? $v : json_encode($v);
        $counts[$k] = ($counts[$k] ?? 0) + 1;
    }
    $out = [];
    foreach ($a as $v) {
        $k = is_string($v) ? $v : json_encode($v);
        if (($counts[$k] ?? 0) > 0) {
            $counts[$k]--;
        } else {
            $out[] = $v;
        }
    }
    return $out;
}

function parity_text_diff(string $a, string $b): string
{
    $n = min(strlen($a), strlen($b));
    $i = 0;
    while ($i < $n && $a[$i] === $b[$i]) {
        $i++;
    }
    $from = max(0, $i - 60);
    return 'first difference at character ' . $i
        . "\n        static: …" . mb_strcut($a, $from, 160)
        . "\n        php:    …" . mb_strcut($b, $from, 160);
}

/**
 * Run the comparison for every static file under $root against the content folder
 * currently configured. Returns ['pages' => [path => diffs], 'identical' => n, 'total' => n].
 */
function parity_run(string $root): array
{
    require_once __DIR__ . '/migrate-lib.php';
    $results = [];
    $identical = 0;
    $accepted = parity_accepted();
    foreach (migrate_source_files($root) as $src) {
        $static = parity_extract(parity_normalize(parity_static_baseline($root, (string) file_get_contents($root . '/' . $src['file']))));
        $resp = front_dispatch('GET', $src['path']);
        if ($resp['status'] !== 200) {
            $results[$src['path']] = ['status' => "PHP site answered {$resp['status']}"];
            continue;
        }
        $php = parity_extract(parity_normalize($resp['body']));
        $diffs = parity_compare($static, $php);
        $notes = [];
        foreach ($diffs as $check => $desc) {
            if (isset($accepted[$check]) && ($accepted[$check][1])($static, $php)) {
                $notes[$check] = 'accepted: ' . $accepted[$check][0];
                unset($diffs[$check]);
            }
        }
        $results[$src['path']] = $diffs + ['_accepted' => $notes];
        if (!$diffs) {
            $identical++;
        }
    }
    return ['pages' => $results, 'identical' => $identical, 'total' => count($results)];
}

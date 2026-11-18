<?php
/**
 * Allow-list HTML cleaner for everything typed into the dashboard.
 *
 * Keeps the markup this site actually uses (layout, Bootstrap classes, data-aos /
 * data-bs-* attributes, the enquiry form, the Google map, videos) and removes
 * anything that can run code: <script>, on* attributes, javascript: links,
 * unknown iframes, CSS expressions.
 */

const SANITIZE_DROP = ['script', 'style', 'object', 'embed', 'applet', 'base', 'link', 'meta', 'frame', 'frameset', 'svg', 'math', 'template', 'noscript'];

const SANITIZE_TAGS = [
    'a', 'abbr', 'address', 'article', 'aside', 'b', 'blockquote', 'br', 'button', 'caption', 'cite', 'code',
    'col', 'colgroup', 'dd', 'del', 'details', 'div', 'dl', 'dt', 'em', 'figcaption', 'figure', 'footer',
    'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'i', 'iframe', 'img', 'input', 'ins', 'kbd',
    'label', 'li', 'main', 'mark', 'nav', 'ol', 'optgroup', 'option', 'p', 'picture', 'pre', 'q', 's', 'section',
    'select', 'small', 'source', 'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'textarea',
    'tfoot', 'th', 'thead', 'time', 'tr', 'u', 'ul', 'video',
];

const SANITIZE_ATTRS = [
    'class', 'id', 'title', 'role', 'style', 'href', 'src', 'srcset', 'sizes', 'alt', 'rel', 'target', 'loading',
    'decoding', 'fetchpriority', 'width', 'height', 'type', 'name', 'value', 'for', 'placeholder', 'rows', 'cols',
    'preload', 'pattern', 'minlength', 'maxlength', 'min', 'max', 'step', 'inputmode', 'autocomplete',
    'allowfullscreen', 'referrerpolicy', 'required', 'controls', 'playsinline', 'muted', 'loop', 'autoplay',
    'poster', 'novalidate', 'selected', 'disabled', 'checked', 'readonly', 'multiple', 'colspan', 'rowspan',
    'scope', 'datetime', 'cite', 'start', 'reversed', 'open', 'tabindex', 'media', 'frameborder', 'allow',
];

/** Hosts whose pages may be embedded with <iframe>. */
const SANITIZE_IFRAME_HOSTS = ['www.google.com', 'maps.google.com', 'www.youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com'];

function sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"?><div id="__sanitize_root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('__sanitize_root');
    if (!$root) {
        return '';
    }
    sanitize_node($root);
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

function sanitize_node(DOMNode $node): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMComment) {
            // Comments are kept (hidden sections of the old site), unless they try something clever.
            if (preg_match('/<\s*script|\[if|<!\[/i', $child->nodeValue)) {
                $node->removeChild($child);
            }
            continue;
        }
        if (!($child instanceof DOMElement)) {
            if (!($child instanceof DOMText)) {
                $node->removeChild($child);
            }
            continue;
        }
        $tag = strtolower($child->nodeName);
        if (in_array($tag, SANITIZE_DROP, true)) {
            $node->removeChild($child);
            continue;
        }
        if (!in_array($tag, SANITIZE_TAGS, true)) {
            // Unknown tag: keep its content, drop the tag itself.
            sanitize_node($child);
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }
        foreach (iterator_to_array($child->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = $attr->value;
            $keep = in_array($name, SANITIZE_ATTRS, true)
                || preg_match('/^(data|aria)-[a-z0-9_.:-]+$/', $name);
            if ($keep && in_array($name, ['href', 'src', 'poster', 'cite'], true)) {
                $keep = sanitize_url_ok($value);
            } elseif ($keep && $name === 'srcset') {
                foreach (explode(',', $value) as $part) {
                    $keep = $keep && sanitize_url_ok(trim(explode(' ', trim($part))[0]));
                }
            } elseif ($keep && $name === 'style') {
                $keep = sanitize_style_ok($value);
            }
            if (!$keep) {
                $child->removeAttribute($attr->name);
            }
        }
        if ($tag === 'iframe' && !sanitize_iframe_ok($child->getAttribute('src'))) {
            $node->removeChild($child);
            continue;
        }
        if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
            $rel = preg_split('/\s+/', strtolower($child->getAttribute('rel'))) ?: [];
            if (!in_array('noopener', $rel, true) && !in_array('noreferrer', $rel, true)) {
                $child->setAttribute('rel', trim(implode(' ', array_filter($rel)) . ' noopener'));
            }
        }
        if ($tag === 'form') {
            // Forms may only post to this site.
            $child->removeAttribute('action');
            if (!in_array(strtolower($child->getAttribute('method')), ['', 'get', 'post'], true)) {
                $child->removeAttribute('method');
            }
        }
        sanitize_node($child);
    }
}

/** http(s), mailto, tel, site-relative and in-page links only. */
function sanitize_url_ok(string $url): bool
{
    $u = trim(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($u === '') {
        return true;
    }
    if (preg_match('#^(https?:|mailto:|tel:)#i', $u)) {
        return true;
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $u)) {
        return false; // javascript:, data:, vbscript:, file: …
    }
    return true; // /path, ./path, #anchor, ?query, relative
}

function sanitize_style_ok(string $style): bool
{
    $s = strtolower(preg_replace('/\s+|\/\*.*?\*\//s', '', html_entity_decode($style, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (preg_match('/expression\(|javascript:|vbscript:|behavior:|-moz-binding|@import|\\\\/', $s)) {
        return false;
    }
    if (preg_match_all('/url\(([^)]*)\)/', $s, $m)) {
        foreach ($m[1] as $u) {
            if (!sanitize_url_ok(trim($u, "'\""))) {
                return false;
            }
        }
    }
    return true;
}

function sanitize_iframe_ok(string $src): bool
{
    $host = strtolower((string) parse_url($src, PHP_URL_HOST));
    return preg_match('#^https://#i', $src) === 1 && in_array($host, SANITIZE_IFRAME_HOSTS, true);
}

/** Plain text field: single line, trimmed, no control characters (it is escaped on output). */
function clean_text($value): string
{
    $v = is_scalar($value) ? (string) $value : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return trim((string) preg_replace('/\s+/u', ' ', (string) $v));
}

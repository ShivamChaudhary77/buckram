<?php
/**
 * HTML comparison helpers shared by the migration (scripts/migrate.php) and the
 * parity test (tests/compare-site.php).
 *
 * Two fragments are "the same" when they produce the same token stream:
 * elements with their attributes (class words and inline styles normalised),
 * and text with whitespace collapsed. Comments and indentation are ignored.
 */

/** Parse an HTML fragment into a DOMDocument whose root is <div id="__root">. */
function dom_fragment(string $html): DOMDocument
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return $doc;
}

/** Parse a whole HTML document. */
function dom_document(string $html): DOMDocument
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"?>' . $html, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return $doc;
}

function dom_root(DOMDocument $doc): DOMElement
{
    $root = $doc->getElementById('__root');
    if (!$root) {
        foreach ($doc->childNodes as $n) {
            if ($n instanceof DOMElement) {
                return $n;
            }
        }
        throw new RuntimeException('Empty fragment');
    }
    return $root;
}

function collapse_ws(string $s): string
{
    return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $s)));
}

/** Token list for a node's content. */
function html_tokens(DOMNode $node): array
{
    $out = [];
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMText) {
            $t = collapse_ws($child->nodeValue ?? '');
            if ($t !== '') {
                $out[] = 'T:' . $t;
            }
        } elseif ($child instanceof DOMElement) {
            $attrs = [];
            foreach ($child->attributes as $a) {
                $v = $a->value;
                if ($a->name === 'class') {
                    $words = preg_split('/\s+/', trim($v)) ?: [];
                    sort($words);
                    $v = implode(' ', array_filter($words, 'strlen'));
                } elseif ($a->name === 'style') {
                    $v = rtrim((string) preg_replace('/\s+/', '', $v), ';');
                } else {
                    $v = collapse_ws($v);
                }
                $attrs[$a->name] = $v;
            }
            ksort($attrs);
            $s = '<' . strtolower($child->nodeName);
            foreach ($attrs as $k => $v) {
                $s .= ' ' . $k . '="' . $v . '"';
            }
            $out[] = $s . '>';
            foreach (html_tokens($child) as $t) {
                $out[] = $t;
            }
            $out[] = '</' . strtolower($child->nodeName) . '>';
        }
    }
    return $out;
}

function fragment_tokens(string $html): array
{
    return html_tokens(dom_root(dom_fragment($html)));
}

/** null when equal, otherwise a short description of the first difference. */
function token_diff(array $a, array $b): ?string
{
    $n = max(count($a), count($b));
    for ($i = 0; $i < $n; $i++) {
        $x = $a[$i] ?? '(end)';
        $y = $b[$i] ?? '(end)';
        if ($x !== $y) {
            $ctx = implode(' ', array_slice($a, max(0, $i - 3), 3));
            return "at token $i after [$ctx]\n      original: " . mb_strimwidth($x, 0, 220, '…') . "\n      rendered: " . mb_strimwidth($y, 0, 220, '…');
        }
    }
    return null;
}

function inner_html(DOMNode $node): string
{
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    return trim($html);
}

function outer_html(DOMNode $node): string
{
    return (string) $node->ownerDocument->saveHTML($node);
}

/** Element children only. @return DOMElement[] */
function el_children(DOMNode $node): array
{
    $out = [];
    foreach ($node->childNodes as $c) {
        if ($c instanceof DOMElement) {
            $out[] = $c;
        }
    }
    return $out;
}

function has_class(DOMElement $el, string $class): bool
{
    return in_array($class, preg_split('/\s+/', trim($el->getAttribute('class'))) ?: [], true);
}

/** Text of an element that must contain no child elements; null otherwise. */
function plain_text(?DOMElement $el): ?string
{
    if (!$el || el_children($el)) {
        return null;
    }
    return collapse_ws($el->textContent);
}

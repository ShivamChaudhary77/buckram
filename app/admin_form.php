<?php
/**
 * Dashboard forms, generated from the field map (admin_fields.php).
 *
 * form_field()   prints one field (recursively for groups and repeaters)
 * form_collect() turns the submitted values back into page / settings data,
 *                cleaning every value on the way (text, HTML, links, numbers).
 *
 * Input names are written by the server (so the form works without JavaScript)
 * and recomputed by admin.js before submit, after sections or items were added,
 * removed or moved: every wrapper with data-part / data-item contributes one step.
 */

/** "page[seo][title]" from ['page', 'seo', 'title']. */
function form_name(array $path): string
{
    $first = array_shift($path);
    return $first . implode('', array_map(function ($p) {
        return '[' . $p . ']';
    }, $path));
}

function form_value_or_default(array $spec, $data)
{
    if (is_array($data) && array_key_exists($spec['key'], $data)) {
        return $data[$spec['key']];
    }
    return $spec['default'] ?? null;
}

/** Print a list of fields; advanced ones go into a collapsed "More options" box. */
function form_fields(array $specs, $data, array $path): string
{
    $main = '';
    $adv = '';
    foreach ($specs as $spec) {
        if (($spec['type'] ?? '') === 'heading') {
            $main .= '<h2 class="form-section-title">' . e($spec['label']) . "</h2>\n";
            continue;
        }
        $html = form_field($spec, $spec['key'] === '' ? $data : form_value_or_default($spec, $data), $path);
        if (!empty($spec['advanced'])) {
            $adv .= $html;
        } else {
            $main .= $html;
        }
    }
    if ($adv !== '') {
        $main .= '<details class="adv"><summary><i class="fas fa-sliders-h"></i> More options</summary><div class="adv-body">' . $adv . '</div></details>';
    }
    return $main;
}

function form_field(array $spec, $value, array $path): string
{
    $type = $spec['type'];
    $key = $spec['key'];
    $myPath = $key === '' ? $path : array_merge($path, [$key]);
    $name = form_name($myPath);
    $id = 'f-' . substr(sha1($name . mt_rand()), 0, 10);
    $label = '<label class="form-label" for="' . $id . '">' . e($spec['label']) . '</label>';
    $help = !empty($spec['help']) ? '<div class="form-text">' . e($spec['help']) . '</div>' : '';
    $part = $key === '' ? '' : ' data-part="' . e($key) . '"';
    $ph = !empty($spec['placeholder']) ? ' placeholder="' . e($spec['placeholder']) . '"' : '';
    $max = !empty($spec['max']) ? ' data-max="' . (int) $spec['max'] . '"' : '';

    switch ($type) {
        case 'group':
            return '<fieldset class="fld-group"' . $part . '><legend>' . e($spec['label']) . '</legend>' . $help
                . form_fields($spec['fields'], is_array($value) ? $value : [], $myPath) . '</fieldset>';

        case 'repeater':
            return form_repeater($spec, is_array($value) ? array_values($value) : [], $myPath, $label, $help, $part);

        case 'list':
        case 'numbers':
            $items = is_array($value) ? array_values($value) : [];
            $rich = !empty($spec['rich']);
            $row = function ($v, $i) use ($myPath, $rich, $type) {
                $n = form_name(array_merge($myPath, [$i]));
                $input = $rich
                    ? form_rich_input($n, (string) $v, 'inline', '')
                    : '<input class="form-control form-control-sm js-name" name="' . e($n) . '" value="' . e((string) $v) . '"' . ($type === 'numbers' ? ' type="number" step="50" min="0"' : ' type="text"') . '>';
                return '<div class="list-item" data-item>' . '<span class="drag" title="Move">⋮⋮</span>' . $input
                    . '<button type="button" class="btn-icon js-up" title="Move up"><i class="fas fa-arrow-up"></i></button>'
                    . '<button type="button" class="btn-icon js-down" title="Move down"><i class="fas fa-arrow-down"></i></button>'
                    . '<button type="button" class="btn-icon js-remove" title="Remove"><i class="fas fa-times"></i></button></div>';
            };
            $html = '';
            foreach ($items as $i => $v) {
                $html .= $row($v, $i);
            }
            return '<div class="fld fld-list"' . $part . ' data-rep>' . $label . $help . '<div class="rep-items">' . $html . '</div>'
                . '<template>' . $row('', '__i__') . '</template>'
                . '<button type="button" class="btn btn-sm btn-outline-secondary js-add"><i class="fas fa-plus"></i> Add ' . e($spec['item'] ?? 'item') . '</button></div>';

        case 'table':
            $rows = is_array($value) ? array_values($value) : [];
            $cols = max(2, ...array_map(function ($r) {
                return is_array($r) ? count($r) : 0;
            }, $rows ?: [[]]));
            $mkRow = function ($cells, $i) use ($myPath, $cols) {
                $h = '<div class="table-row" data-item><span class="drag">⋮⋮</span><div class="cells">';
                for ($c = 0; $c < $cols; $c++) {
                    $h .= '<div class="cell" data-item>' . form_rich_input(form_name(array_merge($myPath, [$i, $c])), (string) ($cells[$c] ?? ''), 'inline', '') . '</div>';
                }
                return $h . '</div><button type="button" class="btn-icon js-up"><i class="fas fa-arrow-up"></i></button>'
                    . '<button type="button" class="btn-icon js-down"><i class="fas fa-arrow-down"></i></button>'
                    . '<button type="button" class="btn-icon js-remove"><i class="fas fa-times"></i></button></div>';
            };
            $html = '';
            foreach ($rows as $i => $r) {
                $html .= $mkRow(is_array($r) ? array_values($r) : [], $i);
            }
            return '<div class="fld fld-table"' . $part . ' data-rep data-table>' . $label . $help . '<div class="rep-items">' . $html . '</div>'
                . '<template>' . $mkRow([], '__i__') . '</template>'
                . '<button type="button" class="btn btn-sm btn-outline-secondary js-add"><i class="fas fa-plus"></i> Add row</button></div>';

        case 'checkbox':
            return '<div class="fld form-check"' . $part . '><input type="hidden" class="js-name" name="' . e($name) . '" value="0">'
                . '<input class="form-check-input js-name" type="checkbox" value="1" id="' . $id . '" name="' . e($name) . '"' . (!empty($value) ? ' checked' : '') . '>'
                . '<label class="form-check-label" for="' . $id . '">' . e($spec['label']) . '</label>' . $help . '</div>';

        case 'select':
            $opts = $spec['options'];
            $v = (string) ($value ?? '');
            if (!array_key_exists($v, $opts)) {
                $opts = [$v => ($v === '' ? '(default)' : 'Custom: ' . $v)] + $opts;
            }
            $o = '';
            foreach ($opts as $ov => $ol) {
                $o .= '<option value="' . e((string) $ov) . '"' . ((string) $ov === $v ? ' selected' : '') . '>' . e($ol) . '</option>';
            }
            return '<div class="fld"' . $part . '>' . $label . '<select class="form-select js-name" id="' . $id . '" name="' . e($name) . '">' . $o . '</select>' . $help . '</div>';

        case 'page':
            $o = '<option value="">— choose a product —</option>';
            foreach (pages_all() as $pid => $p) {
                if (($p['type'] ?? '') === 'product') {
                    $o .= '<option value="' . e($pid) . '"' . ($pid === $value ? ' selected' : '') . '>' . e($p['title'] ?? $pid) . (($p['status'] ?? '') !== 'published' ? ' (draft)' : '') . '</option>';
                }
            }
            return '<div class="fld"' . $part . '>' . $label . '<select class="form-select js-name js-item-label" id="' . $id . '" name="' . e($name) . '">' . $o . '</select>' . $help . '</div>';

        case 'number':
            return '<div class="fld"' . $part . '>' . $label . '<input type="number" class="form-control js-name" id="' . $id . '" name="' . e($name) . '" value="' . e((string) (int) $value) . '" step="50" min="0">' . $help . '</div>';

        case 'textarea':
        case 'code':
        case 'json':
            $v = $type === 'json' ? json_encode(is_array($value) ? $value : [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $value;
            $rows = (int) ($spec['rows'] ?? ($type === 'textarea' ? 3 : 10));
            $cls = $type === 'textarea' ? 'form-control js-name' : 'form-control code js-name';
            return '<div class="fld"' . $part . '>' . $label . '<textarea class="' . $cls . '" id="' . $id . '" name="' . e($name) . '" rows="' . $rows . '"' . $max . $ph . ' spellcheck="' . ($type === 'textarea' ? 'true' : 'false') . '">' . e($v) . '</textarea>' . ($max ? '<div class="counter"></div>' : '') . $help . '</div>';

        case 'inline':
        case 'richtext':
            return '<div class="fld"' . $part . '>' . $label . form_rich_input($name, (string) $value, $type, $id) . $help . '</div>';

        case 'image':
            $v = (string) $value;
            return '<div class="fld fld-image"' . $part . '>' . $label . '<div class="image-field">'
                . '<img class="thumb" alt="" src="' . e($v) . '"' . ($v === '' ? ' hidden' : '') . '>'
                . '<input type="text" class="form-control js-name js-image" id="' . $id . '" name="' . e($name) . '" value="' . e($v) . '" placeholder="/assets/images/…">'
                . '<button type="button" class="btn btn-outline-secondary js-pick-image"><i class="fas fa-images"></i> Choose</button></div>' . $help . '</div>';

        case 'icon':
            $v = (string) $value;
            return '<div class="fld fld-icon"' . $part . '>' . $label . '<div class="input-group"><span class="input-group-text icon-preview"><i class="' . e($v) . '"></i></span>'
                . '<input type="text" class="form-control js-name js-icon" id="' . $id . '" name="' . e($name) . '" value="' . e($v) . '"' . ($ph ?: ' placeholder="fas fa-check"') . '></div>'
                . '<div class="form-text">Icon names come from <a href="https://fontawesome.com/v6/search?o=r&m=free" target="_blank" rel="noopener">Font Awesome</a>, e.g. <code>fas fa-industry</code>.</div>' . $help . '</div>';

        case 'url':
        case 'text':
        default:
            $cls = 'form-control js-name' . (($spec['itemLabelField'] ?? false) ? ' js-item-label' : '');
            return '<div class="fld"' . $part . '>' . $label . '<input type="text" class="' . $cls . '" id="' . $id . '" name="' . e($name) . '" value="' . e((string) $value) . '"' . $max . $ph . '>' . ($max ? '<div class="counter"></div>' : '') . $help . '</div>';
    }
}

/** Rich-text editor: a visual box synced to a hidden textarea (the textarea is what gets saved). */
function form_rich_input(string $name, string $value, string $mode, string $id): string
{
    $tools = '<button type="button" data-cmd="bold" title="Bold"><i class="fas fa-bold"></i></button>'
        . '<button type="button" data-cmd="italic" title="Italic"><i class="fas fa-italic"></i></button>'
        . '<button type="button" data-cmd="link" title="Link"><i class="fas fa-link"></i></button>'
        . '<button type="button" data-cmd="unlink" title="Remove link"><i class="fas fa-unlink"></i></button>';
    if ($mode === 'richtext') {
        $tools .= '<button type="button" data-cmd="insertUnorderedList" title="Bullet list"><i class="fas fa-list-ul"></i></button>'
            . '<button type="button" data-cmd="formatBlock" data-arg="p" title="Paragraph"><i class="fas fa-paragraph"></i></button>';
    }
    $tools .= '<button type="button" data-cmd="removeFormat" title="Clear formatting"><i class="fas fa-eraser"></i></button>'
        . '<button type="button" class="js-html-toggle" title="Show the HTML code">HTML</button>';
    return '<div class="rich rich-' . $mode . '">'
        . '<div class="rich-tools">' . $tools . '</div>'
        . '<div class="rich-area" contenteditable="true"' . ($id ? ' aria-labelledby="' . $id . '"' : '') . '></div>'
        . '<textarea class="form-control code rich-source js-name"' . ($id ? ' id="' . $id . '"' : '') . ' name="' . e($name) . '" rows="' . ($mode === 'richtext' ? 6 : 2) . '" hidden>' . e($value) . '</textarea>'
        . '</div>';
}

function form_repeater(array $spec, array $items, array $path, string $label, string $help, string $part): string
{
    $itemLabelKey = $spec['itemLabel'] ?? null;
    $fields = array_map(function ($f) use ($itemLabelKey) {
        if ($f['key'] === $itemLabelKey) {
            $f['itemLabelField'] = true;
        }
        return $f;
    }, $spec['fields']);
    $one = function ($item, $i) use ($fields, $path, $spec, $itemLabelKey) {
        $title = $itemLabelKey && is_array($item) ? (string) ($item[$itemLabelKey] ?? '') : '';
        if (($spec['fields'][0]['type'] ?? '') === 'page' && $title !== '') {
            $p = page_load($title);
            $title = $p['title'] ?? $title;
        }
        return '<div class="rep-item" data-item>'
            . '<div class="rep-head"><span class="drag">⋮⋮</span><button type="button" class="rep-toggle js-toggle"><i class="fas fa-chevron-right"></i> <span class="rep-title">'
            . e($title !== '' ? $title : 'New ' . ($spec['item'] ?? 'item')) . '</span></button>'
            . '<button type="button" class="btn-icon js-up" title="Move up"><i class="fas fa-arrow-up"></i></button>'
            . '<button type="button" class="btn-icon js-down" title="Move down"><i class="fas fa-arrow-down"></i></button>'
            . '<button type="button" class="btn-icon js-remove" title="Remove"><i class="fas fa-trash-alt"></i></button></div>'
            . '<div class="rep-body" hidden>' . form_fields($fields, is_array($item) ? $item : [], array_merge($path, [$i])) . '</div></div>';
    };
    $html = '';
    foreach ($items as $i => $item) {
        $html .= $one($item, $i);
    }
    return '<div class="fld fld-repeater"' . $part . ' data-rep>' . $label . $help
        . '<div class="rep-items">' . $html . '</div>'
        . '<template>' . $one([], '__i__') . '</template>'
        . '<button type="button" class="btn btn-sm btn-outline-secondary js-add"><i class="fas fa-plus"></i> Add ' . e($spec['item'] ?? 'item') . '</button></div>';
}

/* ================================================================ collect */

/** True when a collected item holds nothing worth keeping. */
function form_is_empty($v): bool
{
    if (is_array($v)) {
        foreach ($v as $x) {
            if (!form_is_empty($x)) {
                return false;
            }
        }
        return true;
    }
    return $v === '' || $v === null || $v === false;
}

/**
 * Clean submitted values for $specs. $orig = the stored data (unknown keys are kept).
 * Problems are added to $errors in plain words.
 */
function form_collect(array $specs, $posted, $orig, array &$errors, string $where = ''): array
{
    $posted = is_array($posted) ? $posted : [];
    $out = is_array($orig) ? $orig : [];
    foreach ($specs as $spec) {
        if (($spec['type'] ?? '') === 'heading') {
            continue;
        }
        $key = $spec['key'];
        $raw = $key === '' ? $posted : ($posted[$key] ?? null);
        $old = $key === '' ? $orig : ($out[$key] ?? null);
        $value = form_collect_value($spec, $raw, $old, $errors, trim($where . ' › ' . $spec['label'], ' ›'));
        if ($key === '') {
            $out = array_merge($out, is_array($value) ? $value : []);
        } else {
            $out[$key] = $value;
        }
    }
    return $out;
}

function form_collect_value(array $spec, $raw, $old, array &$errors, string $where)
{
    switch ($spec['type']) {
        case 'group':
            return form_collect($spec['fields'], $raw, is_array($old) ? $old : [], $errors, $where);

        case 'repeater':
            $items = [];
            if (is_array($raw)) {
                ksort($raw, SORT_NUMERIC);
                foreach ($raw as $item) {
                    $v = form_collect($spec['fields'], $item, [], $errors, $where);
                    $meaningful = array_filter($spec['fields'], function ($f) {
                        return !in_array($f['type'], ['select'], true);
                    });
                    $check = array_intersect_key($v, array_flip(array_column($meaningful, 'key')));
                    if (!form_is_empty($check)) {
                        $items[] = $v;
                    }
                }
            }
            return $items;

        case 'list':
            $out = [];
            if (is_array($raw)) {
                ksort($raw, SORT_NUMERIC);
                foreach ($raw as $v) {
                    $v = !empty($spec['rich']) ? trim(sanitize_html((string) $v)) : clean_text($v);
                    if ($v !== '') {
                        $out[] = $v;
                    }
                }
            }
            return $out;

        case 'numbers':
            $out = [];
            if (is_array($raw)) {
                ksort($raw, SORT_NUMERIC);
                foreach ($raw as $v) {
                    if (trim((string) $v) !== '') {
                        $out[] = max(0, (int) $v);
                    }
                }
            }
            return $out;

        case 'table':
            $rows = [];
            if (is_array($raw)) {
                ksort($raw, SORT_NUMERIC);
                foreach ($raw as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    ksort($row, SORT_NUMERIC);
                    $cells = array_map(function ($c) {
                        return trim(sanitize_html((string) $c));
                    }, array_values($row));
                    if (!form_is_empty($cells)) {
                        $rows[] = $cells;
                    }
                }
            }
            return $rows;

        case 'checkbox':
            return is_array($raw) ? in_array('1', $raw, true) : (string) $raw === '1';

        case 'number':
            return max(0, (int) $raw);

        case 'select':
            // The list holds the usual choices; a custom value kept from the old site
            // (shown as "Custom: …") is allowed when it is a plain layout code.
            $v = (string) ($raw ?? '');
            if (!array_key_exists($v, $spec['options']) && !preg_match('/^[A-Za-z0-9 _.-]{0,120}$/', $v)) {
                return (string) (array_key_first($spec['options']) ?? '');
            }
            return $v;

        case 'page':
            $v = (string) ($raw ?? '');
            return valid_id($v) ? $v : '';

        case 'inline':
        case 'richtext':
        case 'code':
            $v = str_replace("\r\n", "\n", (string) ($raw ?? ''));
            // Untouched HTML is stored exactly as it was (keeps the original formatting).
            if (is_string($old) && $v === $old) {
                return $old;
            }
            $clean = sanitize_html($v);
            return $spec['type'] === 'code' ? rtrim($clean) : trim($clean);

        case 'json':
            $v = trim((string) ($raw ?? ''));
            if ($v === '') {
                return [];
            }
            $d = json_decode($v, true);
            if (!is_array($d) || ($d !== [] && array_keys($d) !== range(0, count($d) - 1))) {
                $errors[] = $where . ': this must be valid JSON in square brackets [ … ].';
                return is_array($old) ? $old : [];
            }
            return $d;

        case 'url':
        case 'image':
            $v = clean_text($raw);
            if ($v !== '' && !sanitize_url_ok($v)) {
                $errors[] = $where . ': links must start with /, https:// , mailto: or tel:.';
                return is_string($old) ? $old : '';
            }
            return $v;

        case 'textarea':
            $v = str_replace("\r\n", "\n", (string) ($raw ?? ''));
            return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v));

        case 'icon':
            $v = clean_text($raw);
            return preg_match('/^[a-z0-9 _-]*$/i', $v) ? $v : '';

        case 'text':
        default:
            return clean_text($raw);
    }
}

/**
 * Turn the submitted editor form into a page. Keeps everything the form does not
 * show (id, type, created, unknown keys) from $orig.
 */
function form_collect_page(array $post, array $orig, array &$errors): array
{
    $page = form_collect(page_fields((string) ($orig['type'] ?? 'page')), $post['page'] ?? [], $orig, $errors);
    $page['status'] = ($post['page']['status'] ?? $orig['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $types = block_types();
    $blocks = [];
    $raw = is_array($post['blocks'] ?? null) ? $post['blocks'] : [];
    ksort($raw, SORT_NUMERIC);
    foreach ($raw as $n => $b) {
        $type = (string) ($b['type'] ?? '');
        if (!isset($types[$type])) {
            $errors[] = 'Section ' . ((int) $n + 1) . ' has an unknown type.';
            continue;
        }
        $extra = json_decode((string) ($b['_extra'] ?? '{}'), true);
        $extra = is_array($extra) ? $extra : [];
        unset($extra['hidden']);
        $collected = form_collect($types[$type]['fields'], $b, [], $errors, 'Section ' . ((int) $n + 1) . ' (' . $types[$type]['label'] . ')');
        $block = ['type' => $type] + $collected + $extra;
        $hidden = $b['hidden'] ?? '0';
        if (is_array($hidden) ? in_array('1', $hidden, true) : (string) $hidden === '1') {
            $block['hidden'] = true;
        }
        $blocks[] = $block;
    }
    $page['blocks'] = $blocks;
    return $page;
}

/** Fields of a block that the form doesn't show (kept as they are). */
function block_extra(array $block): array
{
    $types = block_types();
    $known = ['type', 'hidden'];
    foreach ($types[$block['type']]['fields'] ?? [] as $f) {
        $known[] = $f['key'];
    }
    return array_diff_key($block, array_flip($known));
}

/** One section card in the editor. */
function form_block(array $block, $index): string
{
    $types = block_types();
    $type = $block['type'] ?? 'html';
    $def = $types[$type] ?? $types['html'];
    $path = ['blocks', $index];
    $title = (string) (($block['label'] ?? '') ?: ($block['heading'] ?? '') ?: ($block['eyebrow'] ?? ''));
    if ($title === '' && $type === 'html') {
        $title = trim(mb_strimwidth(strip_tags((string) ($block['html'] ?? '')), 0, 60, '…'));
        $title = (string) preg_replace('/\s+/', ' ', $title);
    }
    $extra = block_extra($block);
    return '<div class="block-card' . (!empty($block['hidden']) ? ' is-hidden' : '') . '" data-item data-type="' . e($type) . '">'
        . '<div class="block-head">'
        . '<span class="drag" title="Drag to move">⋮⋮</span>'
        . '<button type="button" class="block-toggle js-toggle"><i class="fas fa-chevron-right"></i> <i class="fas ' . e($def['icon']) . ' type-icon"></i> '
        . '<span class="block-type">' . e($def['label']) . '</span> <span class="block-title rep-title">' . e($title) . '</span>'
        . (!empty($block['hidden']) ? ' <span class="badge text-bg-secondary">Hidden</span>' : '') . '</button>'
        . '<button type="button" class="btn-icon js-up" title="Move up"><i class="fas fa-arrow-up"></i></button>'
        . '<button type="button" class="btn-icon js-down" title="Move down"><i class="fas fa-arrow-down"></i></button>'
        . '<button type="button" class="btn-icon js-remove" title="Remove section"><i class="fas fa-trash-alt"></i></button>'
        . '</div>'
        . '<div class="block-body" hidden>'
        . '<p class="block-hint">' . e($def['hint']) . '</p>'
        . '<input type="hidden" class="js-name" data-part-leaf="type" name="' . e(form_name(array_merge($path, ['type']))) . '" value="' . e($type) . '">'
        . '<input type="hidden" class="js-name" data-part-leaf="_extra" name="' . e(form_name(array_merge($path, ['_extra']))) . '" value="' . e(json_encode((object) $extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '">'
        . form_field(['key' => 'hidden', 'label' => 'Hide this section on the website', 'type' => 'checkbox'], !empty($block['hidden']), $path)
        . form_fields(array_values(array_filter($def['fields'], function ($f) {
            return $f['key'] !== 'hidden';
        })), $block, $path)
        . '</div></div>';
}

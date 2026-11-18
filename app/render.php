<?php
/**
 * Rendering: page → full HTML using templates/layout.php and one template per block
 * type in templates/blocks/. Templates only print data; all decisions live here.
 */

/** Render templates/<name>.php with $vars as local variables. */
function render_template(string $name, array $vars = []): string
{
    if (!preg_match('#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#', $name)) {
        throw new InvalidArgumentException("Bad template name: $name");
    }
    $file = TEMPLATE_DIR . '/' . $name . '.php';
    if (!is_file($file)) {
        throw new RuntimeException("Template not found: $name");
    }
    extract($vars, EXTR_SKIP);
    ob_start();
    try {
        include $file;
    } catch (Throwable $t) {
        ob_end_clean();
        throw $t;
    }
    return (string) ob_get_clean();
}

function template_exists(string $name): bool
{
    return preg_match('#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#', $name) === 1 && is_file(TEMPLATE_DIR . '/' . $name . '.php');
}

/**
 * Full HTML for a page.
 * $opts: preview (bool) – adds noindex and never caches.
 */
function render_page(array $page, array $opts = []): string
{
    $site = site_load();
    $html = render_template('layout', [
        'site' => $site,
        'page' => $page,
        'head' => page_head($page, $site, $opts),
        'body' => render_blocks($page, $site),
        'opts' => $opts,
    ]);
    return add_image_dimensions($html);
}

/** Official address of a page (handles the home page setting). */
function page_canonical_path(array $page): string
{
    $path = (string) ($page['path'] ?? '/');
    if ($path === '/index.html' && config('home_path') === '/') {
        return '/';
    }
    return $path;
}

/** Everything the <head> needs, with fallbacks from site.json. */
function page_head(array $page, array $site, array $opts = []): array
{
    $seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];
    $brand = (string) ($site['brand'] ?? $site['name'] ?? '');
    $title = trim((string) ($seo['title'] ?? ''));
    if ($title === '') {
        $title = trim((string) ($page['title'] ?? ''));
        if ($brand !== '' && $title !== $brand) {
            $title .= ' | ' . $brand;
        }
    }
    $description = trim((string) ($seo['description'] ?? ''));
    $canonical = trim((string) ($seo['canonical'] ?? ''));
    $canonical = $canonical !== '' ? absolute_url($canonical) : absolute_url(page_canonical_path($page));

    if (!empty($opts['preview'])) {
        $robots = 'noindex,nofollow';
    } elseif (!empty($seo['noindex']) || ($page['status'] ?? 'published') !== 'published') {
        $robots = 'noindex,follow';
    } else {
        $robots = 'index,follow,max-image-preview:large';
    }

    $ogImage = trim((string) ($seo['ogImage'] ?? '')) ?: (string) ($site['defaultOgImage'] ?? '');
    $ogSize = $ogImage !== '' ? local_image_size($ogImage) : null;

    $head = [
        'lang'          => (string) ($site['lang'] ?? 'en'),
        'title'         => $title,
        'description'   => $description,
        'canonical'     => $canonical,
        'robots'        => $robots,
        'siteName'      => $brand,
        'ogTitle'       => trim((string) ($seo['ogTitle'] ?? '')) ?: $title,
        'ogDescription' => trim((string) ($seo['ogDescription'] ?? '')) ?: $description,
        'ogType'        => trim((string) ($seo['ogType'] ?? '')) ?: 'website',
        'ogImage'       => $ogImage !== '' ? absolute_url($ogImage) : '',
        'ogImageWidth'  => $ogSize[0] ?? null,
        'ogImageHeight' => $ogSize[1] ?? null,
        // A large preview only makes sense for a large picture (a logo gets the small card).
        'twitterCard'   => ($ogSize && $ogSize[0] >= 600 && $ogSize[0] / max(1, $ogSize[1]) > 1.3) ? 'summary_large_image' : 'summary',
        'favicon'       => (string) ($site['favicon'] ?? ''),
        'tracking'      => empty($opts['preview']) ? (array) ($site['tracking'] ?? []) : [],
    ];

    // Structured data: the generated graph, plus any extra data added on the page.
    $jsonld = [seo_graph($page, $site, $head)];
    foreach (($page['jsonld'] ?? []) as $item) {
        if (is_array($item)) {
            $jsonld[] = $item;
        }
    }
    $head['jsonld'] = $jsonld;
    return $head;
}

/** HTML for all blocks of a page, in order. */
function render_blocks(array $page, array $site): string
{
    $out = '';
    foreach (array_values($page['blocks'] ?? []) as $i => $block) {
        $out .= render_block($block, ['page' => $page, 'site' => $site, 'index' => $i]);
    }
    return $out;
}

function render_block(array $block, array $ctx): string
{
    $type = (string) ($block['type'] ?? '');
    if ($type === '' || !template_exists('blocks/' . $type)) {
        return config('debug') ? '<!-- unknown block type: ' . e($type) . " -->\n" : '';
    }
    if (!empty($block['hidden'])) {
        return '';
    }
    return render_template('blocks/' . $type, $ctx + ['b' => $block]);
}

/* ---------------------------------------------------- template helpers */

/** ' class="bg-soft"' for a section, or '' when no background is set. */
function section_attr(array $b): string
{
    $bg = trim((string) ($b['bg'] ?? ''));
    return $bg !== '' ? ' class="' . e($bg) . '"' : '';
}

/** How many cards share a row on large screens, from a column class like "col-lg-3 col-sm-6". */
function cards_per_row(string $colClass): int
{
    if (preg_match('/col-(?:xl|lg)-(\d+)/', $colClass, $m) || preg_match('/col-md-(\d+)/', $colClass, $m)) {
        $n = (int) $m[1];
        return $n > 0 ? max(1, intdiv(12, $n)) : 1;
    }
    return 1;
}

/**
 * Staggered animation delay used by the theme: 0, 100, 200… restarting each row
 * ($mode "row"), or growing for every card ($mode "linear").
 */
function aos_delay(int $i, int $perRow, string $mode = 'row'): string
{
    $d = ($mode === 'linear' || $perRow < 1 ? $i : $i % $perRow) * 100;
    return $d > 0 ? ' data-aos-delay="' . $d . '"' : '';
}

/** Animation delay for card $i of a grid block (delayMode row | linear | custom with "delays" list). */
function block_delay(array $b, int $i, int $perRow): string
{
    $mode = (string) ($b['delayMode'] ?? 'row');
    if ($mode === 'custom' && isset($b['delays'][$i])) {
        $d = (int) $b['delays'][$i];
        return $d > 0 ? ' data-aos-delay="' . $d . '"' : '';
    }
    return aos_delay($i, $perRow, $mode === 'linear' ? 'linear' : 'row');
}

/** <i class="fas fa-x"></i> or ''. */
function icon(?string $class, string $extra = ''): string
{
    $class = trim((string) $class . ' ' . $extra);
    return $class !== '' ? '<i class="' . e($class) . '"></i>' : '';
}

/**
 * A link styled as a button.
 * $btn: label, href, class, iconBefore, iconAfter, newTab
 */
function button_html(array $btn): string
{
    $label = (string) ($btn['label'] ?? '');
    $href = (string) ($btn['href'] ?? '');
    if ($label === '' || $href === '') {
        return '';
    }
    $attrs = ' href="' . e($href) . '"';
    if (!empty($btn['newTab'])) {
        $attrs .= ' target="_blank" rel="noopener"';
    }
    if (!empty($btn['class'])) {
        $attrs .= ' class="' . e($btn['class']) . '"';
    }
    $before = !empty($btn['iconBefore']) ? icon($btn['iconBefore']) : '';
    $after = !empty($btn['iconAfter']) ? ' ' . icon($btn['iconAfter']) : '';
    return '<a' . $attrs . '>' . $before . e($label) . $after . '</a>';
}

/** Breadcrumb trail for a page: [['label'=>…, 'href'=>…], …, ['label'=>current]]. */
function breadcrumb_items(array $page): array
{
    $items = [];
    foreach (($page['breadcrumb'] ?? []) as $crumb) {
        if (is_array($crumb) && ($crumb['label'] ?? '') !== '') {
            $items[] = ['label' => (string) $crumb['label'], 'href' => (string) ($crumb['href'] ?? '')];
        }
    }
    $items[] = ['label' => (string) (($page['breadcrumbLabel'] ?? '') ?: ($page['title'] ?? '')), 'href' => ''];
    return $items;
}

/**
 * Data for one product card. $ref = ['page' => 'buckram-fabric'] plus optional
 * overrides (title, tag, summary, image, imageAlt, href). Returns null if the
 * product page is missing or unpublished.
 */
function product_card(array $ref): ?array
{
    $id = (string) ($ref['page'] ?? '');
    $target = $id !== '' ? page_load($id) : null;
    if ($id !== '' && (!$target || ($target['status'] ?? '') !== 'published')) {
        return null;
    }
    $card = is_array($target['card'] ?? null) ? $target['card'] : [];
    $pick = function (string $key) use ($ref, $card) {
        $v = $ref[$key] ?? '';
        return $v !== '' && $v !== null ? (string) $v : (string) ($card[$key] ?? '');
    };
    $title = $pick('title');
    if ($title === '' && $target) {
        $title = (string) ($target['title'] ?? '');
    }
    $href = (string) ($ref['href'] ?? '') ?: ($target ? page_canonical_path($target) : '');
    return [
        'title'    => $title,
        'tag'      => $pick('tag'),
        'summary'  => $pick('summary'),
        'image'    => $pick('image'),
        'imageAlt' => $pick('imageAlt'),
        'href'     => $href,
    ];
}

/** Social links from site.json, in order. */
function site_social(array $site): array
{
    return array_values(array_filter($site['social'] ?? [], function ($s) {
        return is_array($s) && ($s['url'] ?? '') !== '';
    }));
}

function whatsapp_url(array $site, bool $withMessage = false): string
{
    $num = preg_replace('/\D+/', '', (string) ($site['whatsapp']['number'] ?? ''));
    if ($num === '') {
        return '';
    }
    $url = 'https://wa.me/' . $num;
    $msg = (string) ($site['whatsapp']['message'] ?? '');
    if ($withMessage && $msg !== '') {
        $url .= '?text=' . rawurlencode($msg);
    }
    return $url;
}

/** The branded "page not found" page (sent with status 404). */
function render_not_found(): string
{
    $site = site_load();
    $page = [
        'id'     => 'not-found',
        'type'   => 'page',
        'status' => 'published',
        'path'   => '/404.html',
        'title'  => 'Page Not Found',
        'seo'    => ['noindex' => true],
        'blocks' => [
            [
                'type'       => 'page-hero',
                'icon'       => 'fas fa-compass',
                'eyebrow'    => 'Error 404',
                'heading'    => 'Page Not Found',
                'intro'      => 'The page you are looking for has moved or no longer exists.',
                'image'      => (string) ($site['notFoundImage'] ?? ''),
                'breadcrumb' => false,
            ],
            [
                'type' => 'cta',
                'heading' => 'Let’s Get You Back On Track',
                'text' => 'Browse our product range or contact our team directly.',
                'buttons' => [
                    ['label' => 'Go To Home Page', 'href' => config('home_path'), 'class' => 'btn btn-gold btn-lg-custom'],
                    ['label' => 'View Products', 'href' => '/products.html', 'class' => 'btn btn-outline-light-custom btn-lg-custom'],
                ],
            ],
        ],
    ];
    return render_page($page); // 'noindex' above keeps it out of Google
}

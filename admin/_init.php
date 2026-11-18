<?php
/**
 * Shared start of every dashboard screen: loads the app, sends safety headers,
 * provides login checks, CSRF checks, flash messages and the page layout.
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_DIR . '/security.php';
require_once APP_DIR . '/sanitize.php';
require_once APP_DIR . '/media.php';
require_once APP_DIR . '/admin_fields.php';
require_once APP_DIR . '/admin_form.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

function admin_url(string $page = '', array $query = []): string
{
    return '/admin/' . $page . ($query ? '?' . http_build_query($query) : '');
}

function redirect_to(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

function require_login(): void
{
    if (!password_is_set()) {
        redirect_to(admin_url('setup.php'));
    }
    if (!admin_logged_in()) {
        $next = (string) ($_SERVER['REQUEST_URI'] ?? '');
        redirect_to(admin_url('login.php', strpos($next, '/admin/') === 0 ? ['next' => $next] : []));
    }
}

/** Stop unless this is a POST with a valid CSRF token. */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Please use the buttons in the dashboard.');
    }
    if (!csrf_valid($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        admin_page_start('Please try again', '');
        echo '<div class="alert alert-warning">This form had expired (or was sent from somewhere else). Nothing was changed. Please go back, reload the page and try again.</div>';
        admin_page_end();
        exit;
    }
}

function flash(string $type, string $message): void
{
    admin_session_start();
    $_SESSION['flash'][] = [$type, $message];
}

function flash_html(): string
{
    admin_session_start();
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        $icon = ['success' => 'fa-check-circle', 'danger' => 'fa-exclamation-triangle', 'warning' => 'fa-exclamation-circle', 'info' => 'fa-info-circle'][$type] ?? 'fa-info-circle';
        $out .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert"><i class="fas ' . $icon . ' me-2"></i>' . $msg
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

function errors_html(array $errors): string
{
    if (!$errors) {
        return '';
    }
    return '<div class="alert alert-danger"><strong><i class="fas fa-exclamation-triangle me-2"></i>Nothing was saved yet. Please fix:</strong><ul class="mb-0 mt-2">'
        . implode('', array_map(function ($m) {
            return '<li>' . e($m) . '</li>';
        }, array_unique($errors))) . '</ul></div>';
}

function asset_v(string $path): string
{
    $file = ROOT_DIR . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Layout start. $bare = login/setup screens without the sidebar. */
function admin_page_start(string $title, string $active, bool $bare = false, string $actions = ''): void
{
    $site = site_load();
    $brand = (string) ($site['brand'] ?? 'Website');
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> · <?= e($brand) ?> dashboard</title>
<link rel="icon" href="<?= e($site['favicon'] ?? '/assets/images/logo.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= e(asset_v('/admin/assets/admin.css')) ?>">
</head>
<body class="<?= $bare ? 'admin-bare' : 'admin' ?>">
<?php if ($bare): ?>
<main class="bare-main">
  <div class="bare-card">
    <div class="bare-brand"><img src="<?= e($site['logo']['src'] ?? '/assets/images/logo.jpeg') ?>" alt=""><span><?= e($brand) ?><small>Website dashboard</small></span></div>
<?php return; endif;
    $pages = pages_all();
    $drafts = count(array_filter($pages, function ($p) {
        return ($p['status'] ?? '') !== 'published';
    }));
    $trash = count(trash_list());
    $nav = [
        ['index.php', 'pages', 'fa-file-alt', 'Pages', 'Edit the text and pictures of every page', count($pages)],
        ['media.php', 'media', 'fa-images', 'Pictures', 'Upload and find pictures', null],
        ['seo.php', 'seo', 'fa-search', 'Google check', 'What to improve for Google, page by page', null],
        ['redirects.php', 'redirects', 'fa-random', 'Redirects', 'Send old web addresses to new ones', count(redirects_load())],
        ['settings.php', 'settings', 'fa-cog', 'Settings', 'Phone, address, menu, footer', null],
        ['trash.php', 'trash', 'fa-trash-alt', 'Trash', 'Deleted pages you can restore', $trash],
    ];
    ?>
<div class="backdrop" data-close-sidebar></div>
<aside class="sidebar" id="sidebar">
  <a class="side-brand" href="<?= admin_url() ?>"><img src="<?= e($site['logo']['src'] ?? '/assets/images/logo.jpeg') ?>" alt=""><span><?= e($brand) ?><small>Website dashboard</small></span></a>
  <div class="dropdown side-create">
    <button class="btn btn-gold w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-plus me-2"></i>Create new page</button>
    <ul class="dropdown-menu w-100">
<?php foreach (['product', 'page'] as $t): [$l, $ic] = page_type_labels()[$t]; ?>
      <li><a class="dropdown-item" href="<?= e(admin_url('new.php', ['type' => $t])) ?>"><i class="fas <?= e($ic) ?> me-2"></i><?= e($l) ?></a></li>
<?php endforeach; ?>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="<?= e(admin_url('new.php')) ?>"><i class="fas fa-copy me-2"></i>Copy an existing page…</a></li>
    </ul>
  </div>
  <nav class="side-nav">
<?php foreach ($nav as [$href, $key, $icon, $label, $hint, $count]): ?>
    <a href="<?= admin_url($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
      <i class="fas <?= $icon ?>"></i><span><?= e($label) ?><small><?= e($hint) ?></small></span><?php if ($count): ?><em class="count"><?= (int) $count ?></em><?php endif; ?>
    </a>
<?php endforeach; ?>
<?php if ($drafts): ?>
    <a href="<?= admin_url('index.php', ['status' => 'draft']) ?>" class="sub"><i class="fas fa-pencil-alt"></i><span><?= $drafts ?> draft<?= $drafts > 1 ? 's' : '' ?> not published yet</span></a>
<?php endif; ?>
  </nav>
  <div class="side-bottom">
    <a href="<?= e(config('home_path')) ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i>View website</a>
    <a href="<?= admin_url('settings.php') ?>#password"><i class="fas fa-key"></i>Change password</a>
    <form method="post" action="<?= admin_url('logout.php') ?>"><?= csrf_field() ?><button type="submit"><i class="fas fa-sign-out-alt"></i>Log out</button></form>
  </div>
</aside>
<div class="main">
  <header class="topbar-admin">
    <button class="btn-icon menu-btn" type="button" data-open-sidebar aria-label="Open menu"><i class="fas fa-bars"></i></button>
    <h1><?= e($title) ?></h1>
    <div class="top-actions"><?= $actions ?></div>
  </header>
  <div class="content">
<?php if (!config('enabled')): ?>
    <div class="alert alert-info small"><i class="fas fa-info-circle me-2"></i>The website is still running on the old files. Changes you make here go live once the new website is switched on.</div>
<?php endif; ?>
    <?= flash_html() ?>
<?php
}

function admin_page_end(bool $bare = false): void
{
    if ($bare || !admin_logged_in()) {
        echo "  </div>\n</main>\n";
    } else {
        echo "  </div>\n</div>\n";
        echo '<div class="modal fade" id="pickerModal" tabindex="-1" aria-labelledby="pickerTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">'
            . '<div class="modal-header"><h2 class="modal-title h5" id="pickerTitle">Choose a picture</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>'
            . '<div class="modal-body p-0"><iframe title="Pictures" data-picker-frame></iframe></div></div></div></div>' . "\n";
    }
    ?>
<script src="/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_v('/admin/assets/admin.js')) ?>"></script>
</body>
</html>
<?php
}

/** A small "status pill". */
function status_pill(array $page): string
{
    if (($page['status'] ?? '') !== 'published') {
        return '<span class="pill pill-draft" title="Not visible on the website">Draft</span>';
    }
    if (!empty($page['seo']['noindex'])) {
        return '<span class="pill pill-hidden" title="Visible, but hidden from Google (noindex)">Live · hidden from Google</span>';
    }
    return '<span class="pill pill-live">Live</span>';
}

function time_ago(?string $iso): string
{
    $t = $iso ? strtotime($iso) : false;
    if (!$t) {
        return '—';
    }
    $d = time() - $t;
    if ($d < 60) {
        return 'just now';
    }
    if ($d < 3600) {
        return (int) ($d / 60) . ' min ago';
    }
    if ($d < 86400) {
        return (int) ($d / 3600) . ' h ago';
    }
    if ($d < 86400 * 7) {
        return (int) ($d / 86400) . ' days ago';
    }
    return date('j M Y', $t);
}

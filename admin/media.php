<?php
/**
 * Pictures: the website's own pictures and uploaded ones. Also used as the picture
 * picker inside the editor (?pick=1 shows a compact version in a pop-up).
 */
require __DIR__ . '/_init.php';
require_login();

$pick = !empty($_GET['pick']);
if ($pick) {
    header_remove('X-Frame-Options');
    header('X-Frame-Options: SAMEORIGIN'); // shown inside the editor's picture picker
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $back = admin_url('media.php', $pick ? ['pick' => 1] : []);
    try {
        if (($_POST['action'] ?? '') === 'upload') {
            $files = $_FILES['images'] ?? null;
            $urls = [];
            if ($files && is_array($files['name'])) {
                foreach (array_keys($files['name']) as $i) {
                    $urls[] = media_upload([
                        'name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i], 'size' => $files['size'][$i],
                    ]);
                }
            }
            if (!$urls) {
                throw new ContentError(['Please choose a picture first.']);
            }
            flash('success', count($urls) . ' picture' . (count($urls) > 1 ? 's' : '') . ' uploaded. Remember to give each one a description where you use it.');
        } elseif (($_POST['action'] ?? '') === 'delete') {
            media_delete((string) ($_POST['url'] ?? ''));
            flash('success', 'Picture deleted.');
        }
    } catch (ContentError $e) {
        flash('danger', e(implode(' ', $e->errors)));
    }
    redirect_to($back);
}

$all = media_list();
$used = media_used_urls();
$folders = [];
foreach ($all as $m) {
    $folders[$m['source'] . ':' . $m['folder']] = ($m['source'] === 'upload' ? 'Uploaded · ' : '') . $m['folder'];
}
ksort($folders);

if ($pick): ?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Choose a picture</title>
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= e(asset_v('/admin/assets/admin.css')) ?>">
</head><body class="picker">
<?= flash_html() ?>
<?php else:
    admin_page_start('Pictures', 'media');
endif; ?>
<form method="post" action="<?= e(admin_url('media.php', $pick ? ['pick' => 1] : [])) ?>" enctype="multipart/form-data" class="upload-box">
  <?= csrf_field() ?><input type="hidden" name="action" value="upload">
  <i class="fas fa-cloud-upload-alt"></i>
  <div><strong>Upload pictures</strong><br><small class="text-muted">JPG, PNG, WebP or GIF, up to 8 MB. Very wide pictures are made smaller automatically.</small></div>
  <input class="form-control" type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
  <button class="btn btn-navy" type="submit">Upload</button>
</form>

<div class="toolbar">
  <div class="search"><i class="fas fa-search"></i><input type="search" class="form-control" id="mediaSearch" placeholder="Search by file name…" aria-label="Search pictures"></div>
  <select class="form-select w-auto" id="mediaFolder" aria-label="Folder">
    <option value="">All folders (<?= count($all) ?>)</option>
<?php foreach ($folders as $k => $label): ?>
    <option value="<?= e($k) ?>"><?= e($label) ?></option>
<?php endforeach; ?>
  </select>
  <div class="chips"><button type="button" class="chip" data-media-unused>Only unused</button></div>
</div>

<div class="media-grid" id="mediaGrid">
<?php foreach ($all as $m): $isUsed = media_is_used($m['url'], $used); ?>
  <figure class="media-item" data-folder="<?= e($m['source'] . ':' . $m['folder']) ?>" data-name="<?= e(strtolower($m['name'])) ?>" data-used="<?= $isUsed ? '1' : '0' ?>">
    <button type="button" class="media-thumb<?= $pick ? ' js-choose' : ' js-copy' ?>" data-url="<?= e($m['url']) ?>" title="<?= $pick ? 'Use this picture' : 'Copy the address' ?>">
      <img src="<?= e($m['url']) ?>" alt="" loading="lazy">
    </button>
    <figcaption>
      <span class="name" title="<?= e($m['url']) ?>"><?= e($m['name']) ?></span>
      <span class="meta"><?= $m['width'] ? (int) $m['width'] . '×' . (int) $m['height'] . ' · ' : '' ?><?= e(number_format($m['bytes'] / 1024)) ?> KB</span>
      <span class="badges">
        <span class="pill <?= $isUsed ? 'pill-live' : 'pill-draft' ?>"><?= $isUsed ? 'Used' : 'Not used' ?></span>
        <?php if ($m['source'] === 'upload'): ?><span class="pill pill-hidden">Uploaded</span><?php endif; ?>
      </span>
<?php if (!$pick): ?>
      <span class="actions">
        <button type="button" class="btn btn-sm btn-outline-secondary js-copy" data-url="<?= e($m['url']) ?>"><i class="fas fa-copy"></i> Copy address</button>
<?php if ($m['source'] === 'upload' && !$isUsed): ?>
        <form method="post" action="<?= admin_url('media.php') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="url" value="<?= e($m['url']) ?>">
          <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this picture for good?"><i class="fas fa-trash-alt"></i></button></form>
<?php endif; ?>
      </span>
<?php endif; ?>
    </figcaption>
  </figure>
<?php endforeach; ?>
</div>
<p class="empty-note" id="mediaNone" hidden>No pictures match.</p>
<?php if ($pick): ?>
<script src="/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_v('/admin/assets/admin.js')) ?>"></script>
</body></html>
<?php else:
    admin_page_end();
endif;

<?php
require __DIR__ . '/_init.php';
require_login();

$pages = pages_all();
$labels = page_type_labels();
$order = ['home' => 0, 'listing' => 1, 'about' => 2, 'page' => 3, 'contact' => 4, 'product' => 5];
uasort($pages, function ($a, $b) use ($order) {
    return [$order[$a['type']] ?? 9, $a['title'] ?? ''] <=> [$order[$b['type']] ?? 9, $b['title'] ?? ''];
});
$statusFilter = in_array($_GET['status'] ?? '', ['draft', 'published'], true) ? $_GET['status'] : '';
$counts = [];
foreach ($pages as $p) {
    $counts[$p['type']] = ($counts[$p['type']] ?? 0) + 1;
}

admin_page_start('Pages', 'pages', false, '<a class="btn btn-navy btn-sm" href="' . e(admin_url('new.php', ['type' => 'product'])) . '"><i class="fas fa-plus me-1"></i> New product page</a>');
?>
<div class="toolbar">
  <div class="search"><i class="fas fa-search"></i><input type="search" class="form-control" id="pageSearch" placeholder="Search pages by name or address…" aria-label="Search pages"></div>
  <div class="chips" role="group" aria-label="Filter by type">
    <button type="button" class="chip active" data-filter-type="">All <em><?= count($pages) ?></em></button>
<?php foreach ($labels as $t => [$l, $ic]): if (empty($counts[$t])) continue; ?>
    <button type="button" class="chip" data-filter-type="<?= e($t) ?>"><i class="fas <?= e($ic) ?>"></i> <?= e($l) ?> <em><?= $counts[$t] ?></em></button>
<?php endforeach; ?>
  </div>
  <div class="chips" role="group" aria-label="Filter by status">
    <button type="button" class="chip<?= $statusFilter === '' ? ' active' : '' ?>" data-filter-status="">Any status</button>
    <button type="button" class="chip<?= $statusFilter === 'published' ? ' active' : '' ?>" data-filter-status="published">Live</button>
    <button type="button" class="chip<?= $statusFilter === 'draft' ? ' active' : '' ?>" data-filter-status="draft">Drafts</button>
  </div>
</div>

<div class="page-list" id="pageList">
<?php foreach ($pages as $id => $p): [$tl, $ti] = $labels[$p['type']] ?? ['Page', 'fa-file']; ?>
  <div class="page-row" data-type="<?= e($p['type']) ?>" data-status="<?= e($p['status']) ?>" data-search="<?= e(strtolower(($p['title'] ?? '') . ' ' . ($p['path'] ?? '') . ' ' . ($p['seo']['title'] ?? ''))) ?>">
    <div class="type-badge" title="<?= e($tl) ?>"><i class="fas <?= e($ti) ?>"></i></div>
    <div class="page-main">
      <a class="page-title" href="<?= e(admin_url('edit.php', ['id' => $id])) ?>"><?= e($p['title'] ?? $id) ?></a>
      <div class="page-meta"><span class="path"><?= e(page_canonical_path($p)) ?></span> · <?= e($tl) ?> · edited <?= e(time_ago($p['updated'] ?? null)) ?></div>
    </div>
    <div class="page-status"><?= status_pill($p) ?></div>
    <div class="page-actions">
      <a class="btn btn-sm btn-navy" href="<?= e(admin_url('edit.php', ['id' => $id])) ?>"><i class="fas fa-pen me-1"></i>Edit</a>
<?php if (($p['status'] ?? '') === 'published'): ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(page_canonical_path($p)) ?>" target="_blank" rel="noopener" title="Open on the website"><i class="fas fa-external-link-alt"></i><span class="visually-hidden">View</span></a>
<?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
  <p class="empty-note" id="noResults" hidden>No pages match. Try another word or filter.</p>
</div>
<?php admin_page_end();

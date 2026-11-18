<?php
/** Site-wide Google check: every page's problems in one list, plus the generated files. */
require __DIR__ . '/_init.php';
require_login();

$report = seo_report();
$pages = pages_all();
$total = 0;
$bad = 0;
foreach ($report as $checks) {
    $total += count($checks);
    $bad += count(array_filter($checks, function ($c) {
        return $c['level'] === 'bad';
    }));
}
uksort($report, function ($a, $b) use ($report) {
    return count($report[$b]) <=> count($report[$a]);
});

admin_page_start('Google check', 'seo');
?>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card-panel stat"><strong><?= count($pages) ?></strong><span>pages</span></div></div>
  <div class="col-md-4"><div class="card-panel stat"><strong class="<?= $bad ? 'text-danger' : '' ?>"><?= $bad ?></strong><span>problems to fix</span></div></div>
  <div class="col-md-4"><div class="card-panel stat"><strong><?= $total - $bad ?></strong><span>things to improve</span></div></div>
</div>

<div class="card-panel mb-4">
  <h2 class="form-section-title">Made automatically for Google and AI tools</h2>
  <p class="text-muted small">These files are built from your pages every time someone asks for them. You never need to edit them.</p>
  <ul class="mb-0">
    <li><a href="/sitemap.xml" target="_blank" rel="noopener">sitemap.xml</a> — the list of pages for Google (live pages only, not hidden ones)</li>
    <li><a href="/robots.txt" target="_blank" rel="noopener">robots.txt</a> — tells search engines what they may read<?= !empty(site_load()['robots']['blockAI']) ? ' (AI tools are blocked — see Settings)' : '' ?></li>
    <li><a href="/llms.txt" target="_blank" rel="noopener">llms.txt</a> — a short summary of the business for ChatGPT, Perplexity and other AI tools</li>
    <li>Structured data on every page (business details, breadcrumbs, questions & answers) — Google reads it to understand the page</li>
  </ul>
</div>

<div class="page-list">
<?php foreach ($report as $id => $checks): $p = $pages[$id] ?? null; if (!$p) continue; ?>
  <details class="page-row seo-row"<?= array_filter($checks, function ($c) { return $c['level'] === 'bad'; }) ? ' open' : '' ?>>
    <summary>
      <span class="page-title"><?= e($p['title'] ?? $id) ?></span>
      <span class="page-meta"><?= e(page_canonical_path($p)) ?></span>
      <?= $checks ? '<span class="pill pill-draft">' . count($checks) . '</span>' : '<span class="pill pill-live">All good</span>' ?>
      <a class="btn btn-sm btn-navy ms-auto" href="<?= e(admin_url('edit.php', ['id' => $id])) ?>#seo">Fix</a>
    </summary>
<?php if ($checks): ?>
    <ul class="seo-list mt-2">
<?php foreach ($checks as $c): ?>
      <li class="<?= e($c['level']) ?>"><i class="fas <?= $c['level'] === 'bad' ? 'fa-times-circle' : 'fa-exclamation-circle' ?>"></i><?= e($c['text']) ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </details>
<?php endforeach; ?>
</div>
<?php admin_page_end();

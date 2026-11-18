<?php
/** The page editor: sections, Google & sharing, page settings. */
require __DIR__ . '/_init.php';
require_login();

$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$stored = valid_id($id) ? page_load($id) : null;
if (!$stored) {
    http_response_code(404);
    admin_page_start('Page not found', 'pages');
    echo '<div class="alert alert-warning">That page does not exist (it may be in the <a href="' . admin_url('trash.php') . '">Trash</a>).</div>';
    admin_page_end();
    exit;
}

$page = $stored;
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $action = (string) ($_POST['action'] ?? 'save');
    $page = form_collect_page($_POST, $stored, $errors);
    $page['id'] = $stored['id'];
    $page['type'] = $stored['type'];
    if ($action === 'publish') {
        $page['status'] = 'published';
    } elseif ($action === 'unpublish') {
        $page['status'] = 'draft';
    } else {
        $page['status'] = $stored['status'];
    }
    if (($_POST['loaded'] ?? '') !== ($stored['updated'] ?? '')) {
        $errors[] = 'Someone saved this page in another window after you opened it. To avoid overwriting their work, copy your changes, reload the page and make them again.';
    }
    if (!$errors) {
        try {
            [$saved, $messages] = page_save_from_editor($page, $stored);
            $what = ['publish' => 'Published! The page is now live.', 'unpublish' => 'The page is now a draft and no longer on the website.'][$action] ?? 'Saved.';
            if ($action === 'save' && ($saved['status'] ?? '') === 'published') {
                $what = 'Saved. The changes are live.';
            } elseif ($action === 'save') {
                $what = 'Draft saved. It is not on the website until you publish it.';
            }
            flash('success', e($what) . ' ' . implode(' ', array_map('e', $messages)));
            redirect_to(admin_url('edit.php', ['id' => $saved['id']]) . '#' . preg_replace('/[^a-z-]/', '', (string) ($_POST['tab'] ?? '')));
        } catch (ContentError $e) {
            $errors = array_merge($errors, $e->errors);
        }
    }
}

$types = block_types();
$live = ($stored['status'] ?? '') === 'published';
$view = page_canonical_path($stored);
$actions = $live ? '<a class="btn btn-sm btn-outline-secondary" href="' . e($view) . '" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-1"></i>View on website</a>' : '';
admin_page_start($page['title'] ?? $id, 'pages', false, $actions);
echo errors_html($errors);
?>
<form method="post" action="<?= e(admin_url('edit.php', ['id' => $id])) ?>" class="editor" id="editor" data-editor novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($id) ?>">
  <input type="hidden" name="loaded" value="<?= e($stored['updated'] ?? '') ?>">
  <input type="hidden" name="tab" value="" data-current-tab>

  <div class="editor-main">
    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#tab-content" role="tab" data-tab="content"><i class="fas fa-layer-group me-1"></i>Page content</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tab-seo" role="tab" data-tab="seo"><i class="fab fa-google me-1"></i>Google &amp; sharing</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tab-settings" role="tab" data-tab="settings"><i class="fas fa-cog me-1"></i>Page settings</button></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane show active" id="tab-content" role="tabpanel">
        <p class="text-muted small mb-3">The page is made of sections, from top to bottom. Click a section to open it. Use the arrows to move it.</p>
        <div class="blocks" data-part="blocks" data-rep data-blocks>
          <div class="rep-items">
<?php foreach (array_values($page['blocks'] ?? []) as $i => $block): ?>
            <?= form_block($block, $i) ?>
<?php endforeach; ?>
          </div>
<?php foreach ($types as $t => $def): ?>
          <template data-block-template="<?= e($t) ?>"><?= form_block(['type' => $t], '__i__') ?></template>
<?php endforeach; ?>
          <div class="dropdown add-section">
            <button class="btn btn-outline-navy dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-plus me-1"></i> Add a section</button>
            <ul class="dropdown-menu">
<?php foreach ($types as $t => $def): ?>
              <li><button type="button" class="dropdown-item js-add-block" data-type="<?= e($t) ?>"><i class="fas <?= e($def['icon']) ?> me-2"></i><strong><?= e($def['label']) ?></strong><br><small class="text-muted"><?= e($def['hint']) ?></small></button></li>
<?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
<?php
    $pf = page_fields((string) $stored['type']);
    $seoSpec = array_values(array_filter($pf, function ($f) { return $f['key'] === 'seo'; }));
    $rest = array_values(array_filter($pf, function ($f) { return $f['key'] !== 'seo'; }));
?>
      <div class="tab-pane" id="tab-seo" role="tabpanel">
        <div class="snippet" aria-live="polite">
          <div class="snippet-label">How this page may look in Google</div>
          <div class="snippet-url"><?= e(absolute_url($view)) ?></div>
          <div class="snippet-title" data-snippet-title></div>
          <div class="snippet-desc" data-snippet-desc></div>
        </div>
        <div data-part="page"><?= form_fields($seoSpec, $page, ['page']) ?></div>
      </div>
      <div class="tab-pane" id="tab-settings" role="tabpanel">
        <div data-part="page"><?= form_fields($rest, $page, ['page']) ?></div>
      </div>
    </div>
  </div>

  <aside class="editor-side">
    <div class="side-panel sticky">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <?= status_pill($stored) ?>
        <span class="unsaved" data-unsaved hidden><i class="fas fa-circle"></i> Unsaved changes</span>
      </div>
<?php if ($live): ?>
      <button class="btn btn-gold w-100 mb-2" type="submit" name="action" value="save"><i class="fas fa-save me-1"></i> Save changes</button>
<?php else: ?>
      <button class="btn btn-gold w-100 mb-2" type="submit" name="action" value="publish"><i class="fas fa-globe me-1"></i> Publish</button>
      <button class="btn btn-outline-navy w-100 mb-2" type="submit" name="action" value="save"><i class="fas fa-save me-1"></i> Save draft</button>
<?php endif; ?>
      <button class="btn btn-outline-secondary w-100 mb-3" type="submit" name="action" value="preview" formaction="<?= admin_url('preview.php') ?>" formtarget="_blank"><i class="fas fa-eye me-1"></i> Preview changes</button>

      <div class="seo-check" data-seo-check>
        <div class="seo-check-title">Quick Google check</div>
        <ul class="seo-list" data-seo-list></ul>
      </div>

      <div class="side-links">
        <a href="<?= e(admin_url('history.php', ['id' => $id])) ?>"><i class="fas fa-history"></i> Earlier versions (<?= count(history_list($id)) ?>)</a>
<?php if ($live && $stored['type'] !== 'home'): ?>
        <button type="submit" name="action" value="unpublish" class="link-btn" data-confirm="Take this page off the website? It stays here as a draft."><i class="fas fa-eye-slash"></i> Unpublish</button>
<?php endif; ?>
<?php if ($stored['type'] !== 'home'): ?>
        <a href="<?= e(admin_url('delete.php', ['id' => $id])) ?>" class="text-danger"><i class="fas fa-trash-alt"></i> Delete this page…</a>
<?php endif; ?>
      </div>
      <p class="small text-muted mt-3 mb-0">Last saved <?= e(time_ago($stored['updated'] ?? null)) ?>.</p>
    </div>
  </aside>
</form>
<?php admin_page_end();

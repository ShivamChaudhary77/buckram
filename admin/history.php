<?php
/** Earlier versions of a page, with restore. */
require __DIR__ . '/_init.php';
require_login();

$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$page = valid_id($id) ? page_load($id) : null;
if (!$page) {
    redirect_to(admin_url());
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $version = (string) ($_POST['version'] ?? '');
    $old = history_load($id, $version);
    try {
        if (!$old) {
            throw new ContentError(['That version no longer exists.']);
        }
        $old['id'] = $id;
        $old['type'] = $page['type'];
        [$saved, $messages] = page_save_from_editor($old, $page);
        flash('success', 'The version from ' . e(date('j M Y, H:i', strtotime((string) ($old['updated'] ?? 'now')))) . ' is back. The version you replaced is kept in this list. ' . implode(' ', array_map('e', $messages)));
        redirect_to(admin_url('edit.php', ['id' => $id]));
    } catch (ContentError $e) {
        flash('danger', e(implode(' ', $e->errors)));
        redirect_to(admin_url('history.php', ['id' => $id]));
    }
}

$list = history_list($id);
admin_page_start('Earlier versions: ' . ($page['title'] ?? $id), 'pages', false,
    '<a class="btn btn-sm btn-outline-secondary" href="' . e(admin_url('edit.php', ['id' => $id])) . '"><i class="fas fa-arrow-left me-1"></i>Back to the editor</a>');
?>
<p class="text-muted">Every time you save, the previous version is kept here (the last <?= HISTORY_KEEP ?>). Restoring a version replaces the current page; the current page is kept in this list too, so you can always go back.</p>
<div class="card-panel">
<?php if (!$list): ?>
  <p class="mb-0">No earlier versions yet. They appear after the first save.</p>
<?php else: ?>
  <table class="table align-middle mb-0">
    <thead><tr><th>Saved</th><th>Page name</th><th>Status</th><th></th></tr></thead>
    <tbody>
<?php foreach ($list as $v): ?>
      <tr>
        <td><?= e($v['updated'] ? date('j M Y, H:i', strtotime($v['updated'])) : $v['version']) ?> <span class="text-muted small">(<?= e(time_ago($v['updated'])) ?>)</span></td>
        <td><?= e($v['title']) ?></td>
        <td><?= $v['status'] === 'published' ? 'Live' : 'Draft' ?></td>
        <td class="text-end">
          <form method="post" action="<?= e(admin_url('history.php', ['id' => $id])) ?>" class="d-inline">
            <?= csrf_field() ?><input type="hidden" name="version" value="<?= e($v['version']) ?>">
            <button class="btn btn-sm btn-outline-navy" type="submit" data-confirm="Replace the current page with this version?"><i class="fas fa-undo me-1"></i>Restore</button>
          </form>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php admin_page_end();

<?php
/** Deleted pages: restore, delete for good, empty. */
require __DIR__ . '/_init.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $file = (string) ($_POST['file'] ?? '');
    try {
        switch ($_POST['action'] ?? '') {
            case 'restore':
                // A redirect made when the page was deleted would hide it: remove it first
                // (and put it back if the restore fails).
                $trashed = json_read(trash_file($file), []);
                $before = redirects_load();
                $list = array_values(array_filter($before, function ($r) use ($trashed) {
                    return $r['from'] !== ($trashed['path'] ?? null);
                }));
                if (count($list) !== count($before)) {
                    redirects_save($list);
                }
                try {
                    $p = trash_restore($file);
                } catch (ContentError $e) {
                    if (count($list) !== count($before)) {
                        redirects_save($before);
                    }
                    throw $e;
                }
                flash('success', '“' . e($p['title'] ?? '') . '” is back. <a href="' . e(admin_url('edit.php', ['id' => $p['id']])) . '">Open it</a>');
                break;
            case 'delete':
                trash_delete($file);
                flash('success', 'Deleted for good.');
                break;
            case 'empty':
                $n = trash_empty();
                flash('success', $n . ' page' . ($n === 1 ? '' : 's') . ' deleted for good.');
                break;
        }
    } catch (ContentError $e) {
        flash('danger', e(implode(' ', $e->errors)));
    }
    redirect_to(admin_url('trash.php'));
}

$items = trash_list();
$actions = $items ? '<form method="post" action="' . admin_url('trash.php') . '">' . csrf_field()
    . '<button class="btn btn-sm btn-outline-danger" name="action" value="empty" data-confirm="Delete all pages in the Trash for good? This cannot be undone."><i class="fas fa-dumpster me-1"></i>Empty the Trash</button></form>' : '';
admin_page_start('Trash', 'trash', false, $actions);
?>
<div class="card-panel">
<?php if (!$items): ?>
  <p class="mb-0 text-muted"><i class="fas fa-check me-2"></i>The Trash is empty.</p>
<?php else: ?>
  <table class="table align-middle mb-0">
    <thead><tr><th>Page</th><th>Address it had</th><th>Deleted</th><th></th></tr></thead>
    <tbody>
<?php foreach ($items as $t): ?>
      <tr>
        <td><?= e($t['title']) ?></td>
        <td><code><?= e($t['path']) ?></code></td>
        <td><?= e(time_ago($t['deleted'])) ?></td>
        <td class="text-end text-nowrap">
          <form method="post" action="<?= admin_url('trash.php') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="file" value="<?= e($t['file']) ?>">
            <button class="btn btn-sm btn-navy" name="action" value="restore"><i class="fas fa-undo me-1"></i>Restore</button>
            <button class="btn btn-sm btn-outline-danger" name="action" value="delete" data-confirm="Delete “<?= e($t['title']) ?>” for good? This cannot be undone.">Delete for good</button>
          </form>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php admin_page_end();

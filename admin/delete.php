<?php
/** Move a page to the Trash, optionally sending its address to another page (301). */
require __DIR__ . '/_init.php';
require_login();

$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$page = valid_id($id) ? page_load($id) : null;
if (!$page || $page['type'] === 'home') {
    redirect_to(admin_url());
}
$live = ($page['status'] ?? '') === 'published';
$suggest = $page['type'] === 'product' ? '/products.html' : config('home_path');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $target = trim((string) ($_POST['redirect_to'] ?? ''));
    try {
        page_trash($id);
        $msg = '“' . e($page['title'] ?? $id) . '” is in the Trash.';
        if ($live && !empty($_POST['make_redirect']) && $target !== '') {
            $list = redirects_load();
            foreach ($list as &$r) {
                if ($r['code'] === 301 && $r['to'] === $page['path']) {
                    $r['to'] = $target;
                }
            }
            unset($r);
            $list[] = ['from' => $page['path'], 'to' => $target, 'code' => 301];
            try {
                redirects_save($list);
                $msg .= ' Visitors to ' . e($page['path']) . ' are now sent to ' . e($target) . '.';
            } catch (ContentError $e) {
                $msg .= ' The redirect could not be added: ' . e(implode(' ', $e->errors));
            }
        }
        flash('success', $msg . ' <a href="' . admin_url('trash.php') . '">Undo</a>');
        redirect_to(admin_url());
    } catch (ContentError $e) {
        flash('danger', e(implode(' ', $e->errors)));
        redirect_to(admin_url('edit.php', ['id' => $id]));
    }
}

admin_page_start('Delete “' . ($page['title'] ?? $id) . '”?', 'pages');
?>
<div class="card-panel narrow">
  <p>The page moves to the <strong>Trash</strong>. You can restore it from there at any time.</p>
  <form method="post" action="<?= e(admin_url('delete.php', ['id' => $id])) ?>">
    <?= csrf_field() ?>
<?php if ($live): ?>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" name="make_redirect" value="1" id="mk" checked>
      <label class="form-check-label" for="mk">Send visitors of <code><?= e($page['path']) ?></code> to another page (recommended — keeps Google rankings and avoids “page not found”)</label>
    </div>
    <label class="form-label" for="rt">Send them to</label>
    <input class="form-control mb-3" id="rt" name="redirect_to" value="<?= e($suggest) ?>">
<?php endif; ?>
    <button class="btn btn-danger" type="submit"><i class="fas fa-trash-alt me-1"></i>Move to Trash</button>
    <a class="btn btn-link" href="<?= e(admin_url('edit.php', ['id' => $id])) ?>">Cancel</a>
  </form>
</div>
<?php admin_page_end();

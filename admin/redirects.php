<?php
/** Redirects: send an old address to a new one (301), or mark it as gone for good (410). */
require __DIR__ . '/_init.php';
require_login();

$errors = [];
$form = ['from' => '', 'to' => '', 'code' => 301];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $list = redirects_load();
    if (($_POST['action'] ?? '') === 'add') {
        $form = [
            'from' => normalize_page_path(clean_text($_POST['from'] ?? '')),
            'to'   => clean_text($_POST['to'] ?? ''),
            'code' => (int) ($_POST['code'] ?? 301) === 410 ? 410 : 301,
        ];
        if (preg_match('#^https?://[^/]+(/.*)?$#i', $form['from'], $m)) {
            $form['from'] = normalize_page_path($m[1] ?? '/'); // pasted a full address
        }
        $list[] = $form;
        try {
            redirects_save($list);
            flash('success', 'Redirect added: ' . e($form['from']) . ($form['code'] === 410 ? ' is marked as gone.' : ' → ' . e($form['to'])));
            redirect_to(admin_url('redirects.php'));
        } catch (ContentError $e) {
            $errors = $e->errors;
        }
    } elseif (($_POST['action'] ?? '') === 'remove') {
        $from = (string) ($_POST['from'] ?? '');
        redirects_save(array_values(array_filter($list, function ($r) use ($from) {
            return $r['from'] !== $from;
        })));
        flash('success', 'Redirect removed.');
        redirect_to(admin_url('redirects.php'));
    }
}

$list = redirects_load();
admin_page_start('Redirects', 'redirects');
echo errors_html($errors);
?>
<p class="text-muted">When a page moves or is removed, a redirect sends visitors and Google to the right place, so you keep your rankings and nobody lands on “page not found”. Pages that you rename or delete in the dashboard get their redirect automatically.</p>
<div class="card-panel mb-4">
  <h2 class="h6">Add a redirect</h2>
  <form method="post" action="<?= admin_url('redirects.php') ?>" class="row g-2 align-items-end">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="col-md-4"><label class="form-label" for="from">Old address</label><input class="form-control" id="from" name="from" value="<?= e($form['from']) ?>" placeholder="/old-page.html" required></div>
    <div class="col-md-3"><label class="form-label" for="code">What should happen</label>
      <select class="form-select" id="code" name="code" data-toggle-to>
        <option value="301"<?= $form['code'] === 301 ? ' selected' : '' ?>>Send to a new address (301)</option>
        <option value="410"<?= $form['code'] === 410 ? ' selected' : '' ?>>Gone for good (410)</option>
      </select></div>
    <div class="col-md-4" data-to-field><label class="form-label" for="to">New address</label><input class="form-control" id="to" name="to" value="<?= e($form['to']) ?>" placeholder="/new-page.html"></div>
    <div class="col-md-1"><button class="btn btn-navy w-100" type="submit">Add</button></div>
  </form>
</div>
<div class="card-panel">
<?php if (!$list): ?>
  <p class="mb-0 text-muted">No redirects yet.</p>
<?php else: ?>
  <table class="table align-middle mb-0">
    <thead><tr><th>Old address</th><th></th><th>Goes to</th><th>Added</th><th></th></tr></thead>
    <tbody>
<?php foreach ($list as $r): ?>
      <tr>
        <td><code><?= e($r['from']) ?></code></td>
        <td><i class="fas fa-arrow-right text-muted"></i></td>
        <td><?= (int) $r['code'] === 410 ? '<span class="pill pill-draft" title="HTTP 410">Gone for good</span>' : '<code>' . e($r['to']) . '</code>' ?></td>
        <td class="small text-muted"><?= e(time_ago($r['created'] ?? null)) ?></td>
        <td class="text-end"><form method="post" action="<?= admin_url('redirects.php') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="from" value="<?= e($r['from']) ?>">
          <button class="btn btn-sm btn-outline-danger" data-confirm="Remove this redirect? Visitors to <?= e($r['from']) ?> will see “page not found”."><i class="fas fa-times"></i> Remove</button></form></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php admin_page_end();

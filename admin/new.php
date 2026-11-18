<?php
/** Create a page: either a new one of a type (with starter sections) or a copy of an existing page. */
require __DIR__ . '/_init.php';
require_login();

$labels = page_type_labels();
$type = in_array($_GET['type'] ?? $_POST['type'] ?? '', ['product', 'page'], true) ? ($_GET['type'] ?? $_POST['type']) : '';
$errors = [];
$title = '';
$copyFrom = ($_POST['type'] ?? '') === '' ? (string) ($_POST['copy'] ?? '') : '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $title = clean_text($_POST['title'] ?? '');
    if ($title === '') {
        $errors[] = 'Please give the page a name.';
    }
    if (($_POST['type'] ?? '') === '' && $copyFrom === '') {
        $errors[] = 'Please choose the page to copy, or start a new empty page.';
    }
    $source = $copyFrom !== '' ? page_load($copyFrom) : null;
    if ($copyFrom !== '' && !$source) {
        $errors[] = 'The page to copy no longer exists.';
    }
    if (!$errors) {
        $type = $source['type'] ?? ($type ?: 'page');
        if (in_array($type, ['home', 'listing', 'contact', 'about'], true)) {
            $type = 'page'; // only one home/contact/… page
        }
        $slug = slugify($title) ?: 'new-page';
        $id = $slug;
        for ($n = 2; page_load($id) || page_find_by_path(($type === 'product' ? '/product/' : '/') . $id . '.html', true); $n++) {
            $id = $slug . '-' . $n;
        }
        $page = $source ?: ['blocks' => page_starter($type, $title)];
        $page = array_merge($page, [
            'id' => $id, 'type' => $type, 'status' => 'draft', 'title' => $title,
            'path' => ($type === 'product' ? '/product/' : '/') . $id . '.html',
            'seo' => ['title' => '', 'description' => ''] + ($source['seo'] ?? []),
        ]);
        unset($page['source'], $page['created'], $page['updated']);
        $page['seo']['title'] = '';
        $page['seo']['description'] = '';
        if ($type === 'product') {
            $page['breadcrumb'] = $page['breadcrumb'] ?? [['label' => 'Home', 'href' => config('home_path')], ['label' => 'Products', 'href' => '/products.html']];
            $page['card'] = ['title' => $title] + ($source['card'] ?? []);
            $page['card']['title'] = $title;
        } else {
            $page['breadcrumb'] = $page['breadcrumb'] ?? [['label' => 'Home', 'href' => config('home_path')]];
        }
        foreach ($page['blocks'] as &$b) {
            if ($b['type'] === 'page-hero') {
                $b['heading'] = $title;
                break;
            }
        }
        unset($b);
        try {
            page_save($page);
            flash('success', 'Draft “' . e($title) . '” created. It is not on the website until you publish it.');
            redirect_to(admin_url('edit.php', ['id' => $id]));
        } catch (ContentError $e) {
            $errors = $e->errors;
        }
    }
}

$pages = pages_all();
admin_page_start('Create a new page', 'pages');
echo errors_html($errors);
?>
<div class="card-panel narrow">
  <form method="post" action="<?= admin_url('new.php') ?>">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="title">Page name</label>
      <input class="form-control form-control-lg" id="title" name="title" value="<?= e($title) ?>" placeholder="e.g. Non-Woven Interlining" required autofocus>
      <div class="form-text">You can change everything later. The web address is made from the name.</div>
    </div>
    <fieldset class="mb-3">
      <legend class="form-label">Start from</legend>
<?php foreach (['product', 'page'] as $t): [$l, $ic] = $labels[$t]; ?>
      <div class="form-check"><input class="form-check-input" type="radio" name="type" id="t-<?= $t ?>" value="<?= $t ?>"<?= $type === $t && $copyFrom === '' ? ' checked' : '' ?> data-copy-off>
        <label class="form-check-label" for="t-<?= $t ?>"><i class="fas <?= e($ic) ?> me-1"></i> A new, empty <?= e(strtolower($l)) ?></label></div>
<?php endforeach; ?>
      <div class="form-check mt-2"><input class="form-check-input" type="radio" name="type" id="t-copy" value=""<?= $type === '' ? ' checked' : '' ?>>
        <label class="form-check-label" for="t-copy"><i class="fas fa-copy me-1"></i> A copy of an existing page (keeps its layout and text)</label></div>
      <select class="form-select mt-2" name="copy" id="copySelect" aria-label="Page to copy">
        <option value="">— choose the page to copy —</option>
<?php foreach ($pages as $id => $p): ?>
        <option value="<?= e($id) ?>"<?= $copyFrom === $id ? ' selected' : '' ?>><?= e($p['title'] ?? $id) ?> (<?= e($p['path'] ?? '') ?>)</option>
<?php endforeach; ?>
      </select>
    </fieldset>
    <button class="btn btn-navy" type="submit"><i class="fas fa-plus me-1"></i> Create draft</button>
    <a class="btn btn-link" href="<?= admin_url() ?>">Cancel</a>
  </form>
</div>
<?php admin_page_end();

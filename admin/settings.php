<?php
/** Site-wide settings (site.json) and the password change. */
require __DIR__ . '/_init.php';
require_login();

$site = site_load();
$errors = [];
$pwError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    if (($_POST['action'] ?? '') === 'password') {
        if (!password_check((string) ($_POST['current'] ?? ''))) {
            $pwError = 'Your current password is not right.';
        } else {
            $pwError = (string) password_problem((string) ($_POST['password'] ?? ''), (string) ($_POST['confirm'] ?? ''));
            if ($pwError === '') {
                password_set((string) $_POST['password']);
                admin_login(); // new session id
                flash('success', 'Your new password is saved.');
                redirect_to(admin_url('settings.php'));
            }
        }
    } else {
        $new = form_collect(settings_fields(), $_POST['site'] ?? [], $site, $errors);
        foreach (['ga4' => '/^(G-[A-Z0-9]{4,20})?$/', 'gtm' => '/^(GTM-[A-Z0-9]{4,12})?$/', 'metaPixel' => '/^(\d{6,20})?$/'] as $k => $re) {
            $new['tracking'][$k] = strtoupper(trim((string) ($new['tracking'][$k] ?? '')));
            if (!preg_match($re, $new['tracking'][$k])) {
                $errors[] = 'Tracking codes: the ' . ['ga4' => 'Google Analytics ID looks like G-XXXXXXXXXX', 'gtm' => 'Tag Manager ID looks like GTM-XXXXXXX', 'metaPixel' => 'Meta Pixel ID is only digits'][$k] . '.';
            }
        }
        if (!$errors) {
            site_save($new);
            flash('success', 'Settings saved. The whole website uses them right away.');
            redirect_to(admin_url('settings.php'));
        }
        $site = $new;
    }
}

admin_page_start('Settings', 'settings');
echo errors_html($errors);
?>
<form method="post" action="<?= admin_url('settings.php') ?>" class="settings-form" data-editor novalidate>
  <?= csrf_field() ?>
  <div class="card-panel">
    <div data-part="site"><?= form_fields(settings_fields(), $site, ['site']) ?></div>
  </div>
  <div class="save-bar"><span class="unsaved" data-unsaved hidden><i class="fas fa-circle"></i> Unsaved changes</span>
    <button class="btn btn-gold" type="submit"><i class="fas fa-save me-1"></i> Save settings</button></div>
</form>

<div class="card-panel mt-4" id="password">
  <h2 class="form-section-title">Change password</h2>
<?php if ($pwError): ?><div class="alert alert-danger"><?= e($pwError) ?></div><?php endif; ?>
  <form method="post" action="<?= admin_url('settings.php') ?>#password" class="row g-3" style="max-width:640px">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <div class="col-12"><label class="form-label" for="cur">Current password</label><input class="form-control" type="password" id="cur" name="current" autocomplete="current-password" required></div>
    <div class="col-md-6"><label class="form-label" for="np">New password</label><input class="form-control" type="password" id="np" name="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required>
      <div class="form-text">At least <?= PASSWORD_MIN_LENGTH ?> characters.</div></div>
    <div class="col-md-6"><label class="form-label" for="np2">Type it again</label><input class="form-control" type="password" id="np2" name="confirm" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required></div>
    <div class="col-12"><button class="btn btn-navy" type="submit">Change password</button></div>
  </form>
</div>
<?php admin_page_end();

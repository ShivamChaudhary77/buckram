<?php
require __DIR__ . '/_init.php';

if (!password_is_set()) {
    redirect_to(admin_url('setup.php'));
}
if (admin_logged_in()) {
    redirect_to(admin_url());
}

$error = '';
$locked = login_locked_for();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid($_POST['_csrf'] ?? null)) {
        $error = 'The login form had expired. Please try again.';
    } elseif ($locked > 0) {
        $error = 'Too many wrong passwords. Please wait ' . ceil($locked / 60) . ' minutes and try again.';
    } elseif (password_check((string) ($_POST['password'] ?? ''))) {
        login_clear_failures();
        admin_login();
        $next = (string) ($_POST['next'] ?? '');
        redirect_to(preg_match('#^/admin/[A-Za-z0-9_./?=&%-]*$#', $next) && strpos($next, '//') === false ? $next : admin_url());
    } else {
        login_record_failure();
        $locked = login_locked_for();
        $error = $locked > 0
            ? 'Too many wrong passwords. For safety, logging in is paused for 15 minutes.'
            : 'That password is not right. Please try again.';
    }
}

admin_page_start('Log in', '', true);
?>
    <h1 class="h4 mb-3">Log in</h1>
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>
    <form method="post" action="<?= admin_url('login.php') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($_GET['next'] ?? $_POST['next'] ?? '') ?>">
      <label class="form-label" for="password">Password</label>
      <div class="input-group mb-3">
        <input class="form-control form-control-lg" type="password" id="password" name="password" autocomplete="current-password" required autofocus>
        <button class="btn btn-outline-secondary js-show-password" type="button" aria-label="Show password"><i class="fas fa-eye"></i></button>
      </div>
      <button class="btn btn-navy btn-lg w-100" type="submit">Log in</button>
    </form>
    <p class="small text-muted mt-3 mb-0">Forgot the password? Ask your web developer to set a new one (see DASHBOARD-GUIDE.md).</p>
<?php admin_page_end(true);

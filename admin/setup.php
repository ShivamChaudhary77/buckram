<?php
/**
 * First run only: choose the dashboard password. Needs the one-time link printed by
 * "php app/cli.php setup-link" (or the go-live script). Stops working once a password exists.
 */
require __DIR__ . '/_init.php';

if (password_is_set()) {
    redirect_to(admin_url('login.php'));
}
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$valid = setup_token_valid($token);
$error = '';

if ($valid && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid($_POST['_csrf'] ?? null)) {
        $error = 'The form had expired. Please try again.';
    } else {
        $problem = password_problem((string) ($_POST['password'] ?? ''), (string) ($_POST['confirm'] ?? ''));
        if ($problem) {
            $error = $problem;
        } else {
            password_set((string) $_POST['password']);
            admin_login();
            flash('success', 'Your password is saved. Welcome to your website dashboard!');
            redirect_to(admin_url());
        }
    }
}

admin_page_start('Set up the dashboard', '', true);
if (!$valid): ?>
    <h1 class="h4">Set up the dashboard</h1>
    <p>To choose the first password you need a one-time setup link. Ask your web developer, or run this on the server:</p>
    <pre class="code-box">php app/cli.php setup-link</pre>
    <p class="small text-muted mb-0">The link works once and expires after 24 hours.</p>
<?php else: ?>
    <h1 class="h4">Choose your password</h1>
    <p class="text-muted">This password opens the dashboard. Use at least <?= PASSWORD_MIN_LENGTH ?> characters — a short sentence is easy to remember and hard to guess.</p>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
    <form method="post" action="<?= admin_url('setup.php') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label class="form-label" for="password">New password</label>
      <input class="form-control mb-3" type="password" id="password" name="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required autofocus>
      <label class="form-label" for="confirm">Type it again</label>
      <input class="form-control mb-3" type="password" id="confirm" name="confirm" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required>
      <button class="btn btn-navy w-100" type="submit">Save password and open the dashboard</button>
    </form>
<?php endif;
admin_page_end(true);

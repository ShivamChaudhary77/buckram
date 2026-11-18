<?php
/** Shows the page with the editor's unsaved changes. Never cached, never indexed, nothing saved. */
require __DIR__ . '/_init.php';
require_login();
require_post();

$stored = page_load((string) ($_POST['id'] ?? ''));
if (!$stored) {
    http_response_code(404);
    exit('Page not found.');
}
$errors = [];
$page = form_collect_page($_POST, $stored, $errors);
$page['id'] = $stored['id'];
$page['type'] = $stored['type'];

header_remove('X-Frame-Options');
header('X-Frame-Options: SAMEORIGIN');
$html = render_page($page, ['preview' => true]);
$banner = '<div style="position:fixed;z-index:99999;left:50%;bottom:16px;transform:translateX(-50%);background:#0F2747;color:#fff;padding:10px 18px;border-radius:30px;font:600 14px/1.3 Inter,sans-serif;box-shadow:0 6px 24px rgba(0,0,0,.25)">Preview — not saved yet'
    . ($errors ? ' · ' . count($errors) . ' problem(s) to fix before saving' : '') . '</div>';
echo str_replace('</body>', $banner . "\n</body>", $html);

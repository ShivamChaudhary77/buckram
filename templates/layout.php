<?php
/** @var array $site @var array $page @var array $head @var string $body @var array $opts */
$scripts = $site['scripts'] ?? [
    '/assets/js/jquery.min.js',
    '/assets/js/bootstrap.bundle.min.js',
    '/assets/js/aos.js',
    '/assets/js/swiper-bundle.min.js',
    '/assets/js/main.js',
];
?><!DOCTYPE html>
<html lang="<?= e($head['lang']) ?>">
<head>
<?= render_template('partials/head', ['head' => $head, 'site' => $site]) ?>
</head>
<body class="<?= e($page['bodyClass'] ?? 'header-fixed-offset') ?>">

<header class="site-header">
<?= render_template('partials/topbar', ['site' => $site]) ?>
<?= render_template('partials/navbar', ['site' => $site, 'page' => $page]) ?>
</header>

<main>

<?= $body ?>
</main>

<?= render_template('partials/footer', ['site' => $site]) ?>

<?php foreach ($scripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>

<?php
/** @var array $head @var array $site */
$styles = $site['styles'] ?? [
    '/assets/css/bootstrap.min.css',
    '/assets/css/aos.css',
    '/assets/css/swiper-bundle.min.css',
    ['href' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', 'referrerpolicy' => 'no-referrer'],
    '/assets/css/style.css',
    '/assets/css/animation.css',
    '/assets/css/responsive.css',
];
$fonts = $site['fontsUrl'] ?? 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($head['title']) ?></title>
<?php if ($head['description'] !== ''): ?>
<meta name="description" content="<?= e($head['description']) ?>">
<?php endif; ?>
<?php if ($head['robots']): ?>
<meta name="robots" content="<?= e($head['robots']) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= e($head['canonical']) ?>">
<meta property="og:title" content="<?= e($head['ogTitle']) ?>">
<?php if ($head['ogDescription'] !== ''): ?>
<meta property="og:description" content="<?= e($head['ogDescription']) ?>">
<?php endif; ?>
<meta property="og:type" content="<?= e($head['ogType']) ?>">
<meta property="og:url" content="<?= e($head['canonical']) ?>">
<?php if ($head['siteName'] !== ''): ?>
<meta property="og:site_name" content="<?= e($head['siteName']) ?>">
<?php endif; ?>
<?php if ($head['ogImage'] !== ''): ?>
<meta property="og:image" content="<?= e($head['ogImage']) ?>">
<?php if ($head['ogImageWidth']): ?>
<meta property="og:image:width" content="<?= (int) $head['ogImageWidth'] ?>">
<meta property="og:image:height" content="<?= (int) $head['ogImageHeight'] ?>">
<?php endif; ?>
<?php endif; ?>
<meta name="twitter:card" content="<?= e($head['twitterCard']) ?>">
<meta name="twitter:title" content="<?= e($head['ogTitle']) ?>">
<?php if ($head['ogDescription'] !== ''): ?>
<meta name="twitter:description" content="<?= e($head['ogDescription']) ?>">
<?php endif; ?>
<?php if ($head['ogImage'] !== ''): ?>
<meta name="twitter:image" content="<?= e($head['ogImage']) ?>">
<?php endif; ?>
<?php if ($head['favicon'] !== ''): ?>
<link rel="icon" href="<?= e($head['favicon']) ?>"<?= preg_match('/\.svg$/i', $head['favicon']) ? ' type="image/svg+xml"' : '' ?>>
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= e($fonts) ?>" rel="stylesheet">

<?php foreach ($styles as $css): $css = is_array($css) ? $css : ['href' => $css]; ?>
<link rel="stylesheet" href="<?= e($css['href']) ?>"<?= !empty($css['referrerpolicy']) ? ' referrerpolicy="' . e($css['referrerpolicy']) . '"' : '' ?>>
<?php endforeach; ?>
<?php foreach ($head['jsonld'] as $data): ?>

<script type="application/ld+json">
<?= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>

</script>
<?php endforeach; ?>
<?= render_template('partials/tracking-head', ['t' => $head['tracking']]) ?>

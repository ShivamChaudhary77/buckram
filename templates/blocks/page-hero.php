<?php
/**
 * Top banner of inner pages, with breadcrumb.
 * @var array $b  icon, eyebrow, heading (the page H1), intro (html), image, breadcrumb (bool, default true)
 * @var array $page
 */
$style = ($b['image'] ?? '') !== '' ? " style=\"background-image:url('" . e($b['image']) . "');\"" : '';
$crumbs = ($b['breadcrumb'] ?? true) ? breadcrumb_items($page) : [];
?>
  <section class="page-hero"<?= $style ?>>
    <div class="container-custom">
<?php if (($b['eyebrow'] ?? '') !== ''): ?>
      <span class="hero-eyebrow"><?= icon($b['icon'] ?? '') ?><?= e($b['eyebrow']) ?></span>
<?php endif; ?>
      <h1><?= e($b['heading'] ?? ($page['title'] ?? '')) ?></h1>
<?php if (($b['intro'] ?? '') !== ''): ?>
      <p><?= $b['intro'] ?></p>
<?php endif; ?>
<?php if (count($crumbs) > 1): ?>
      <div class="breadcrumb-bar">
        <ul class="breadcrumb-custom">
<?php foreach ($crumbs as $i => $c): ?>
<?php if ($i > 0): ?>
          <li class="sep">/</li>
<?php endif; ?>
<?php if ($c['href'] !== ''): ?>
          <li><a href="<?= e($c['href']) ?>"><?= e($c['label']) ?></a></li>
<?php else: ?>
          <li class="active"><?= e($c['label']) ?></li>
<?php endif; ?>
<?php endforeach; ?>
        </ul>
      </div>
<?php endif; ?>
    </div>
  </section>


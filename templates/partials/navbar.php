<?php
/** @var array $site @var array $page */
$logo = $site['logo'] ?? [];
$home = config('home_path');
$cta = $site['navCta'] ?? null;
?>
<nav class="navbar navbar-expand-lg navbar-main">
  <div class="container-custom">
    <a class="navbar-brand" href="<?= e($home) ?>">
      <img src="<?= e($logo['src'] ?? '') ?>" alt="<?= e($logo['alt'] ?? '') ?>">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="mainNav">
      <ul class="navbar-nav align-items-lg-center">
<?php foreach (($site['nav'] ?? []) as $n => $item):
    $page_attr = !empty($item['page']) ? ' data-page="' . e($item['page']) . '"' : '';
    if (!empty($item['children'])):
        $ddId = 'navDropdown' . $n;
        if (!empty($item['dropdownId'])) {
            $ddId = (string) $item['dropdownId'];
        } ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle"<?= $page_attr ?> href="<?= e($item['href'] ?? '#') ?>" id="<?= e($ddId) ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false"><?= e($item['label'] ?? '') ?></a>
          <ul class="dropdown-menu" aria-labelledby="<?= e($ddId) ?>">
<?php foreach ($item['children'] as $child): ?>
<?php if (!empty($child['divider'])): ?>
            <li><hr class="dropdown-divider"></li>
<?php else: ?>
            <li><a class="dropdown-item" href="<?= e($child['href'] ?? '') ?>"><?= !empty($child['icon']) ? icon($child['icon']) : '' ?><?= e($child['label'] ?? '') ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
          </ul>
        </li>
<?php else: ?>
        <li class="nav-item"><a class="nav-link"<?= $page_attr ?> href="<?= e($item['href'] ?? '') ?>"><?= e($item['label'] ?? '') ?></a></li>
<?php endif; endforeach; ?>
<?php if ($cta && !empty($cta['label'])): ?>
        <li class="nav-item">
          <a href="<?= e($cta['href'] ?? '') ?>" class="<?= e($cta['class'] ?? 'btn btn-gold btn-sm-custom btn-enquire') ?>"><?= e($cta['label']) ?></a>
        </li>
<?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

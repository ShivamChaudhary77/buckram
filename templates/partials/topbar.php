<?php
/** @var array $site */
$phone = $site['phones'][0] ?? null;
$email = (string) ($site['email'] ?? '');
?>
<div class="topbar">
  <div class="container-custom d-flex flex-wrap align-items-center justify-content-between">
    <div class="d-flex flex-wrap align-items-center gap-3">
<?php if ($phone): ?>
      <a href="tel:<?= e($phone['tel'] ?? '') ?>"><i class="fas fa-phone-alt me-2"></i><?= e($phone['display'] ?? '') ?></a>
<?php endif; ?>
<?php if ($email !== ''): ?>
      <a href="mailto:<?= e($email) ?>" class="d-none d-md-inline"><i class="fas fa-envelope me-2"></i><?= e($email) ?></a>
<?php endif; ?>
<?php if (!empty($site['topbarLocation'])): ?>
      <a href="<?= e($site['mapUrl'] ?? '') ?>" target="_blank" rel="noopener" class="d-none d-lg-inline"><i class="fas fa-map-marker-alt me-2"></i><?= e($site['topbarLocation']) ?></a>
<?php endif; ?>
    </div>
    <div class="d-flex align-items-center">
<?php if (!empty($site['hours'])): ?>
      <span class="d-none d-md-inline me-3"><i class="fas fa-clock me-2"></i><?= e($site['hours']) ?></span>
<?php endif; ?>
      <div class="topbar-social">
<?php foreach (site_social($site) as $s): ?>
        <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['label'] ?? '') ?>"><?= icon($s['icon'] ?? '') ?></a>
<?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

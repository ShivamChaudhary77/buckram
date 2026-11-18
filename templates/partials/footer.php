<?php
/** @var array $site */
$footer = $site['footer'] ?? [];
$news = $site['newsletter'] ?? [];
$logo = $site['logo'] ?? [];
$wa = whatsapp_url($site, true);
?>
<?php if (!empty($news['enabled'])): ?>
<div class="newsletter-bar">
  <div class="container-custom">
    <div class="row align-items-center g-3">
      <div class="col-lg-6">
        <h3 class="mb-0"><?= e($news['heading'] ?? '') ?></h3>
        <p class="mb-0 text-muted-custom"><?= e($news['text'] ?? '') ?></p>
      </div>
      <div class="col-lg-6">
        <form id="newsletterForm" class="d-flex gap-2">
          <input type="email" class="form-control" placeholder="<?= e($news['placeholder'] ?? '') ?>" required>
          <button type="submit" class="btn btn-navy text-nowrap"><?= e($news['button'] ?? 'Subscribe') ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<footer class="site-footer">
  <div class="container-custom">
    <div class="row g-5">
      <div class="col-lg-4 col-md-6">
        <div class="footer-about">
          <span class="footer-logo-badge">
            <img src="<?= e($footer['logo'] ?? ($logo['src'] ?? '')) ?>" alt="<?= e($footer['logoAlt'] ?? '') ?>">
          </span>
          <p><?= e($footer['about'] ?? '') ?></p>
          <div class="footer-social">
<?php foreach (site_social($site) as $s): ?>
            <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['label'] ?? '') ?>"><?= icon($s['icon'] ?? '') ?></a>
<?php endforeach; ?>
          </div>
        </div>
      </div>
<?php foreach (($footer['columns'] ?? []) as $col): ?>

      <div class="<?= e($col['class'] ?? 'col-lg-2 col-md-6') ?>">
        <h5><?= e($col['title'] ?? '') ?></h5>
        <ul>
<?php foreach (($col['links'] ?? []) as $link): ?>
          <li><a href="<?= e($link['href'] ?? '') ?>"><?= e($link['label'] ?? '') ?></a></li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endforeach; ?>

      <div class="col-lg-3 col-md-6">
        <h5><?= e($footer['contactTitle'] ?? 'Get In Touch') ?></h5>
        <ul class="footer-contact">
<?php if (!empty($footer['contactPerson'])): ?>
          <li><i class="fas fa-user"></i><span><?= e($footer['contactPerson']) ?></span></li>
<?php endif; ?>
<?php if (!empty($site['phones'])): ?>
          <li><i class="fas fa-phone-alt"></i><span><?= implode('<br>', array_map(function ($p) {
              return '<a href="tel:' . e($p['tel'] ?? '') . '">' . e($p['display'] ?? '') . '</a>';
          }, $site['phones'])) ?></span></li>
<?php endif; ?>
<?php if (!empty($site['email'])): ?>
          <li><i class="fas fa-envelope"></i><span><a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a></span></li>
<?php endif; ?>
<?php if (!empty($site['addresses'])): ?>
          <li><i class="fas fa-map-marker-alt"></i><span><?= implode('<br>', array_map(function ($a) use ($site) {
              $text = trim(($a['label'] ?? '') !== '' ? $a['label'] . ': ' . ($a['text'] ?? '') : (string) ($a['text'] ?? ''));
              $map = (string) (($a['mapUrl'] ?? '') ?: ($site['mapUrl'] ?? ''));
              return $map !== '' ? '<a href="' . e($map) . '" target="_blank" rel="noopener">' . e($text) . '</a>' : e($text);
          }, $site['addresses'])) ?></span></li>
<?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="footer-bottom d-flex flex-wrap justify-content-between align-items-center">
      <p class="mb-0">&copy; <span data-current-year><?= date('Y') ?></span> <?= e($footer['copyright'] ?? '') ?></p>
      <p class="mb-0 text-md-end"><?= e($footer['tagline'] ?? '') ?></p>
    </div>
  </div>
</footer>
<?php if ($wa !== ''): ?>

<a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="whatsapp-float" aria-label="Chat on WhatsApp">
  <i class="fab fa-whatsapp"></i>
</a>
<?php endif; ?>

<button type="button" class="back-to-top" aria-label="Back to top">
  <i class="fas fa-arrow-up"></i>
</button>

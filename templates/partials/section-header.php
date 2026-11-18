<?php
/**
 * Centred section heading used above card grids, tables and product lists.
 * @var array $h  eyebrow, heading, subtitle (subtitle may contain simple HTML), dark (bool)
 */
$dark = !empty($h['dark']);
if (($h['eyebrow'] ?? '') === '' && ($h['heading'] ?? '') === '' && ($h['subtitle'] ?? '') === '') {
    return;
}
?>
      <div class="section-header text-center mx-auto" data-aos="fade-up">
<?php if (($h['eyebrow'] ?? '') !== ''): ?>
        <span class="section-eyebrow justify-content-center"><?= e($h['eyebrow']) ?></span>
<?php endif; ?>
<?php if (($h['heading'] ?? '') !== ''): ?>
        <h2 class="section-title<?= $dark ? ' text-white' : '' ?>"><?= e($h['heading']) ?></h2>
<?php endif; ?>
<?php if (($h['subtitle'] ?? '') !== ''): ?>
        <p class="section-subtitle mx-auto<?= $dark ? ' text-white-50' : '' ?>"><?= $h['subtitle'] ?></p>
<?php endif; ?>
      </div>

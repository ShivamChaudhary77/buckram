<?php
/**
 * Centred section heading used above card grids, tables and product lists.
 * @var array $h  eyebrow, heading, subtitle (subtitle may contain simple HTML)
 */
if (($h['eyebrow'] ?? '') === '' && ($h['heading'] ?? '') === '' && ($h['subtitle'] ?? '') === '') {
    return;
}
?>
      <div class="section-header text-center mx-auto" data-aos="fade-up">
<?php if (($h['eyebrow'] ?? '') !== ''): ?>
        <span class="section-eyebrow justify-content-center"><?= e($h['eyebrow']) ?></span>
<?php endif; ?>
<?php if (($h['heading'] ?? '') !== ''): ?>
        <h2 class="section-title"><?= e($h['heading']) ?></h2>
<?php endif; ?>
<?php if (($h['subtitle'] ?? '') !== ''): ?>
        <p class="section-subtitle mx-auto"><?= $h['subtitle'] ?></p>
<?php endif; ?>
      </div>

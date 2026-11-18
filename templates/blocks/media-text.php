<?php
/**
 * Image (or video) beside text.
 * @var array $b
 *   bg, textFirst (bool: text column comes first in the code and the image is
 *   moved left with order classes), image, imageAlt, video,
 *   eyebrow, heading, html (paragraphs), miniFeatures [{icon,title,text}],
 *   checklist [text…], afterHtml, button {label,href,class,iconAfter}
 */
$textFirst = !empty($b['textFirst']);
$mediaCol = 'col-lg-6' . ($textFirst ? ' order-lg-1' : '');
$textCol = 'col-lg-6' . ($textFirst ? ' order-lg-2' : '');
$mediaAos = (string) ($b['mediaAos'] ?? 'fade-right');
$textAos = (string) ($b['textAos'] ?? 'fade-left');

ob_start(); ?>
        <div class="<?= $mediaCol ?>" data-aos="<?= $mediaAos ?>">
          <div class="img-frame">
<?php if (($b['video'] ?? '') !== ''): ?>
            <video class="w-100 d-block" controls playsinline preload="metadata">
              <source src="<?= e($b['video']) ?>" type="video/mp4">
              Your browser does not support the video tag.
            </video>
<?php else: ?>
            <img src="<?= e($b['image'] ?? '') ?>" alt="<?= e($b['imageAlt'] ?? '') ?>" loading="lazy" class="w-100">
<?php endif; ?>
          </div>
        </div>
<?php $media = ob_get_clean();

ob_start(); ?>
        <div class="<?= $textCol ?>" data-aos="<?= $textAos ?>">
<?php if (($b['eyebrow'] ?? '') !== ''): ?>
          <span class="section-eyebrow"><?= e($b['eyebrow']) ?></span>
<?php endif; ?>
<?php if (($b['heading'] ?? '') !== ''): ?>
          <h2 class="section-title"><?= e($b['heading']) ?></h2>
<?php endif; ?>
<?php if (trim((string) ($b['html'] ?? '')) !== ''): ?>
          <?= trim((string) $b['html']) ?>

<?php endif; ?>
<?php if (!empty($b['miniFeatures'])): ?>
          <div class="row g-3 mb-4">
<?php foreach ($b['miniFeatures'] as $f): ?>
            <div class="col-sm-6">
              <div class="feature-mini">
                <div class="feature-mini-icon"><?= icon($f['icon'] ?? '') ?></div>
                <div>
                  <h4><?= e($f['title'] ?? '') ?></h4>
                  <p><?= e($f['text'] ?? '') ?></p>
                </div>
              </div>
            </div>
<?php endforeach; ?>
          </div>
<?php endif; ?>
<?php if (!empty($b['checklist'])): ?>
          <ul class="check-list mb-4">
<?php foreach ($b['checklist'] as $item): ?>
            <li><i class="fas fa-check-circle"></i><?= $item ?></li>
<?php endforeach; ?>
          </ul>
<?php endif; ?>
<?php if (trim((string) ($b['afterHtml'] ?? '')) !== ''): ?>
          <?= trim((string) $b['afterHtml']) ?>

<?php endif; ?>
<?php if (!empty($b['button'])): ?>
          <?= button_html($b['button'] + ['class' => 'btn btn-navy', 'iconAfter' => 'fas fa-arrow-right ms-2']) ?>

<?php endif; ?>
        </div>
<?php $text = ob_get_clean(); ?>
  <section<?= section_attr($b) ?>>
    <div class="container-custom">
      <div class="row align-items-center g-5">
<?= $textFirst ? $text . $media : $media . $text ?>
      </div>
    </div>
  </section>


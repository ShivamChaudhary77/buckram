<?php
/**
 * Questions and answers (accordion). The same items feed the FAQPage schema (Phase 5).
 * @var array $b
 *   bg, eyebrow, heading, intro, button {label, href},
 *   accordionId, itemPrefix, items [{q, a (html)}]
 */
$acc = preg_replace('/[^A-Za-z0-9_-]/', '', (string) (($b['accordionId'] ?? '') ?: 'faq'));
$prefix = preg_replace('/[^A-Za-z0-9_-]/', '', (string) (($b['itemPrefix'] ?? '') ?: $acc . '-'));
?>
  <section<?= section_attr($b) ?>>
    <div class="container-custom">
      <div class="row g-5">
        <div class="col-lg-4" data-aos="fade-right">
<?php if (($b['eyebrow'] ?? '') !== ''): ?>
          <span class="section-eyebrow"><?= e($b['eyebrow']) ?></span>
<?php endif; ?>
          <h2 class="section-title"><?= e(($b['heading'] ?? '') ?: 'Frequently Asked Questions') ?></h2>
<?php if (($b['intro'] ?? '') !== ''): ?>
          <p class="section-subtitle"><?= $b['intro'] ?></p>
<?php endif; ?>
<?php if (!empty($b['button']['label'])): ?>
          <?= button_html($b['button'] + ['class' => 'btn btn-navy mt-2']) ?>

<?php endif; ?>
        </div>
        <div class="col-lg-8" data-aos="fade-left">
          <div class="accordion faq-accordion" id="<?= e($acc) ?>">
<?php foreach (array_values($b['items'] ?? []) as $i => $item): $id = $prefix . ($i + 1); $first = $i === 0; ?>
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button<?= $first ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($id) ?>"><?= e($item['q'] ?? '') ?></button>
              </h3>
              <div id="<?= e($id) ?>" class="accordion-collapse collapse<?= $first ? ' show' : '' ?>" data-bs-parent="#<?= e($acc) ?>">
                <div class="accordion-body"><?= $item['a'] ?? '' ?></div>
              </div>
            </div>
<?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>


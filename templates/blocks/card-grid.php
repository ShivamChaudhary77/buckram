<?php
/**
 * A grid of cards (applications, benefits, values, variants…).
 * @var array $b
 *   bg, eyebrow, heading, subtitle,
 *   card: "value" (icon card) | "variant" (optional photo card)
 *   centered (bool), col (column class, default "col-lg-4 col-md-6"), titleTag (h3|h4),
 *   items [{icon, image, imageAlt, title, text}], link {label, href},
 *   center (bool: centre an incomplete last row), dark (white heading on a dark band),
 *   delayMode ("row" | "linear" | "custom" + delays [ms…])
 */
$col = (string) (($b['col'] ?? '') ?: 'col-lg-4 col-md-6');
$perRow = cards_per_row($col);
$tag = in_array($b['titleTag'] ?? 'h3', ['h3', 'h4'], true) ? ($b['titleTag'] ?? 'h3') : 'h3';
$variant = ($b['card'] ?? 'value') === 'variant';
$centered = !empty($b['centered']);
?>
  <section<?= section_attr($b) ?>>
    <div class="container-custom">
<?= render_template('partials/section-header', ['h' => $b]) ?>
      <div class="row g-4<?= !empty($b['center']) ? ' justify-content-center' : '' ?>">
<?php foreach (array_values($b['items'] ?? []) as $i => $item): ?>
        <div class="<?= e($col) ?>" data-aos="fade-up"<?= block_delay($b, $i, $perRow) ?>>
<?php if ($variant): ?>
          <div class="variant-card">
<?php if (($item['image'] ?? '') !== ''): ?>
            <img src="<?= e($item['image']) ?>" alt="<?= e($item['imageAlt'] ?? '') ?>" loading="lazy" class="variant-swatch">
<?php elseif (($item['icon'] ?? '') !== ''): ?>
            <?= icon($item['icon']) ?>

<?php endif; ?>
<?php else: ?>
          <div class="value-card<?= $centered ? ' text-center' : '' ?>">
            <div class="value-icon<?= $centered ? ' mx-auto' : '' ?>"><?= icon($item['icon'] ?? '') ?></div>
<?php endif; ?>
            <<?= $tag ?>><?= e($item['title'] ?? '') ?></<?= $tag ?>>
            <p><?= $item['text'] ?? '' ?></p>
          </div>
        </div>
<?php endforeach; ?>
      </div>
<?php if (!empty($b['link']['label'])): ?>
      <div class="text-center mt-5" data-aos="fade-up">
        <?= button_html($b['link'] + ['class' => 'btn btn-outline-navy', 'iconAfter' => 'fas fa-arrow-right ms-2']) ?>

      </div>
<?php endif; ?>
    </div>
  </section>


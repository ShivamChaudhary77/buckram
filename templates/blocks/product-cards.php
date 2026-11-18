<?php
/**
 * Product cards built from each product page's "card" fields (one source of truth).
 * @var array $b
 *   bg, eyebrow, heading, subtitle, col (default "col-lg-4 col-md-6"), center (bool),
 *   items [{page: "<product id>", tag?, summary?, image?, imageAlt?, title?}],
 *   link {label, href}
 */
$col = (string) (($b['col'] ?? '') ?: 'col-lg-4 col-md-6');
$perRow = cards_per_row($col);
$cards = [];
foreach (($b['items'] ?? []) as $ref) {
    $card = is_array($ref) ? product_card($ref) : null;
    if ($card) {
        $cards[] = $card;
    }
}
?>
  <section<?= section_attr($b) ?>>
    <div class="container-custom">
<?= render_template('partials/section-header', ['h' => $b]) ?>
      <div class="row g-4<?= !empty($b['center']) ? ' justify-content-center' : '' ?>">
<?php foreach ($cards as $i => $c): ?>
        <div class="<?= e($col) ?>" data-aos="fade-up"<?= aos_delay($i, $perRow) ?>>
          <div class="product-card">
            <div class="product-img"><img src="<?= e($c['image']) ?>" alt="<?= e($c['imageAlt']) ?>" loading="lazy"></div>
            <div class="product-body">
<?php if ($c['tag'] !== ''): ?>
              <span class="product-tag"><?= e($c['tag']) ?></span>
<?php endif; ?>
              <h3><?= e($c['title']) ?></h3>
              <p><?= e($c['summary']) ?></p>
              <a href="<?= e($c['href']) ?>" class="product-link">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
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


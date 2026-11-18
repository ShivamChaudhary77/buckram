<?php
/**
 * Table: product specifications (2 columns) or a comparison table (any number of columns).
 * @var array $b  bg, eyebrow, heading, subtitle, style ("spec-table" | "compare-table"),
 *                columns [heading…], rows [[cell (simple HTML)…], …], note (simple HTML under the table)
 */
$cols = $b['columns'] ?? ['Parameter', 'Typical Specification'];
$tableClass = in_array($b['style'] ?? '', ['spec-table', 'compare-table'], true) ? $b['style'] : 'spec-table';
?>
  <section<?= section_attr($b) ?>>
    <div class="container-custom">
<?= render_template('partials/section-header', ['h' => $b]) ?>
      <div class="table-responsive-custom" data-aos="fade-up">
        <table class="<?= $tableClass ?>">
          <thead>
            <tr>
<?php foreach ($cols as $c): ?>
              <th><?= e($c) ?></th>
<?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
<?php foreach (($b['rows'] ?? []) as $row): ?>
            <tr>
<?php foreach ((array) $row as $cell): ?>
              <td><?= $cell ?></td>
<?php endforeach; ?>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php if (trim((string) ($b['note'] ?? '')) !== ''): ?>
      <p class="text-center text-muted mt-4" data-aos="fade-up"><?= $b['note'] ?></p>
<?php endif; ?>
    </div>
  </section>


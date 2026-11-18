<?php
/**
 * Two-column specification table.
 * @var array $b  bg, eyebrow, heading, subtitle, columns [left, right], rows [[left, right], …]
 */
$cols = $b['columns'] ?? ['Parameter', 'Typical Specification'];
?>
  <section<?= section_attr($b) ?>>
    <div class="container-custom">
<?= render_template('partials/section-header', ['h' => $b]) ?>
      <div class="table-responsive-custom" data-aos="fade-up">
        <table class="spec-table">
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
    </div>
  </section>


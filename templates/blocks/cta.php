<?php
/**
 * Closing call-to-action banner.
 * @var array $b  heading, text, buttons [{label, href, class, iconBefore, newTab}]
 *   When buttons is empty: "Request Bulk Quote" + "Chat on WhatsApp" from site settings.
 * @var array $site
 */
$buttons = $b['buttons'] ?? [];
if (!$buttons) {
    $buttons = [['label' => 'Request Bulk Quote', 'href' => '/contact.html', 'class' => 'btn btn-gold btn-lg-custom']];
    $wa = whatsapp_url($site);
    if ($wa !== '') {
        $buttons[] = ['label' => 'Chat on WhatsApp', 'href' => $wa, 'newTab' => true, 'class' => 'btn btn-outline-light-custom btn-lg-custom', 'iconBefore' => 'fab fa-whatsapp me-2'];
    }
}
?>
  <section class="section-tight">
    <div class="container-custom">
      <div class="cta-section" data-aos="zoom-in">
        <div class="cta-wrap text-center">
          <h2 class="section-title"><?= e($b['heading'] ?? '') ?></h2>
<?php if (($b['text'] ?? '') !== ''): ?>
          <p class="mb-4 fs-5"><?= $b['text'] ?></p>
<?php endif; ?>
          <div class="d-flex gap-3 justify-content-center flex-wrap">
<?php foreach ($buttons as $btn): ?>
            <?= button_html($btn) ?>

<?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>


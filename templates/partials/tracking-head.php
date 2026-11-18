<?php
/**
 * Visitor statistics tags from Settings. Empty IDs print nothing. Never on previews
 * (and the dashboard has its own layout without them). IDs are format-checked on save.
 * @var array $t  ga4, gtm, metaPixel
 */
$gtm = preg_match('/^GTM-[A-Z0-9]{4,12}$/', (string) ($t['gtm'] ?? '')) ? $t['gtm'] : '';
$ga4 = preg_match('/^G-[A-Z0-9]{4,20}$/', (string) ($t['ga4'] ?? '')) ? $t['ga4'] : '';
$px = preg_match('/^\d{6,20}$/', (string) ($t['metaPixel'] ?? '')) ? $t['metaPixel'] : '';
if (!$gtm && !$ga4 && !$px) {
    return;
}
?>

<?php if ($gtm): ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= $gtm ?>');</script>
<?php endif; ?>
<?php if ($ga4): ?>
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $ga4 ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= $ga4 ?>');</script>
<?php endif; ?>
<?php if ($px): ?>
<!-- Meta Pixel -->
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= $px ?>');fbq('track','PageView');</script>
<?php endif; ?>

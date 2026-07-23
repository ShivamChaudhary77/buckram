# Jaibaba Interlinings — Page Build Spec (internal reference for page authors)

Read `/Users/shivamkumar/Desktop/jaibaba/index.html` FIRST as the canonical reference for exact boilerplate, section patterns, and tone. Copy its structure precisely — do not invent new CSS classes or new patterns not listed below.

## Non-negotiable rules

1. **Root-relative paths only.** Every href/src starts with `/` — e.g. `/about.html`, `/assets/css/style.css`, `/product/buckram-fabric.html`. Never use relative `../` paths. This is critical for pages inside `/product/`.
2. **Images: ONLY use the exact URLs given to you in your task prompt.** Do NOT invent, guess, or recall Unsplash photo IDs from memory — every unlisted ID risks a 404. If you need an image not provided, reuse the closest one already given to you rather than inventing a new URL. Always append `?w=WIDTH&q=80&auto=format&fit=crop` (WIDTH: 1920 for hero backgrounds, 1000 for large content images, 600 for cards, 400 for small thumbnails).
3. **No React/Vue/build tools.** Plain HTML5 + Bootstrap 5 classes + the custom classes below. No inline `<script>` logic beyond what's already in main.js — if you need new JS behavior, note it, don't invent inline handlers.
4. **Content voice:** senior industrial B2B copywriter. Concrete, specific, confident, no fluff, no "unlock synergies" AI-speak, no fabricated statistics (no invented client counts, country counts, tonnage — only use 1977 founding / 48+ years / 7 product categories / Delhi location, which are verified facts). Short paragraphs. Audience: garment manufacturers, buying houses, OEM buyers, procurement managers, export buyers.
5. **Every page needs:** full SEO head block (title, meta description, canonical, OG tags, favicon, font links, all 6 CSS links in exact order, Font Awesome CDN), preloader div, `<header>` wrapper with the two data-include divs, breadcrumb inside the page-hero, main content sections, footer data-include div, and the 5 script tags at the bottom — all copied exactly from index.html.

## Exact head block (copy verbatim, only change title/description/canonical/og tags)

```html
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PAGE TITLE HERE</title>
<meta name="description" content="PAGE META DESCRIPTION HERE">
<link rel="canonical" href="https://www.jaibabainterlinings.com/PAGE_URL_HERE">
<meta property="og:title" content="OG TITLE">
<meta property="og:description" content="OG DESCRIPTION">
<meta property="og:type" content="website">
<meta property="og:image" content="/assets/images/logo.svg">
<link rel="icon" href="/assets/images/logo.svg" type="image/svg+xml">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/aos.css">
<link rel="stylesheet" href="/assets/css/swiper-bundle.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/animation.css">
<link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body class="header-fixed-offset">

<div class="preloader"><div class="loader-ring"></div></div>

<header class="site-header">
  <div data-include="/includes/header.html"></div>
  <div data-include="/includes/navbar.html"></div>
</header>

<main>
  ... page content ...
</main>

<div data-include="/includes/footer.html"></div>

<script src="/assets/js/jquery.min.js"></script>
<script src="/assets/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/aos.js"></script>
<script src="/assets/js/swiper-bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
</body>
</html>
```

## Interior page hero pattern (use for every page except index.html)

```html
<section class="page-hero" style="background-image:url('IMAGE_URL?w=1920&q=80&auto=format&fit=crop');">
  <div class="container-custom">
    <span class="hero-eyebrow"><i class="fas fa-ICON"></i>EYEBROW TEXT</span>
    <h1>PAGE H1</h1>
    <p>One or two sentence page intro.</p>
    <div class="breadcrumb-bar">
      <ul class="breadcrumb-custom">
        <li><a href="/index.html">Home</a></li>
        <li class="sep">/</li>
        <li class="active">CURRENT PAGE NAME</li>
      </ul>
    </div>
  </div>
</section>
```

For deeper pages (e.g. product detail pages under `/product/`), add an extra breadcrumb level: Home / Products / Current Product.

## Available CSS component classes (defined in style.css / animation.css — do not redefine, just use)

- **Layout:** `.container-custom`, `section` (auto-padded), `.section-tight`, `.bg-soft`, `.bg-soft-alt`, `.bg-navy`
- **Section heading:** `.section-header` (wrap), `.section-eyebrow`, `.section-title`, `.section-subtitle`, `.divider-gold`
- **Buttons:** `.btn.btn-gold`, `.btn.btn-navy`, `.btn.btn-outline-light-custom` (on dark bg only), `.btn.btn-outline-navy`, modifiers `.btn-sm-custom` / `.btn-lg-custom`
- **Cards:** `.card-custom`, `.product-card` (`.product-img` > img, `.product-body`, `.product-tag`, `.product-link`), `.value-card` (`.value-icon` + icon), `.step-card` (`.step-num`, `.step-icon`), `.testimonial-card`, `.contact-info-card`, `.variant-card`
- **Stats:** `.stat-card`, `.counter[data-count="NUMBER"]`, `.stat-suffix`, `.stat-caption` — only use verified numbers: 1977, 48, 7, 100 (%)
- **Lists:** `.check-list` (li with `<i class="fas fa-check-circle">`), `.pill-list` > `.pill` (with icon)
- **Tables:** `.spec-table` (wrap in `.table-responsive-custom`), `.compare-table` (use `<i class="fas fa-check-circle">` / `<i class="fas fa-times-circle">` for yes/no cells)
- **FAQ:** `.accordion.faq-accordion` — copy the exact accordion markup pattern from index.html FAQ section, with unique IDs per page (e.g. `#aboutFaq`, `#mfgFaq1`, `#mfgFaq2`...)
- **Timeline:** `.timeline` > `.timeline-item` (`.timeline-year`, h3, p) — for About page journey/timeline
- **Gallery:** `.gallery-item` (img + optional `.gallery-caption`), or `.swiper.gallery-swiper` with `.swiper-wrapper > .swiper-slide` + `.swiper-pagination` for carousels
- **CTA:** `.cta-section` > `.cta-wrap` — copy exact pattern from index.html bottom CTA, vary heading/copy per page
- **Images:** `.img-frame` (rounded+shadow wrapper), `.badge-experience` (floating stat badge over an image), `.product-hero-img`
- **Guide steps:** `.guide-step` (`.guide-num`, h4, p) — for Products page buying guide
- **Forms (contact page only):** `.form-box`, `.form-label-custom`, `.form-control-custom`, `.form-select-custom`, `#formAlert`, `#enquiryForm` id required, `.map-placeholder` (Google Maps iframe embed with Delhi search query)
- **Feature mini:** `.feature-mini` (`.feature-mini-icon`, h4, p) — small icon+text rows

All icons are Font Awesome 6 (`fas`, `fab`). All headings auto-styled — don't add inline font styles.

## Contact details (use exactly, do not alter)

- Company: Jai Baba Nanak Cloth Trading Co.
- Contact Person: Mr. Sahil Chawla
- Phone: +91 9910555033 / +91 8750555033
- Email: Jaibabananakclothtrading@gmail.com
- Location: Delhi, India
- WhatsApp link: `https://wa.me/919910555033`
- These already appear in header/navbar/footer includes — only repeat on the Contact page's own contact cards/details section.

## AOS usage

Add `data-aos="fade-up"` (and `data-aos-delay="100"`, `"200"`, `"300"` for staggering siblings in a row) to section headers and card grid items, `data-aos="fade-right"` / `"fade-left"` for two-column image+text rows, `data-aos="zoom-in"` for CTA sections. Don't over-animate — one data-aos per component, not nested.

## Internal linking

Every page should link to at least 2-3 other relevant pages in body copy or CTAs (e.g. Products page links to individual product pages and to Quality/Applications; product detail pages link back to `/products.html` and to 1-2 related product pages; About links to Manufacturing and Quality). Use descriptive anchor text, not "click here".

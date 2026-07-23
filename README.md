# Jaibaba Interlinings — Corporate Website

Premium B2B website for **Jai Baba Nanak Cloth Trading Co.** ("Jaibaba Interlinings"), a Delhi-based garment interlining fabric manufacturer established in 1977.

Built with plain **HTML5, CSS3, Bootstrap 5, JavaScript, jQuery, AOS and Swiper.js** — no frameworks, no build step. PHP is used only for the contact form email handler.

## Folder Structure

```
jaibaba/
├── index.html                 Homepage
├── about.html
├── manufacturing.html
├── products.html
├── quality.html
├── applications.html
├── exports.html
├── contact.html
├── product/                   7 product category pages
│   ├── buckram-fabric.html
│   ├── fusible-interlining.html
│   ├── pocketing-fabric.html
│   ├── collar-cuff.html
│   ├── belt-roll.html
│   ├── shoulder-pad.html
│   └── eva-foam.html
├── includes/                  Shared partials, injected client-side via fetch()
│   ├── header.html            Top contact/social bar
│   ├── navbar.html            Main navigation
│   └── footer.html            Newsletter bar, footer, WhatsApp float, back-to-top
├── assets/
│   ├── css/                   bootstrap, aos, swiper (vendor) + style/responsive/animation (custom)
│   ├── js/                    jquery, bootstrap bundle, aos, swiper (vendor) + main.js (custom)
│   └── images/                logo.svg + banners/factory/products/icons folders
├── php/
│   └── contact-form.php       Validates, sanitizes and emails the enquiry form
└── BUILD_SPEC.md              Internal design-system reference used while building the site
```

## How To Run Locally

This site uses client-side `fetch()` to load the shared header/navbar/footer on every page, and the contact form posts to a PHP endpoint. Both require the site to be served over `http://`, not opened directly as a `file://` path (fetch and PHP won't work from a double-clicked HTML file).

**Option A — PHP's built-in server (needed to test the contact form):**
```bash
cd jaibaba
php -S localhost:8000
```
Then open `http://localhost:8000/index.html`. Requires PHP installed locally (`brew install php` on macOS).

**Option B — Any static server (fine for browsing/design review, contact form will not send email):**
```bash
cd jaibaba
python3 -m http.server 8000
```

**Option C — VS Code "Live Server" extension** — right-click `index.html` → "Open with Live Server". Same limitation as Option B for the contact form.

## Deploying

Upload the whole folder to any PHP-capable host (shared hosting, cPanel, etc.) with the site root pointed at this folder. All internal links use root-relative paths (`/about.html`, `/assets/css/style.css`, etc.), so the site must be deployed at the domain root (or update the paths if it needs to live in a subfolder).

## Contact Form / Email

`php/contact-form.php` currently sends mail via PHP's built-in `mail()` function, which depends on the host's mail transfer agent being configured — many shared hosts support this out of the box, but delivery is not guaranteed everywhere.

**To switch to SMTP (recommended for reliable delivery):**
1. Install [PHPMailer](https://github.com/PHPMailer/PHPMailer) via Composer (`composer require phpmailer/phpmailer`) or download it manually.
2. In `php/contact-form.php`, fill in the `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION` constants (use environment variables in production, not hardcoded values).
3. Replace the body of `sendNotificationEmail()` with a PHPMailer SMTP call using those constants.

The recipient address is set to `Jaibabananakclothtrading@gmail.com` and the email subject is fixed to "New Bulk Enquiry from Website" per the brief.

## Logo

No `logo.png` was supplied at build time, so `assets/images/logo.svg` is a placeholder wordmark (navy square + "JAIBABA / INTERLININGS" in the brand colors). It's referenced everywhere a logo appears (navbar, footer, favicon, OG image). **Replace `assets/images/logo.svg`** with the real logo when available — either keep it as an SVG, or swap the file extension and update the handful of `<img src="/assets/images/logo.svg">` references site-wide (a simple find-and-replace across all `.html` files and `includes/`).

## Images

All photography is sourced from Unsplash (free-to-use license) and hotlinked directly via `images.unsplash.com` URLs — no local image downloads were needed. Every image URL used across the site was individually verified to return HTTP 200 before being placed on a page. If you'd prefer to self-host images for performance/uptime independence, download each URL used and update the `src`/`style="background-image"` references to point at local files under `assets/images/`.

## Content Notes

Copy avoids fabricated statistics, client names, certifications, and export country lists that weren't provided — only verified facts (established 1977, 48+ years, Delhi-based, 7 product categories, manufacturer/supplier/wholesaler/exporter/trader) are used as hard claims. Specification tables use qualitative/typical-range language rather than invented precise numbers. Update these sections with real data (certifications, exact specs, verified export markets, client testimonials) as it becomes available.

## Browser Support

Tested against modern evergreen browsers (Chrome, Edge, Safari, Firefox). Uses `IntersectionObserver` (counters) and `fetch` (includes, contact form) — both supported in all browsers released in the last several years; no polyfills included.

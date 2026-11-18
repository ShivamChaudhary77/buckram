# Jaibaba Interlinings: Dashboard Plan (Phase 0 review)

Goal: keep every URL, every word and every SEO tag the same, but let the owner edit all pages at `/admin`.
Stack: **plain PHP 8.0+ and JSON files.** No database, no Composer, no build step.

Review date: 2026-11-18. Nothing in the project was changed during this review except this file.

---

## 1. What the site is today

| Item | Finding |
|---|---|
| Pages | 18 HTML pages: 8 top-level pages and 10 product pages under `/product/` |
| Shared parts | `includes/header.html` (top bar), `includes/navbar.html`, `includes/footer.html` (newsletter bar, footer, WhatsApp button, back-to-top button) |
| How shared parts load | **In the browser with JavaScript** (`fetch()` in `assets/js/main.js`). Search engines and AI crawlers that don't run JS see **no menu, no footer, no address or phone number** |
| Generators | None. The pages were written by hand, following `BUILD_SPEC.md` |
| Forms | Enquiry form on `contact.html` → `php/contact-form.php` (PHP `mail()`), which stays as it is. The newsletter form in the footer only *pretends* to subscribe: it has no back end |
| Server files | No `.htaccess`, no `robots.txt`, no sitemap, no redirects file |
| Tracking | **None.** No GA4, GTM or Meta Pixel on any page |
| Assets | Local CSS/JS (Bootstrap 5, AOS, Swiper, jQuery) plus Google Fonts and Font Awesome from a CDN. ~120 local images |
| External media | Home page video comes from **another website** (`antardrishtiyog.com/jaibaba-video.mp4`). `applications.html` uses **7 Unsplash images** loaded from unsplash.com |
| Repo state | 9 pages have **uncommitted edits** ("client feedback"). They must be committed before the migration runs |

### URL list (all must keep working)
`/` and `/index.html`, `/about.html`, `/manufacturing.html`, `/products.html`, `/quality.html`, `/applications.html`, `/exports.html`, `/contact.html`, and `/product/` + `buckram-fabric`, `fusible-interlining`, `pocketing-fabric`, `collar-cuff`, `belt-roll`, `shoulder-pad`, `eva-foam`, `woven-knitted-elastics`, `tatron`, `velcro-tape` (each `.html`).

---

## 2. Page types → editable fields

### a) Product page (10 pages: same layout, so fully structured fields)
Every product page has the same 10 sections in the same order. Small differences found:
- `belt-roll` shows its "Features" as a tick list plus a paragraph; the other pages show 4 small icon boxes.
- `tatron` and `velcro-tape` have 3 variants; the others have 4. Some variants have a photo, some don't.
- `fusible-interlining` and `pocketing-fabric` have one extra row in the specification table.

| Section | Fields |
|---|---|
| Top banner | label + icon, page heading (H1), intro sentence, background image |
| Breadcrumb | automatic (Home / Products / *name*) |
| Overview | image + alt text, small label, heading, paragraphs (rich text), tick list, button |
| Applications | label, heading, intro, cards (icon, title, text), link |
| Features | label, heading, image + alt, *either* icon boxes (icon, title, text) *or* tick list + paragraph |
| Benefits | label, heading, cards (icon, title, text) |
| Specifications | label, heading, note, table rows (parameter, value) |
| Variants | label, heading, intro, cards (title, text, optional photo) |
| FAQ | label, heading, intro, questions and answers |
| Related products | pick 2 products (the card is built from that product's card fields) |
| Closing call-to-action | heading, text |
| **Product card** (used on Home, Products page and in "Related products") | short label ("Structured"), one-line summary, card image + alt |

If the parity test shows that the same product's card text differs between Home, Products and Related, each listing gets its own override field. Nothing is silently merged.

### b) Home page (one page)
Hero (label, H1, intro, 2 buttons, background, 4 stat boxes), trust strip, "Who we are" (video link, text), featured products (choose which), manufacturing, industries, "why choose us" cards, quality, export, testimonials, FAQ, closing call-to-action. Each one becomes a section with its own fields.

### c) Information pages (About, Manufacturing, Quality, Applications, Exports, Products, Contact)
Each page is laid out differently. Plan:
- Top banner + breadcrumb, FAQ and the closing call-to-action become **structured fields** (they repeat across pages).
- Everything else becomes a list of **sections**: the common card, tick-list and image-plus-text patterns get proper fields. Any one-off layout (for example the About timeline, the comparison tables) is kept as a **cleaned HTML section** so that nothing is lost, and it is edited with a simple rich-text editor.

### d) Site-wide settings (`site.json`, one "Settings" screen)
Business name, legal name, 2 phone numbers, email, head office and branch address, Google Maps link, opening hours, WhatsApp number and its pre-filled message, social links (Facebook, Instagram, X, YouTube), the main menu and the Products drop-down, footer columns and "about" text, newsletter bar text, default share image, and tracking IDs (empty for now).

---

## 3. SEO, AEO and GEO findings

### Problems the new templates fix automatically
| # | Problem | Pages | Fix |
|---|---|---|---|
| 1 | **Menu, footer, phone and address are invisible without JavaScript** (no internal links from the menu, no NAP for Google or AI tools) | all 18 | Server-render the header, menu and footer in PHP. The visible page looks identical. `main.js` keeps working (with nothing left to fetch, it simply skips that step) |
| 2 | **FAQs shown but no `FAQPage` schema** | 12 (home, quality, 10 products) | Build the schema from the same visible questions |
| 3 | **Breadcrumbs shown but no `BreadcrumbList` schema** | 17 | Generate it from the page's breadcrumb |
| 4 | **Structured data only on the home page** | 17 have none | Every page gets an `Organization` + `WebSite` + `WebPage` graph; `AboutPage`, `ContactPage`, `CollectionPage` (Products) and `Product` (no prices or ratings) where they fit |
| 5 | Organization address only says "Delhi" | home | Use the full head-office address that is already on the site (8710 Roshanara Road, Delhi 110007). Both phone numbers |
| 6 | `og:image` is a **relative path** and points to the **placeholder** `logo.svg` (the real logo is `logo.jpeg`) | all | Absolute URL. Default to the real logo, or a per-page image |
| 7 | No `og:url`, `og:site_name`, Twitter card tags or `robots` meta | all | Generated |
| 8 | No `robots.txt`, `sitemap.xml` or `llms.txt` | site | Generated automatically from the pages |
| 9 | Favicon is the placeholder `logo.svg` | all | Settings field (keeps the current file until the owner changes it) |
| 10 | Images have no `width`/`height` | all | Added from the real file sizes (avoids layout shift) |

### Problems shown to the editor as warnings (not auto-changed, because this is wording)
| # | Problem | Pages |
|---|---|---|
| 11 | Meta descriptions **too long** (Google cuts at ~160 characters) | all 18 (167–379 characters) |
| 12 | Titles **too long** (over ~60 characters) | 11 of 18 |
| 13 | No short "direct answer" paragraph under the H1 (AEO) | all; the field will exist but stay empty until the owner writes it |

### Content risks (owner must decide; nothing will be invented)
- **Home page testimonials** are anonymous ("Procurement Manager", "Export Buyer", "OEM Buyer") and show 5 stars. They will **not** be marked up as reviews or ratings. Please confirm that they are real quotes, or replace or remove them.
- **Newsletter form** does nothing. Options: remove it, or send sign-ups to the same email as the enquiry form.
- **Home page video** is hosted on another domain. If that site goes down, the video disappears. It would be better to host it on this site (the file exists locally in `assets/videos/` but is not in git because of its size).
- **Unsplash hotlinks** on Applications: better to download them into `assets/images/` (fewer outside dependencies, faster).
- The home page canonical is `/index.html`, while `/` serves the same page. See question 4.

---

## 4. Hosting facts

| Item | Finding |
|---|---|
| Domain | `www.jaibabainterlinings.com` (from the canonicals). **It does not resolve in DNS today**, so the site doesn't appear to be live yet. Good news: no Google ranking to protect yet |
| Server | Unknown (no `.htaccess`, `.cpanel.yml` or host docs in the repo). The contact form needs PHP, so the host must have PHP |
| Local PHP | 8.5 with `dom`, `json`, `mbstring`, `fileinfo`, `gd`, so everything can be built and tested locally |
| Deploys | Unknown (git, FTP or File Manager?) |
| Hostinger | A Hostinger connection is available in this workspace. If the site is hosted there, the steps can be tailored to hPanel |

The build will ship with: `.htaccess` (Apache/LiteSpeed: Hostinger, cPanel, Plesk), an Nginx snippet, the content folder **outside the public folder** when the host allows it, and cron-based steps for hosts without SSH.

---

## 5. Plan (one phase per request; each finishes with tests, then stops for review)

| Phase | What gets built | Done when |
|---|---|---|
| **1. Foundation** | `app/` core (config, JSON storage with atomic writes, routing, rendering, cache), `templates/`, `index.php`, `.htaccess`, Nginx snippet. A feature switch (`enabled`) is **off** by default, so the static site keeps serving | Unit tests pass |
| **2. Migration** | `scripts/migrate.py`: turns the 18 pages + 3 includes into `content/pages/*.json` + `content/site.json`. Re-runnable until go-live | Every page has a JSON file; nothing dropped |
| **3. Parity** | `tests/compare-site.php`: renders every URL in PHP and compares it with the static file (text, title, meta, canonical, OG, JSON-LD, H1, links, images) | "All 18 pages identical", apart from the documented improvements in section 3 |
| **4. Dashboard** | `/admin`: login (one password), page list, editor with SEO panel and character counters, preview, history and restore (last 30 versions), trash, images, redirects, settings. CSRF, login lockout, HTML cleaning. Brand colours (navy/gold) | Dashboard HTTP test passes |
| **5. SEO automation** | `sitemap.xml`, `robots.txt`, `llms.txt`, all JSON-LD, automatic 301 when a URL changes, broken-link check, live SEO checks in the editor, site-wide SEO report | SEO tests pass |
| **6. Go-live** | `GO-LIVE.md` (for your host), `scripts/go-live.sh` (dry run by default; `--apply` backs up the static files and prints the undo command), `rollback-go-live.sh`, `backup-content.sh`, plain-language `DASHBOARD-GUIDE.md`, live checker, `CLAUDE.md` notes | Live checker passes on the real domain |

What stays untouched: `assets/` (all CSS, JS and images), `php/contact-form.php`, the visual design.

---

## 6. Questions for you
1. **Hosting:** which host and plan (Hostinger, cPanel or other)? Do you have SSH or only File Manager? How do you upload: git, FTP or File Manager?
2. **Editors:** who will use the dashboard? One owner password is planned; say so if more than one person needs separate logins.
3. **Languages:** English only? (Assumed yes; no hreflang.)
4. **Home page URL:** keep `/index.html` as the official address (exactly as now), or make `/` the official one with a 301 from `/index.html` (cleaner, and safe because the site isn't live yet)? Recommended: `/`.
5. **Tracking:** do you want GA4, GTM or Meta Pixel? If yes, the IDs can be entered later in Settings.
6. **Content risks** in section 3: testimonials, newsletter form, video hosting, Unsplash images. Fix now, or leave as they are?

---

## 7. Progress

### Phase 1: Foundation ✅ (2026-11-18)
**Built**
- `app/`: `config.php` (defaults → env vars `JAIBABA_*` → untracked `config.local.php`), `bootstrap.php`, `helpers.php` (escaping, atomic locked JSON writes), `content.php` (pages, site settings, URL index, history of the last 30 versions, trash, redirects with loop/chain checks), `cache.php`, `render.php`, `front.php` (routing), `cli.php`.
- `templates/`: `layout.php`; `partials/` (head, top bar, menu, footer, section heading); `blocks/` (`page-hero`, `media-text`, `card-grid`, `spec-table`, `faq`, `product-cards`, `cta`, `html`).
- `index.php`, `.htaccess` (Apache/LiteSpeed), `deploy/nginx.conf.example`, `deploy/dev-router.php` (local preview), `content/.htaccess` (deny all).
- Tests: `php tests/run.php` → **112 passed, 0 failed**. `php app/cli.php check` renders every page through PHP.

**Decisions made**
- **Pages are an ordered list of blocks**, not one rigid template per type. The product pages differ in small ways (h3 vs h4, 3 vs 4 columns, optional photos), so each block has a few display options. In Phase 2 a section becomes a structured block only if it renders identically; otherwise it is kept as an `html` block.
- **Product cards** (Home, Products page, Related products) are built from the product page's own `card` fields, so they are edited in one place.
- The **header, menu and footer** are rendered on the server from `site.json`. `main.js` needs no change: with no `data-include` placeholders it skips the fetch step.
- The home page address stays **`/index.html`** for now (setting `home_path`; switch it to `/` to make `/` official with a 301).
- **Feature switch `enabled` is off by default.** While it is off, `.htaccess` keeps serving the static `.html` files (`DirectoryIndex index.html index.php`), and `index.php` serves a static file if the server passes one to it anyway.
- Address clean-up when on: uppercase → lowercase, `//` → `/`, `/page.html/` → `/page.html`, `/page` → `/page.html` (all 301). Unknown → real 404 (noindex). Dashboard redirects → 301 / 410.

**Run locally**: `php -S localhost:8000 deploy/dev-router.php`. To preview the PHP site against a content folder, prefix `JAIBABA_ENABLED=1 JAIBABA_CONTENT_DIR=/path/to/content`.

**Next: Phase 2 (Migration).** Needs the 9 uncommitted page edits committed first.

### Phase 2: Migration ✅ (2026-11-18)
**Built**
- `scripts/migrate.php` (+ `scripts/lib/migrate-lib.php`, `scripts/lib/html-compare.php`): turns the 18 static pages and 3 includes into `content/site.json` + `content/pages/*.json`.
  - `php scripts/migrate.php --dry-run --verbose` shows the report without writing.
  - It works in a scratch folder and only then copies into `content/`, so a failed run never leaves half a snapshot. Re-runnable until go-live; **never run it after go-live.**
- **Fidelity check built in:** every section is converted into fields, rendered back through the real templates and compared with the original (tags, attributes and text). Only exact matches are kept as fields; anything else is kept as an unchanged HTML section. Finally, each page's whole `<main>` is rendered and compared again.
- New block options found during migration: comparison tables (`spec-table` with `style: compare-table` and a note line), dark section headings, centred card rows, the dark "talk to us" tile in product lists, image frame/column/animation-delay variants.
- Local preview of the PHP site: `JAIBABA_ENABLED=1 JAIBABA_PREVIEW_PHP=1 php -S localhost:8000 deploy/dev-router.php`.
- Tests: `php tests/run.php` → **177 passed** (adds: migration has no problems, product pages fully structured, repeatable, every page renders with one H1).

**Result**
| | |
|---|---|
| Header, menu, footer (`site.json`) | rendered **identical** to `includes/*.html` |
| Main content of all 18 pages | rendered **identical** to the static files |
| Sections | **143 editable** as fields, **21 kept as HTML** |
| Product pages (10) | **100 % fields** (no HTML sections) |
| Product cards | one set of card fields per product; listings that show different wording (e.g. the longer summaries on the Products page) keep their own override |

**Kept as HTML (one-off layouts, still editable as HTML)**: home hero, trust strip, "Who we are" (contains a commented-out list), testimonials, the commented-out statistics band; About story, journey, leadership and timeline; Applications category grid; Contact cards, enquiry form and business details; Exports documents list; Manufacturing six stages and gallery; Products overview, "which category" grid and buying guide; Quality raw-material checks and packing.

**Not yet compared (Phase 3)**: `<head>` tags and JSON-LD per page, links and images across the whole document.

### Phase 3: Parity ✅ (2026-11-18)
**Built**
- `tests/compare-site.php` (+ `scripts/lib/parity.php`): for every static `.html` file, renders the same address through the PHP site and compares:
  - title, meta description, canonical and robots
  - every meta tag (Open Graph, Twitter) and every other `<head>` tag
  - JSON-LD, decoded and compared as data
  - H1s, every link target, every image `src` + `alt`
  - the visible text and the full `<body>` markup (tags, attributes, text; whitespace and comments ignored)
- **Baseline** = the static page as a browser shows it, with the `data-include` placeholders replaced by the include files (what `main.js` does).
- The only normalisation is the footer year (static file says "2026", PHP prints the current year; `main.js` overwrites it anyway).
- `php tests/run.php` now also runs parity and proves the checker catches real changes: a changed title, description, JSON-LD value, image alt, CSS class or link is each reported, and untouched pages stay "same". **185 passed.**

**Result: all 18 pages identical.** No accepted differences.

**What a crawler sees differently (deliberate, invisible to visitors):** the static pages ship empty `data-include` placeholders, so search engines and AI tools that don't run JavaScript see no menu, footer, phone or address. The PHP site sends them in the HTML. In a browser, both versions end up with identical markup, which is exactly what this test compares.

**Next: Phase 4 (Dashboard).** The SEO improvements from section 3 (schema, og:url, sitemap…) come in Phase 5. Each one will then be listed in `parity_accepted()` with its reason, so the parity report keeps separating deliberate changes from accidents.

### Phase 4: Dashboard ✅ (2026-11-18)
**Built** (`/admin`, plain PHP + the site's own Bootstrap, one `admin.js`, no build step)
| Screen | What it does |
|---|---|
| `setup.php` | First password, only with a one-time link (`php app/cli.php setup-link`, valid 24 h, dead after use) |
| `login.php` / `logout.php` | One password. Logout is a POST |
| `index.php` | All pages: search as you type, filter by type and status, status pills (Live / Draft / Hidden from Google), last edited |
| `new.php` | New product page or information page (with starter sections), or a copy of any page. Always starts as a draft |
| `edit.php` | Three tabs: **Page content** (sections: open, move, add, remove, hide), **Google & sharing** (with a Google result preview and character counters), **Page settings** (name, address, product card, breadcrumb, advanced). Side panel: Save / Publish / Preview, a quick Google check, earlier versions, unpublish, delete. "Unsaved changes" marker and leave-page warning. Refuses to overwrite a page that was saved in another window meanwhile |
| `preview.php` | Shows unsaved changes (noindex, not cached, nothing saved) |
| `history.php` | Last 30 versions, restore (the replaced version is kept too) |
| `delete.php` / `trash.php` | Delete moves to the Trash and offers a 301 to another page. Restore (also removes that redirect), delete for good, empty |
| `media.php` | Website pictures + uploads, search, folder filter, "used / not used", sizes, copy address, picture picker inside the editor. Only unused uploads can be deleted |
| `redirects.php` | Add / remove 301 and 410, with loop, chain and live-page checks |
| `settings.php` | Business details, phones, addresses, WhatsApp, social profiles, logo and pictures, menu (with drop-down), menu button, newsletter bar, footer, tracking IDs (checked format). Password change |

- The forms are **generated from one field map** (`app/admin_fields.php`), with plain-language labels. Technical options sit under "More options".
- Rich text: a small editor (bold, italic, link, list, paragraph, clear, view HTML). Pasting from Word or Google Docs comes in as plain text.
- **Renaming a live page** automatically creates a 301 from the old address and updates older redirects, so there are no chains. Moving a page back removes its redirect.

**Security**
- Password hashed (Argon2id) in `<content>/.auth.json` (chmod 600, not in git).
- Session cookie is HttpOnly, SameSite=Lax, Secure on HTTPS and limited to `/admin`; new id on login; 2 h idle timeout.
- CSRF token on every POST.
- Login locked for 15 minutes after 5 wrong passwords (per IP).
- Allow-list HTML cleaner on every rich-text and HTML field: scripts, `on*` attributes, `javascript:` links, unknown iframes and CSS tricks are removed. All 76 HTML values of the current site pass through **unchanged**.
- Uploads: checks the real file type, 8 MB limit, random safe names, resized above 2000 px. The `uploads/` folder gets an `.htaccess` that never runs scripts.
- Admin pages send `noindex`, `X-Frame-Options: DENY`, `nosniff` and `no-store`. Helper files (`admin/_*.php`) are blocked.

**Tests**
- `php tests/run.php` → **226 passed**. Key guarantee: **for all 18 pages, opening the editor and saving without changes gives exactly the same website**, and the same for Settings (header, menu, footer).
- `php tests/dashboard-http.php` → **50 passed**, over real HTTP with a temporary content copy: setup, login, wrong password, create, preview, publish, edit, stale-form protection, history restore, rename → 301, upload (and a disguised PHP file refused), delete → 301 → restore, manual redirect and loop refusal, settings, tracking-ID check, CSRF refused on all 9 POST screens, private files 403, lockout after 5 wrong passwords, no PHP warnings in the server log.
- Checked by hand in the browser: login, page list, editing and moving a section, saving (only the intended change differs from the static page), picture picker, phone width (no sideways scrolling).

**Try it locally**
```bash
cp -R content /tmp/jb-content
JAIBABA_CONTENT_DIR=/tmp/jb-content JAIBABA_BASE_URL=http://localhost:8000 php app/cli.php setup-link
JAIBABA_ENABLED=1 JAIBABA_PREVIEW_PHP=1 JAIBABA_CONTENT_DIR=/tmp/jb-content php -S localhost:8000 deploy/dev-router.php
```
Open the printed link to choose a password, then go to `http://localhost:8000/admin/`.

**Next: Phase 5 (SEO automation).** Generated `sitemap.xml`, `robots.txt` and `llms.txt`; the full JSON-LD graph (Organization, WebSite, WebPage, BreadcrumbList, FAQPage, Product, …); `og:url`, `og:site_name` and Twitter tags; tracking tags; broken-link check; full SEO checks in the editor and a site-wide SEO report. Each change will be listed as accepted in the parity test.

### Phase 5: SEO automation ✅ (2026-11-18)
**Generated from the content (editors can't break it)** — `app/seo.php`
- **Structured data on every page:** one JSON-LD graph containing:
  - `Organization`: name, brand, logo with size, description, founding year, full head-office address, phones, email, sales contact, official profiles
  - `WebSite`
  - `WebPage`, as `AboutPage`, `ContactPage` or `CollectionPage` where they fit, with the main picture and last-changed date
  - `BreadcrumbList`, from the visible breadcrumb
  - `FAQPage`, only from questions that are actually shown (hidden FAQ sections are left out)
  - `ItemList` of the products on the Products page

  There are no ratings, reviews or prices, because the site has none. Product pages deliberately have no `Product` markup: without a price or reviews, Google reports it as an error.
- **Head tags:** `og:url`, `og:site_name`, `og:image` as a full address with width and height, Twitter tags, and a robots tag on every page (`index,follow,max-image-preview:large`, or `noindex` for hidden pages, drafts and previews).
- **Share picture:** the real logo (`logo.jpeg`) instead of the SVG placeholder. WhatsApp, Facebook and LinkedIn don't show SVG pictures.
- **Pictures** get `width`/`height` from the real files, so the page doesn't jump while loading. Checked in the browser: every picture is exactly the same size on screen as before (the site CSS already has `img { height: auto }`).
- **`/sitemap.xml`:** live, indexable pages only, with real last-changed dates.
- **`/robots.txt`:** allows the site, blocks `/admin/`, lists the sitemap. A Settings switch can block AI tools (off by default).
- **`/llms.txt`:** the business summary, the key pages with one-line descriptions, and contact details.
- **Tracking:** GA4, Google Tag Manager and Meta Pixel are added to public pages once their IDs are set in Settings. They never appear on previews or the dashboard, and an ID in the wrong format prints nothing.
- **Settings → Facts for Google and AI search:** description, founding year, address in parts, official profiles. The migration filled these only from what the site already said: the home page schema and the footer address.

**Safety nets**
- Automatic 301 when a live page's address changes or the page is deleted (Phase 4).
- **Full check in the editor** (as last saved):
  - Google title and description: missing, too long or too short, or the same as another page
  - main heading: missing, or more than one
  - intro under the heading longer than 80 words (AEO)
  - main search phrase: missing from the title, heading or first sentences, or used by another page too
  - pictures without a description
  - broken links, links to drafts, links to redirected addresses
- **Dashboard → Google check:** every page's problems in one list, plus links to the generated files. Also on the command line: `php app/cli.php seo-report`.
- **Bulk fixes:** `php app/cli.php apply-fixes fixes.json [--apply]`. It's a dry run by default, changes a field only if it still holds the expected old text, and saves with history.

**Parity:** all 18 pages are still identical in visible text, links, images, titles, descriptions and canonical addresses. Five deliberate improvements are listed with their reasons in `parity_accepted()`: robots tag, added meta tags, other head tags, JSON-LD graph, picture sizes. Each one is checked precisely: for example, the home page's old Organization facts must all still be present.

**Tests:** `php tests/run.php` → **263 passed**; `php tests/dashboard-http.php` → **56 passed**; `php tests/compare-site.php` → all 18 identical apart from the accepted improvements.

**For the owner to decide (not changed, because it is wording):**
- `seo-report` lists 29 points: 11 titles over 60 characters, and all 18 descriptions over 160.
- The business description in Settings still lists 7 product lines; the site now has 10.

### Phase 6: Go-live kit ✅ (2026-11-18), not yet run on the real server
**Built**
- `scripts/go-live.sh`:
  - **dry run by default:** checks PHP and its extensions, checks the content folder can be written, renders every page and lists the old files.
  - **`--apply`:**
    1. backs up the old files (`site-backups/static-….tar.gz`)
    2. copies the content outside the web root (`site-content`), never overwriting existing content
    3. writes `app/config.local.php` (`enabled => true`)
    4. sets the password: asks for it in a terminal, otherwise prints a one-time setup link
    5. removes the old files
    6. checks every page and prints the undo command
  - Works from a run-once cron job when there is no SSH.
- `scripts/rollback-go-live.sh <backup>`: restores the old files and switches the PHP site off. Content is kept.
- `scripts/backup-content.sh`: daily `.tar.gz` of the content and uploads (no cache or sessions), keeps 14.
- `scripts/lib/common.sh`: finds a command-line PHP 8+ even where cron's `php` is php-cgi (cPanel, CloudLinux, Plesk paths).
- New CLI commands: `preflight`, `static-files`, `write-config`.
- `scripts/migrate.php` now **refuses to run while the site is switched on** (it would overwrite dashboard edits).
- `tests/verify-live.php URL`: reads the live sitemap. Checks every page (200, title, description, self-canonical, one H1, indexable), the generated files, the 301 clean-up, real 404s, 11 private paths blocked, and the dashboard login (not indexable).
- Docs:
  - `GO-LIVE.md`: Hostinger, cPanel, Plesk and Nginx; with SSH or cron only; after-go-live list; rollback
  - `DASHBOARD-GUIDE.md`: for the owner, no jargon
  - `CLAUDE.md`: project notes for future work
  - README pointer

**Rehearsed** on a copy of the project laid out like a host (`home/public_html` + `site-content` + `site-backups`):
1. dry run: "Everything is ready"
2. `--apply` with no terminal: backup made, content copied outside the web folder, config written, setup link printed, 21 old files removed, 18 pages render
3. `verify-live.php` against it: **111 passed, 0 failed**
4. rollback: all 18 pages back **byte-identical**, switch off
5. a change was made to the live content, then `--apply` again: content **kept**, the change is live
6. migration refused while live
7. backup made, without cache or sessions

**Hosting:** the Hostinger account connected here has no site for this domain, and the domain doesn't resolve yet. The runbook therefore covers each host type; the owner picks theirs.

**Left for the real go-live (see GO-LIVE.md):**
1. merge `php-dashboard` and deploy
2. run go-live on the server
3. set the password
4. run `verify-live.php`
5. remove the static files from git
6. add the daily backup cron
7. Force HTTPS and the www redirect
8. submit the sitemap in Search Console

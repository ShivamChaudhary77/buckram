# Jaibaba Interlinings website: project notes

B2B site for Jai Baba Nanak Cloth Trading Co. (garment interlinings, Delhi, since 1977).
It is a **plain PHP 8 + JSON site with a one-password `/admin` dashboard**: no framework, no Composer, no database, no build step. Plan and history: `DASHBOARD-PLAN.md`. Go-live: `GO-LIVE.md`. Owner manual: `DASHBOARD-GUIDE.md`.

## Where things live
- **Content** (pages, settings, redirects, history, password hash): the folder set as `content_dir`.
  - Before go-live: the `content/` snapshot in git.
  - After go-live: `../site-content` on the server (outside the web root, **not in git**).
  - Pages: `pages/<id>.json`, each an ordered list of **blocks**.
  - Settings: `site.json` (business facts, menu, footer, tracking IDs, the `org` facts used for structured data).
- **Code:**
  - `app/`: config, content store, render, front controller, SEO, security, sanitizer, media, CLI
  - `templates/`: `layout.php`, `partials/`, one file per block type in `blocks/`
  - `admin/`: the dashboard
- **Field map** (what the dashboard edits, with plain-language labels): `app/admin_fields.php`. To add a field, add one line there and use it in the block template.
- **Uploads:** `uploads/` in the web root (not in git, included in backups).
- **Untouched by the PHP site:** `assets/` (CSS, JS, images) and `php/contact-form.php`.

## Rules
- **After go-live, never run `scripts/migrate.php`.** It would overwrite dashboard edits; it refuses while `enabled` is true. Before go-live it can be re-run to pick up edits made to the static `.html` files.
- **Never put secrets in git:** `app/config.local.php`, `content/.auth.json`, `content/_*` and `uploads/` are ignored.
- **Don't invent facts** (stats, reviews, certifications, prices) in content or structured data.
- Keep PHP **8.0-compatible**: no enums, readonly properties or `array_is_list`, and use `?type` for nullable parameters.
- Output escaping: `e()` for text. HTML fields are cleaned with `sanitize_html()` on save.
- Every write goes through `app/content.php` (`page_save`, `page_save_from_editor`, `site_save`, `redirects_save`), so history, the URL index, cache clearing and validation always happen.

## How to test
```bash
php tests/run.php                 # unit tests (≈263): content, routing, migration, parity, dashboard forms, security, SEO
php tests/compare-site.php        # before go-live: every static page vs the PHP render (accepted improvements listed)
php tests/dashboard-http.php      # end-to-end over HTTP with a temp content copy (≈56)
php tests/verify-live.php https://www.jaibabananakinterlinings.com   # after every deploy
php app/cli.php check             # render every page, report errors
php app/cli.php seo-report        # SEO problems per page
```
Local preview of the PHP site:
```bash
JAIBABA_ENABLED=1 JAIBABA_PREVIEW_PHP=1 php -S localhost:8000 deploy/dev-router.php
```
Add `JAIBABA_CONTENT_DIR=/tmp/copy` to work on a copy. For a local password: `JAIBABA_BASE_URL=http://localhost:8000 php app/cli.php setup-link --content=/tmp/copy`.

## Manual upload (Hostinger, no Git)
`bash scripts/build-upload.sh --first-time` builds `dist/public_html.zip` (switched on, with `app/config.local.php` pointing to `../site-content`) and `dist/site-content.zip`. Later code updates: `bash scripts/build-upload.sh` (no config, content or uploads). Steps: `HOSTINGER-UPLOAD.md`.

## Go-live state
Not live yet (as of 2026-11-18): the `.html` files still serve the site and `enabled` is false. After go-live, delete the static `.html` files and `includes/` from git (see GO-LIVE.md §6), and update this section.

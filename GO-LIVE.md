# Go-live runbook: switch to the PHP site with the dashboard

> **Hostinger, uploading by hand (no Git)?** Follow **`HOSTINGER-UPLOAD.md`** instead: two ready-made ZIP files, step by step in hPanel.

This switches www.jaibabainterlinings.com from the old `.html` files to the PHP site you can edit at `/admin`. It takes about 15 minutes. It is reversible with one command, and the dashboard content is never lost by undoing.

Everything below was rehearsed on a copy of the site, in this order:
1. dry run
2. switch-over (as from cron, with no terminal)
3. live checker: 111 of 111 passed
4. rollback: all files back, byte-identical
5. second go-live: dashboard edits kept

---

## 0. What the server needs

| Need | How to check |
|---|---|
| PHP **8.0 or newer** (8.2+ recommended) with `json`, `mbstring`, `dom`, `fileinfo` (and `gd` for resizing uploads) | `bash scripts/go-live.sh` (dry run) checks all of this |
| Apache or LiteSpeed with `.htaccess` (Hostinger, cPanel, Plesk), or Nginx with `deploy/nginx.conf.example` | — |
| HTTPS, and one address form (`https://www.…`) | Turn on "Force HTTPS" in the hosting panel. See step 6 for www |
| A writable folder **next to** the website folder for the content (e.g. `site-content`) | The dry run checks it |

No database, Composer, Node or SSH is needed.

## 1. Before the day

1. **Merge the `php-dashboard` branch** into `main` and deploy it as usual. Nothing changes for visitors: the switch is off, and the old `.html` files keep serving the site.
2. **Check the contact form still sends** (`php/contact-form.php` is untouched).
3. **Run the dry run on the server** (step 3, without `--apply`). It must end with "Everything is ready".

## 2. Where things go

| What | Hostinger | cPanel | Plesk |
|---|---|---|---|
| Website folder | `/home/u…/domains/jaibabainterlinings.com/public_html` | `/home/USER/public_html` | `/var/www/vhosts/DOMAIN/httpdocs` |
| Content folder (made by the script) | `…/jaibabainterlinings.com/site-content` | `/home/USER/site-content` | `/var/www/vhosts/DOMAIN/site-content` |
| Backups (made by the script) | `…/jaibabainterlinings.com/site-backups` | `/home/USER/site-backups` | `/var/www/vhosts/DOMAIN/site-backups` |
| PHP version | hPanel → Websites → *site* → Advanced → PHP Configuration | MultiPHP Manager | Domains → PHP Settings |
| Terminal | hPanel → Advanced → SSH Access (most plans) | Terminal | SSH (if enabled) |
| Cron jobs | hPanel → Advanced → Cron Jobs | Cron Jobs | Scheduled Tasks |

The content folder sits outside the public folder, so it can't be downloaded and deploys never overwrite it. If your host only lets you write inside the website folder, use `--content-dir=content`: the folder is then protected by its own `.htaccess` (Apache/LiteSpeed only).

## 3. Go live

### A. With a terminal (SSH)
```bash
cd ~/domains/jaibabainterlinings.com/public_html   # your website folder
bash scripts/go-live.sh                             # dry run: checks, changes nothing
bash scripts/go-live.sh --apply                     # switch over
```
The script:
1. backs up the old files (`site-backups/static-….tar.gz`)
2. copies the content snapshot into `site-content`
3. switches the PHP site on (`app/config.local.php`, not in git)
4. asks you for the dashboard password
5. removes the old `.html` files
6. checks every page
7. prints the **undo command**. Copy it somewhere safe.

### B. Without a terminal (cron as a remote shell)
1. In the hosting panel, add a cron job that runs **every minute**:
   ```bash
   [ -f $HOME/domains/jaibabainterlinings.com/public_html/index.html ] && bash $HOME/domains/jaibabainterlinings.com/public_html/scripts/go-live.sh --apply > $HOME/go-live-log.txt 2>&1
   ```
   (cPanel: use `$HOME/public_html/…`.) The `[ -f …index.html ]` guard makes it run only once: after go-live that file is gone.
2. Wait two minutes, then **delete the cron job**.
3. Open `go-live-log.txt` in the File Manager. It ends with "The new website is live", a **one-time link** to choose the dashboard password (it works once and expires in 24 hours), and the undo command.

If the log says "No command-line PHP 8.0+ found", add `PHP_BIN=/opt/alt/php82/usr/bin/php ` (Hostinger/CloudLinux) or `PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php ` (cPanel) at the start of the command.

## 4. Set the dashboard password
- **With a terminal:** the script asks for it. To change it later: `php app/cli.php set-password`.
- **Without a terminal:** open the link from the log. To get a new link: `php app/cli.php setup-link` (it only works while no password exists).

Then log in at **https://www.jaibabainterlinings.com/admin/**.

## 5. Check the live site
From any computer with PHP (or on the server):
```bash
php tests/verify-live.php https://www.jaibabainterlinings.com
```
It reads the live sitemap and checks:
- every page answers 200, with its title, description, canonical and one H1
- the sitemap, robots.txt and llms.txt are served
- the 301 clean-up and real 404s work
- private files are blocked
- `/admin/` asks for a login

**Run it after every deploy.**

Also by hand:
- send a test enquiry through the contact form
- open a product page on a phone
- edit a page in the dashboard and check the change shows on the site

## 6. Right after go-live
1. **Remove the old files from git as well**, in a separate commit. Otherwise the next deploy brings them back, and as real files they would win over the PHP site:
   ```bash
   git rm index.html about.html applications.html contact.html exports.html manufacturing.html products.html quality.html product/*.html includes/*.html
   git commit -m "Remove the static pages: the PHP site serves them now"
   ```
2. **Daily backup:** add a daily cron job (for example at 03:00):
   ```bash
   bash $HOME/domains/jaibabainterlinings.com/public_html/scripts/backup-content.sh > /dev/null 2>&1
   ```
   It keeps the last 14 backups of the content and the uploaded pictures in `site-backups`. Download one now and then, so a copy exists off the server too.
3. **One address form:** the official addresses are `https://www.jaibabainterlinings.com/…`. Turn on "Force HTTPS" in the panel. If `jaibabainterlinings.com` (without www) doesn't already redirect, set a redirect to the www version in the panel (Hostinger: Domains → Redirects; cPanel: Redirects).
4. **Google Search Console and Bing Webmaster Tools:** add the site and submit `https://www.jaibabainterlinings.com/sitemap.xml`. Watch "Pages / Indexing" for the next two weeks.
5. **Tracking:** if you use Google Analytics, Tag Manager or Meta Pixel, enter the IDs in Dashboard → Settings → Visitor statistics. Then check in their real-time view that visits arrive.
6. **Uploaded pictures** live in `uploads/` inside the website folder. It isn't in git, so deploys never overwrite it, and the daily backup includes it.

## 7. Undo (rollback)
```bash
bash scripts/rollback-go-live.sh ~/domains/jaibabainterlinings.com/site-backups/static-YYYYMMDD-HHMMSS.tar.gz
```
This puts the old `.html` files back and switches the PHP site off. The content folder is kept, so going live again later keeps every dashboard edit (the script sees existing content and doesn't overwrite it). Without a terminal, run it once from cron the same way as step 3B.

## 8. Never do this after go-live
- **Don't run `php scripts/migrate.php`.** It would replace dashboard edits with the old `.html` files. (It now refuses while the site is switched on.)
- Don't upload old `.html` page files again.
- Don't edit `site-content/*.json` by hand while someone is using the dashboard. Use the dashboard, or `php app/cli.php apply-fixes` for bulk changes.

## Nginx (VPS)
Use `deploy/nginx.conf.example` in the server block (it blocks private folders and sends unknown addresses to `index.php`). Set `client_max_body_size 10m;` for picture uploads. The rest of this runbook is the same.

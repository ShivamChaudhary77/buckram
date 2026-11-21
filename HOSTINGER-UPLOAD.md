# Host the website on Hostinger Premium (manual upload)

This puts www.jaibabananakinterlinings.com online with the dashboard, using only the Hostinger panel (hPanel) and its File Manager. No GitHub connection is needed.

You upload **two ZIP files**:

| File | What it is | Where it goes |
|---|---|---|
| `dist/public_html.zip` (~90 MB, almost all pictures) | the website and the dashboard, already switched on | **inside** `public_html` |
| `dist/site-content.zip` (~75 KB) | all page texts and settings | **one folder above** `public_html` (so nobody can download it) |

On the server it will look like this:
```
/home/u123456789/domains/jaibabananakinterlinings.com/
├── public_html/        ← website (from public_html.zip)
├── site-content/       ← page texts and settings (from site-content.zip)
└── site-backups/       ← made later by the daily backup
```
`u123456789` is your Hostinger username. You can see it in the File Manager path and on the SSH Access page.

---

## Step 1: Make the two ZIP files (on your Mac)
In Terminal, in the project folder:
```bash
cd ~/Desktop/jaibaba
bash scripts/build-upload.sh --first-time
```
This creates `dist/public_html.zip` and `dist/site-content.zip`. The old `.html` pages aren't included: the PHP site builds every page from the content.

## Step 2: Add the website and connect the domain
1. In **hPanel → Websites → Add website**, choose to create an **empty website** (no WordPress, no builder) for `jaibabananakinterlinings.com`.
2. **If the domain was bought at Hostinger**, it connects by itself.
   **If it was bought elsewhere** (GoDaddy, BigRock, …), log in there and change the **nameservers** to:
   ```
   ns1.dns-parking.com
   ns2.dns-parking.com
   ```
   It can take a few hours, up to 24, before the domain opens your Hostinger site.

## Step 3: PHP version
**Websites → Manage (jaibabananakinterlinings.com) → Advanced → PHP Configuration**
- **PHP version:** choose **8.2** or **8.3** and save.
- **PHP extensions tab:** make sure `fileinfo`, `gd`, `mbstring`, `dom` and `json` are ticked. They usually are by default.

## Step 4: SSL (the padlock)
**Websites → Manage → Security → SSL**: install the free SSL for `jaibabananakinterlinings.com` (it covers www too), then turn on **Force HTTPS**.
The site already sends `jaibabananakinterlinings.com` (without www) to `https://www.jaibabananakinterlinings.com`, so everyone lands on one address.

## Step 5: Email for the contact form
The enquiry form sends from `no-reply@jaibabananakinterlinings.com` to `Jaibabananakclothtrading@gmail.com`. Hostinger only delivers PHP mail reliably when the sender mailbox exists:
**hPanel → Emails → (your domain) → Create email account** → `no-reply@jaibabananakinterlinings.com`, with any strong password. You never need to log in to it.

## Step 6: Upload the website
**Websites → Manage → Files → File Manager**
1. Open **`public_html`**. Delete what Hostinger put there (`default.php`, and `index.html` if present).
2. Click **Upload** and choose `dist/public_html.zip`.
3. Right-click the ZIP → **Extract** → extract into **this folder** (`public_html`). Then delete the ZIP.
4. Turn on **"Show hidden files"** (File Manager settings) and check that **`.htaccess`** is inside `public_html`. Without it, only the home page works.

You should now see `public_html/admin`, `app`, `assets`, `php`, `scripts`, `templates`, `index.php` and `.htaccess`.

> If the 90 MB upload stops halfway: create an FTP account (**Files → FTP Accounts**), upload the ZIP with FileZilla to the same place, then extract it in File Manager.

## Step 7: Upload the content folder (one level up)
1. In File Manager, go **up one level**, to the folder that **contains** `public_html` (`domains/jaibabananakinterlinings.com`).
2. Upload `dist/site-content.zip` there.
3. Right-click → **Extract** → into this folder. You now have `site-content` **next to** `public_html`, and inside it `site.json` and a `pages` folder. Delete the ZIP.

**Check:** the path must be `…/jaibabananakinterlinings.com/site-content/site.json`, not `…/site-content/site-content/site.json`.

## Step 8: Choose the dashboard password
Choose **one** of the two ways.

**A. With SSH (Terminal on your Mac)**
1. hPanel → **Advanced → SSH Access** → enable it, and copy the login command it shows (it looks like `ssh -p 65002 u123456789@123.45.67.89`).
2. In Terminal, paste that command and log in with your hosting password, then:
   ```bash
   cd domains/jaibabananakinterlinings.com/public_html
   php app/cli.php set-password
   ```
   Type a password of at least 10 characters, twice.

**B. Without SSH (a one-time cron job)**
1. hPanel → **Advanced → Cron Jobs** → Custom. Set it to run **once an hour**: Minute = a minute 2–3 minutes from now (e.g. `25`), Hour/Day/Month/Weekday = every (\*). Paste this command (replace `u123456789` with your username, and `php83` with the PHP version from Step 3):
   ```bash
   /opt/alt/php83/usr/bin/php -d display_errors=1 /home/u123456789/domains/jaibabananakinterlinings.com/public_html/app/cli.php setup-link
   ```
   - Don't add `> file.txt`: Hostinger captures the output of cron jobs itself, so such a file stays empty.
   - Don't use "every minute": each run makes a new link and cancels the previous one.
2. After that minute has passed, open the cron job's **output** in the Cron Jobs list (View output). It shows the link. Then **delete the cron job**.
3. Open the link in your browser and choose the password. The link works once and for 24 hours.

## Step 9: Check that everything works
1. Open **https://www.jaibabananakinterlinings.com**: the home page, the Products menu, two product pages, the Contact page.
2. Send a **test enquiry** through the contact form and check that it arrives (look in Spam the first time).
3. Open **https://www.jaibabananakinterlinings.com/admin/**, log in, change a word on a page, save, and see it on the site. Then change it back. Earlier versions are kept in the editor if you need them.
4. On your Mac, run the full automatic check:
   ```bash
   php tests/verify-live.php https://www.jaibabananakinterlinings.com
   ```
   It should end with `… passed, 0 failed`.

## Step 10: Daily backup (2 minutes, do it once)
hPanel → **Advanced → Cron Jobs** → Custom → **once a day** (e.g. 03:00):
```bash
/bin/bash /home/u123456789/domains/jaibabananakinterlinings.com/public_html/scripts/backup-content.sh
```
It keeps 14 days of backups of the pages, settings and uploaded pictures in `site-backups`. Download one to your computer once a month. Hostinger also makes its own weekly backups (**Files → Backups**).

## Step 11: Tell Google
1. **Google Search Console** → Add property → Domain → `jaibabananakinterlinings.com`. It asks you to add a TXT record: hPanel → **Domains → DNS / Nameservers**.
2. **Sitemaps** → submit `https://www.jaibabananakinterlinings.com/sitemap.xml`.
3. Do the same in **Bing Webmaster Tools** (it can import from Search Console).
4. Optional: put your Google Analytics / Meta Pixel IDs in **Dashboard → Settings → Visitor statistics**.

---

## Later: uploading code changes
Texts, pictures, menu and settings are changed in the **dashboard**: nothing to upload.
Only when the **code** changes (a developer changes a template, for example):
```bash
bash scripts/build-upload.sh
```
Upload the new `dist/public_html.zip` into `public_html` → Extract → **overwrite**. This update ZIP never contains your password, settings or pictures.

**Never** do any of these on the live site:
- upload `site-content.zip` again (it would replace everything edited in the dashboard)
- delete `public_html/app/config.local.php` (it switches the site on and points to the content)
- delete `public_html/uploads` (your uploaded pictures)
- upload the old `.html` page files

## If something goes wrong
| What you see | Fix |
|---|---|
| "500 Internal Server Error" | PHP version below 8.0 (Step 3), or `.htaccess` damaged. Check **Advanced → Error logs** |
| Home page works, other pages say "Page not found" from Hostinger | `.htaccess` is missing in `public_html` (Step 6.4) |
| Every page shows the site's own "Page Not Found" | `site-content` is in the wrong place (Step 7 check) |
| "This website needs PHP 8.0 or newer" | Step 3 |
| Dashboard asks for the setup link again | No password yet: Step 8 |
| Enquiries don't arrive | Create `no-reply@jaibabananakinterlinings.com` (Step 5), check Spam, wait 10 minutes |
| Forgot the dashboard password | Step 8A again (`php app/cli.php set-password`) |

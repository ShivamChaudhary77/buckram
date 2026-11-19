#!/usr/bin/env bash
# Build ZIP files for uploading the website by hand (File Manager / FTP), e.g. on Hostinger.
#
#   bash scripts/build-upload.sh --first-time     first upload: website (switched on) + content folder
#   bash scripts/build-upload.sh                  later code updates: website files only
#
# Output in dist/:
#   public_html.zip   extract INSIDE public_html
#   site-content.zip  (first time only) extract in the folder ABOVE public_html; it creates site-content/
#
# Update ZIPs never contain app/config.local.php, the content folder or uploads/,
# so extracting them over the live site keeps the password, dashboard edits and pictures.

set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FIRST=0
BASE_URL="https://www.jaibabainterlinings.com"
for arg in "$@"; do
  case "$arg" in
    --first-time) FIRST=1 ;;
    --base-url=*) BASE_URL="${arg#*=}" ;;
    -h|--help) sed -n '2,13p' "$0"; exit 0 ;;
    *) echo "Unknown option: $arg"; exit 1 ;;
  esac
done
command -v zip >/dev/null || { echo "The 'zip' command is needed."; exit 1; }

DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$DIST" "$STAGE/public_html"
rm -f "$DIST/public_html.zip" "$DIST/site-content.zip"

# ---- website files (the PHP site; the old .html pages and includes/ are not needed)
cd "$ROOT"
for item in .htaccess index.php admin app templates assets php scripts; do
  cp -R "$item" "$STAGE/public_html/"
done
rm -f "$STAGE/public_html/app/config.local.php"
rm -rf "$STAGE/public_html/assets/videos"
find "$STAGE/public_html" \( -name '.DS_Store' -o -name '*.lock' -o -name '.lock' \) -delete

if [ "$FIRST" = "1" ]; then
  # Switched on from the start. The content folder sits next to public_html, outside the web.
  cat > "$STAGE/public_html/app/config.local.php" <<PHP
<?php
// Server settings (not in git). Made by scripts/build-upload.sh --first-time.
return [
    'enabled'     => true,
    // .../domains/jaibabainterlinings.com/site-content (next to public_html, not downloadable)
    'content_dir' => dirname(__DIR__, 2) . '/site-content',
    'base_url'    => '$BASE_URL',
];
PHP

  # ---- content folder (pages, settings, redirects) from the snapshot
  mkdir -p "$STAGE/site-content"
  cp -R "$ROOT/content/." "$STAGE/site-content/"
  rm -rf "$STAGE/site-content"/_* "$STAGE/site-content"/.auth* "$STAGE/site-content/README.md"
  find "$STAGE/site-content" \( -name '.DS_Store' -o -name '.lock' -o -name '.gitkeep' \) -delete
  (cd "$STAGE" && zip -qr "$DIST/site-content.zip" site-content)
fi

(cd "$STAGE/public_html" && zip -qr "$DIST/public_html.zip" .)

echo "Built in $DIST:"
ls -lh "$DIST" | awk 'NR>1 {print "  " $NF "  (" $5 ")"}'
if [ "$FIRST" = "1" ]; then
  echo "First upload: extract public_html.zip inside public_html, and site-content.zip in the folder above it."
else
  echo "Update: extract public_html.zip inside public_html and overwrite. Never upload site-content again."
fi

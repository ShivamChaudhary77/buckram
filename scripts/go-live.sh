#!/usr/bin/env bash
# Switch the website from the old static .html files to the PHP site with the dashboard.
#
#   bash scripts/go-live.sh                     dry run: checks everything, changes nothing
#   bash scripts/go-live.sh --apply             switch over
#
# Options:
#   --content-dir=PATH   where pages and settings will live (default: a "site-content" folder
#                        next to the website folder, so it is outside the public web folder)
#   --base-url=URL       the public address (default: https://www.jaibabananakinterlinings.com)
#   --verify             after switching, run the live checker against --base-url
#
# Works without SSH: run it once from a cron job (see GO-LIVE.md). Without a terminal it
# never asks questions; it prints a one-time link for choosing the dashboard password.
# The undo command is printed at the end.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"

APPLY=0
VERIFY=0
CONTENT_DIR="$(dirname "$ROOT")/site-content"
BACKUP_DIR="${BACKUP_DIR:-$(dirname "$ROOT")/site-backups}"
BASE_URL="$(config_value base_url)"
for arg in "$@"; do
  case "$arg" in
    --apply) APPLY=1 ;;
    --verify) VERIFY=1 ;;
    --content-dir=*) CONTENT_DIR="${arg#*=}" ;;
    --base-url=*) BASE_URL="${arg#*=}" ;;
    -h|--help) sed -n '2,17p' "$0"; exit 0 ;;
    *) echo "Unknown option: $arg"; exit 1 ;;
  esac
done
[ "${CONTENT_DIR:0:1}" = "/" ] || CONTENT_DIR="$ROOT/$CONTENT_DIR"
BASE_URL="${BASE_URL%/}"

say() { printf '%s\n' "$*"; }
step() { printf '\n== %s\n' "$*"; }

say "Website folder : $ROOT"
say "Content folder : $CONTENT_DIR"
say "Backups folder : $BACKUP_DIR"
say "Address        : $BASE_URL"
say "PHP            : $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

if [ "$(config_value enabled)" = "1" ] && [ ! -f "$ROOT/index.html" ]; then
  say ""
  say "The new website is already live. Nothing to do."
  say "To go back to the old files: bash scripts/rollback-go-live.sh <backup file in $BACKUP_DIR>"
  exit 0
fi

step "1. Server check"
"$PHP" "$ROOT/app/cli.php" preflight --target="$CONTENT_DIR"

step "2. Every page renders in PHP"
"$PHP" "$ROOT/app/cli.php" check --content="$ROOT/content" | tail -n 3

step "3. Old files that the PHP site replaces"
STATIC_FILES="$("$PHP" "$ROOT/app/cli.php" static-files --content="$ROOT/content")"
COUNT="$(printf '%s\n' "$STATIC_FILES" | grep -c . || true)"
printf '%s\n' "$STATIC_FILES" | sed 's/^/   /'
say "   ($COUNT files; they are backed up first)"

if [ "$APPLY" != "1" ]; then
  say ""
  say "Everything is ready. This was a dry run: nothing was changed."
  say "To switch over:  bash scripts/go-live.sh --apply${CONTENT_DIR:+ --content-dir=$CONTENT_DIR}"
  exit 0
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$BACKUP_DIR/static-$STAMP.tar.gz"

step "4. Backing up the old files"
mkdir -p "$BACKUP_DIR"
chmod 750 "$BACKUP_DIR" 2>/dev/null || true
EXTRA=()
[ -f "$ROOT/app/config.local.php" ] && EXTRA+=("app/config.local.php")
# shellcheck disable=SC2086
(cd "$ROOT" && tar czf "$BACKUP" $STATIC_FILES "${EXTRA[@]+"${EXTRA[@]}"}")
say "   $BACKUP"

step "5. Content folder"
if [ -f "$CONTENT_DIR/site.json" ]; then
  say "   $CONTENT_DIR already has content: kept as it is (not overwritten)."
else
  mkdir -p "$CONTENT_DIR"
  cp -R "$ROOT/content/." "$CONTENT_DIR/"
  rm -rf "$CONTENT_DIR/_cache" "$CONTENT_DIR/_index.json"
  say "   copied the content snapshot to $CONTENT_DIR"
fi
chmod 750 "$CONTENT_DIR" 2>/dev/null || true

step "6. Switching on"
"$PHP" "$ROOT/app/cli.php" write-config --enabled=1 --content-dir="$CONTENT_DIR" --base-url="$BASE_URL"

step "7. Dashboard password"
if "$PHP" -r 'require $argv[1]."/app/bootstrap.php"; require APP_DIR."/security.php"; exit(password_is_set() ? 0 : 1);' "$ROOT"; then
  say "   A password already exists: kept."
elif has_tty; then
  "$PHP" "$ROOT/app/cli.php" set-password
else
  "$PHP" "$ROOT/app/cli.php" setup-link
fi

step "8. Removing the old files"
printf '%s\n' "$STATIC_FILES" | while IFS= read -r f; do
  [ -n "$f" ] && rm -f "$ROOT/$f"
done
rmdir "$ROOT/includes" "$ROOT/product" 2>/dev/null || true   # only when empty
"$PHP" "$ROOT/app/cli.php" rebuild-index >/dev/null
"$PHP" "$ROOT/app/cli.php" clear-cache >/dev/null
say "   done"

step "9. Checking the live content"
"$PHP" "$ROOT/app/cli.php" check | tail -n 1
if [ "$VERIFY" = "1" ]; then
  "$PHP" "$ROOT/tests/verify-live.php" "$BASE_URL" || say "   The live check found problems (see above)."
fi

say ""
say "The new website is live."
say "Dashboard: $BASE_URL/admin/"
say ""
say "To undo (puts the old files back and switches the PHP site off; your content is kept):"
say "   bash scripts/rollback-go-live.sh $BACKUP"

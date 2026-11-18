#!/usr/bin/env bash
# Daily backup of everything edited in the dashboard: the content folder (pages,
# settings, history, password hash) and the uploaded pictures. Keeps the newest 14.
#
#   bash scripts/backup-content.sh
#
# Run it from a daily cron job (see GO-LIVE.md). Set BACKUP_DIR to change where backups go
# (default: a "site-backups" folder next to the website folder) and KEEP to keep more or fewer.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"

CONTENT_DIR="$(config_value content_dir)"
UPLOADS_DIR="$(config_value uploads_dir)"
BACKUP_DIR="${BACKUP_DIR:-$(dirname "$ROOT")/site-backups}"
KEEP="${KEEP:-14}"

[ -f "$CONTENT_DIR/site.json" ] || { echo "No content found in $CONTENT_DIR"; exit 1; }
mkdir -p "$BACKUP_DIR"
chmod 750 "$BACKUP_DIR" 2>/dev/null || true

FILE="$BACKUP_DIR/content-$(date +%Y%m%d-%H%M%S).tar.gz"
ARGS=(-C "$(dirname "$CONTENT_DIR")" "$(basename "$CONTENT_DIR")")
[ -d "$UPLOADS_DIR" ] && ARGS+=(-C "$(dirname "$UPLOADS_DIR")" "$(basename "$UPLOADS_DIR")")
tar czf "$FILE" --exclude='_cache' --exclude='sessions' --exclude='.lock' "${ARGS[@]}"
chmod 640 "$FILE" 2>/dev/null || true

# Keep only the newest $KEEP backups.
ls -1t "$BACKUP_DIR"/content-*.tar.gz 2>/dev/null | tail -n +"$((KEEP + 1))" | while IFS= read -r old; do rm -f "$old"; done

echo "Backup saved: $FILE ($(du -h "$FILE" | cut -f1))"
echo "Tip: download a copy now and then (File Manager → site-backups) so a copy exists off the server too."

#!/usr/bin/env bash
# Undo the go-live: put the old static files back and switch the PHP site off.
# The content folder (pages and settings edited in the dashboard) is kept.
#
#   bash scripts/rollback-go-live.sh /path/to/site-backups/static-YYYYMMDD-HHMMSS.tar.gz

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"

BACKUP="${1:-}"
if [ -z "$BACKUP" ] || [ ! -f "$BACKUP" ]; then
  echo "Usage: bash scripts/rollback-go-live.sh <static-….tar.gz backup made by go-live.sh>"
  ls -1t "$(dirname "$ROOT")/site-backups"/static-*.tar.gz 2>/dev/null | head -n 5 | sed 's/^/  found: /' || true
  exit 1
fi

echo "Restoring the old files from $BACKUP"
tar xzf "$BACKUP" -C "$ROOT"
# The backup may contain the config from before go-live; make sure the PHP site is off either way.
"$PHP" "$ROOT/app/cli.php" write-config --enabled=0 >/dev/null
"$PHP" "$ROOT/app/cli.php" clear-cache >/dev/null 2>&1 || true
echo "Done. The website runs on the old .html files again."
echo "The content folder was not touched; going live again later keeps all dashboard edits."

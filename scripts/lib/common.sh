# Shared helpers for the go-live, rollback and backup scripts.
# Works without SSH too (cron): never asks questions when there is no terminal.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# Find a command-line PHP 8+ (cron's "php" can be php-cgi on shared hosting).
find_php() {
  local c p
  for c in "${PHP_BIN:-}" php php8.4 php8.3 php8.2 php8.1 php8.0 /usr/local/bin/php /usr/bin/php \
           /opt/cpanel/ea-php8*/root/usr/bin/php /opt/alt/php8*/usr/bin/php /usr/bin/php8* /opt/plesk/php/8.*/bin/php; do
    [ -n "$c" ] || continue
    for p in $c; do
      if command -v "$p" >/dev/null 2>&1 || [ -x "$p" ]; then
        if [ "$("$p" -r 'echo PHP_SAPI;' 2>/dev/null)" = "cli" ] && "$p" -r 'exit(PHP_VERSION_ID >= 80000 ? 0 : 1);' 2>/dev/null; then
          echo "$p"; return 0
        fi
      fi
    done
  done
  return 1
}

PHP="$(find_php)" || { echo "No command-line PHP 8.0+ found. Set PHP_BIN=/path/to/php and run again."; exit 1; }

has_tty() { [ -t 0 ] && [ -t 1 ]; }

# Read a config value as PHP sees it (env vars → app/config.local.php → defaults).
config_value() {
  "$PHP" -r 'require $argv[1] . "/app/bootstrap.php"; $v = config($argv[2]); echo is_bool($v) ? ($v ? "1" : "0") : $v;' "$ROOT" "$1"
}

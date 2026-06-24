#!/bin/bash
# Исправления для «Проверка системы» Bitrix — запуск на prod.
#
#   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
#   bash tools/perf/prod-checker-fixes.sh
set -euo pipefail

ROOT="${1:-/var/www/vilmed_ru_usr/data/www/vilmed.ru}"
cd "$ROOT"

SETTINGS="bitrix/.settings.php"
DBCONN="bitrix/php_interface/dbconn.php"
COMPOSITE_FLAG="bitrix/html_pages/.enabled"

echo "== prod-checker-fixes: $ROOT =="

if grep -q "initCommand" "$SETTINGS" 2>/dev/null && php -l "$SETTINGS" >/dev/null 2>&1; then
  echo "  OK: initCommand already in .settings.php"
elif grep -q "initCommand" "$SETTINGS" 2>/dev/null; then
  echo "  WARN: broken initCommand in .settings.php — restoring from backup if present"
  if [[ -f "${SETTINGS}.bak.checker" ]]; then
    cp -a "${SETTINGS}.bak.checker" "$SETTINGS"
    echo "  OK: restored ${SETTINGS} from .bak.checker"
  fi
fi

if ! grep -q "initCommand" "$SETTINGS" 2>/dev/null; then
  cp -a "$SETTINGS" "${SETTINGS}.bak.checker"
  php "$ROOT/tools/perf/add-initcommand.php"
  php -l "$SETTINGS" >/dev/null
fi

if grep -q "BX_CRONTAB_SUPPORT" "$DBCONN" 2>/dev/null; then
  echo "  OK: BX_CRONTAB_SUPPORT already in dbconn.php"
else
  cp -a "$DBCONN" "${DBCONN}.bak.checker"
  php -r '
    $f = "bitrix/php_interface/dbconn.php";
    $c = file_get_contents($f);
    if (strpos($c, "BX_CRONTAB_SUPPORT") !== false) { echo "skip\n"; exit(0); }
    $marker = "define(\"BX_UTF\", true);";
    $add = $marker . "\ndefine(\"BX_CRONTAB_SUPPORT\", true);";
    if (strpos($c, $marker) === false) { fwrite(STDERR, "ERROR: BX_UTF not found in dbconn.php\n"); exit(1); }
    file_put_contents($f, str_replace($marker, $add, $c));
    echo "  OK: added BX_CRONTAB_SUPPORT to dbconn.php\n";
  '
fi

if [[ -f "$COMPOSITE_FLAG" ]]; then
  echo "  OK: composite enabled ($COMPOSITE_FLAG exists)"
else
  echo "  WARN: composite OFF — enable in /bitrix/admin/composite.php"
  echo "        then: bash tools/perf/prod-warmup.sh https://vilmed.ru"
fi

if [[ -f "$ROOT/tools/perf/prod-cssinliner-fix.php" ]]; then
  echo
  echo "== cssinliner (reduce HTML bloat) =="
  php "$ROOT/tools/perf/prod-cssinliner-fix.php" || echo "  WARN: cssinliner fix failed"
fi

PHPINI="/var/www/vilmed_ru_usr/data/php-bin/vilmed.ru/php.ini"
if [[ -f "$PHPINI" ]]; then
  echo "  PHP ini ($PHPINI):"
  grep -E '^(max_input_vars|memory_limit|opcache\.|post_max_size|upload_max)' "$PHPINI" 2>/dev/null | sed 's/^/    /' || true
else
  echo "  WARN: site php.ini not found at $PHPINI"
fi

echo
echo "Crontab (vilmed_ru_usr, если ещё нет):"
echo '  * * * * * /usr/bin/php -f /var/www/vilmed_ru_usr/data/www/vilmed.ru/bitrix/modules/main/tools/cron_events.php'
echo
echo "Повторная проверка: /bitrix/admin/site_checker.php → Полное тестирование"
echo "Composite: bash tools/perf/prod-composite-check.sh https://vilmed.ru"

#!/bin/bash
# Безопасный деплoy vilmed.ru на prod.
#
#   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
#   bash tools/perf/prod-deploy.sh
#
# Полная очистка композита (редко):
#   CLEAR_HTML_PAGES=1 bash tools/perf/prod-deploy.sh
set -euo pipefail

ROOT="${1:-/var/www/vilmed_ru_usr/data/www/vilmed.ru}"
BASE="${2:-https://vilmed.ru}"
DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$ROOT"

SETTINGS_BAK="/root/.settings.php.bak.deploy"

echo "== backup .settings.php =="
cp -a bitrix/.settings.php "$SETTINGS_BAK"

echo "== git pull =="
git pull origin main

echo "== restore .settings.php =="
cp -a "$SETTINGS_BAK" bitrix/.settings.php

echo "== clear Bitrix runtime cache =="
CACHE_DIRS=(bitrix/cache bitrix/managed_cache bitrix/stack_cache)
if [[ -f bitrix/html_pages/.enabled ]]; then
  if [[ "${CLEAR_HTML_PAGES:-0}" == "1" ]]; then
    CACHE_DIRS+=(bitrix/html_pages)
    echo "  CLEAR_HTML_PAGES=1 — clearing composite cache"
  else
    echo "  skip bitrix/html_pages/ (composite ON; use CLEAR_HTML_PAGES=1 to clear)"
  fi
else
  echo "  skip bitrix/html_pages/ (composite OFF)"
fi
for dir in "${CACHE_DIRS[@]}"; do
  if [[ -d "$dir" ]]; then
    find "$dir" -mindepth 1 -delete 2>/dev/null || true
    echo "  cleared: $dir/"
  fi
done

if [[ "${CLEAR_HTML_PAGES:-0}" == "1" ]]; then
  touch bitrix/html_pages/.enabled
fi

echo "== Apache reload (opcache) =="
apache2ctl configtest
systemctl reload apache2

echo "== permissions =="
chown -R vilmed_ru_usr:vilmed_ru_usr .

if [[ -f bitrix/html_pages/.enabled ]]; then
  echo "== composite warmup =="
  bash "$DIR/prod-warmup.sh" "$BASE"
  bash "$DIR/prod-composite-check.sh" "$BASE" || true

  if [[ -f "$ROOT/tools/perf/webp-warmup.php" ]]; then
    echo "== webp batch (limit ${WEBP_LIMIT:-500}) =="
    php "$ROOT/tools/perf/webp-warmup.php" --limit="${WEBP_LIMIT:-500}" || true
  fi
fi

echo "Done."

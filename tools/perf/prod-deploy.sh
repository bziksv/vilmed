#!/bin/bash
# Безопасный деплoy vilmed.ru на prod.
#
#   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
#   bash tools/perf/prod-deploy.sh
#
# Быстрый деплой по умолчанию: pull + runtime cache + reload (~10–20 с).
#
# Опции:
#   INVALIDATE_HOME=1  — сбросить кеш только главной (footer/header/JS)
#   RUN_WARMUP=1       — полный прогрев композита + sitemap + webp (долго, не для каждой правки)
#   CLEAR_HTML_PAGES=1 — полная очистка bitrix/html_pages (редко)
#   FLUSH_REDIS=1      — сброс Bitrix cache в Redis (ВКЛ по умолчанию; кеш именно там, не в файлах)
#                        FLUSH_REDIS=0 — пропустить (если правки не требуют сброса кеша)
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
# ВАЖНО: на этом сервере Bitrix cache хранится в Redis (bitrix/.settings.php -> cache.type=redis).
# Файловые `find bitrix/cache -delete` НЕ сбрасывают managed/component/composite кеш — он в Redis.
# Для применения правок шаблонов/компонентов нужен сброс Redis: FLUSH_REDIS=1 (по умолчанию ВКЛ).
if [[ "${FLUSH_REDIS:-1}" == "1" ]] && grep -q "'type' => 'redis'" bitrix/.settings.php 2>/dev/null; then
  if command -v redis-cli >/dev/null 2>&1; then
    echo "  redis cache detected — FLUSHDB (sessions on files, safe)"
    redis-cli FLUSHDB >/dev/null 2>&1 && echo "  redis FLUSHDB OK" || echo "  redis FLUSHDB FAILED"
  else
    echo "  WARN: redis-cli not found, cache NOT cleared (Redis)"
  fi
else
  echo "  skip Redis flush (FLUSH_REDIS=0 or non-redis cache)"
fi
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
  echo "  WARN: после CLEAR_HTML_PAGES откройте /bitrix/admin/composite.php → Сохранить"
  echo "        (восстановит bitrix/html_pages/.config.php)"
fi

echo "== Apache reload (opcache) =="
apache2ctl configtest
systemctl reload apache2

echo "== permissions =="
chown -R vilmed_ru_usr:vilmed_ru_usr .

if [[ -f bitrix/html_pages/.enabled ]]; then
  if [[ "${INVALIDATE_HOME:-0}" == "1" ]]; then
    echo "== invalidate homepage composite =="
    bash "$DIR/prod-invalidate-home.sh" "$BASE" "$ROOT"
  fi

  if [[ "${RUN_WARMUP:-0}" == "1" ]]; then
    echo "== composite warmup (RUN_WARMUP=1) =="
    bash "$DIR/prod-warmup.sh" "$BASE"
    if [[ -f "$DIR/prod-warmup-sitemap.sh" ]]; then
      bash "$DIR/prod-warmup-sitemap.sh" "$BASE" "${SITEMAP_WARMUP_LIMIT:-25}"
    fi
    bash "$DIR/prod-composite-check.sh" "$BASE" || true

    if [[ -f "$ROOT/tools/perf/webp-warmup.php" ]]; then
      echo "== webp batch (limit ${WEBP_LIMIT:-1000}) =="
      php "$ROOT/tools/perf/webp-warmup.php" --limit="${WEBP_LIMIT:-1000}" || true
    fi
  else
    echo "  skip warmup (быстрый деплой; RUN_WARMUP=1 — полный прогрев, INVALIDATE_HOME=1 — только /)"
  fi
fi

echo "Done."

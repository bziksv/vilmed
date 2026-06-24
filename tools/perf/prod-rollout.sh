#!/bin/bash
# Полный выкат perf-фиксов на сервере (один скрипт, без wipe html_pages).
#
#   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
#   bash tools/perf/prod-rollout.sh
set -euo pipefail

ROOT="${1:-/var/www/vilmed_ru_usr/data/www/vilmed.ru}"
BASE="${2:-https://vilmed.ru}"
DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$ROOT"

echo "== prod-rollout: $ROOT =="

if git diff --name-only -- bitrix/themes/.default/modules.css 2>/dev/null | grep -q .; then
	echo "  reset local bitrix/themes/.default/modules.css"
	git checkout -- bitrix/themes/.default/modules.css
fi

SETTINGS_BAK="/root/.settings.php.bak.rollout"
cp -a bitrix/.settings.php "$SETTINGS_BAK"

echo "== git pull =="
git pull origin main
cp -a "$SETTINGS_BAK" bitrix/.settings.php

echo "== clear runtime cache (NOT html_pages) =="
for dir in bitrix/cache bitrix/managed_cache bitrix/stack_cache; do
	if [[ -d "$dir" ]]; then
		find "$dir" -mindepth 1 -delete 2>/dev/null || true
		echo "  cleared: $dir/"
	fi
done

if [[ -f bitrix/html_pages/.enabled ]]; then
	rm -f bitrix/html_pages/vilmed.ru/index@.html
	echo "  cleared: bitrix/html_pages/vilmed.ru/index@.html only"
	if [[ ! -f bitrix/html_pages/.config.php ]] && [[ -f tools/perf/prod-composite-restore.php ]]; then
		echo "== composite restore (.config.php missing) =="
		php tools/perf/prod-composite-restore.php
	fi
else
	echo "  WARN: composite OFF — open /bitrix/admin/composite.php → Save"
fi

echo "== cssinliner =="
if [[ -f tools/perf/prod-cssinliner-fix.php ]]; then
	php tools/perf/prod-cssinliner-fix.php
else
	echo "  WARN: prod-cssinliner-fix.php missing"
fi

echo "== apache reload =="
apache2ctl configtest
systemctl reload apache2

chown -R vilmed_ru_usr:vilmed_ru_usr .

echo "== warmup =="
bash "$DIR/prod-invalidate-home.sh" "$BASE" "$ROOT"
bash "$DIR/prod-warmup.sh" "$BASE"

if [[ -f tools/perf/webp-warmup.php ]]; then
	php tools/perf/webp-warmup.php --limit="${WEBP_LIMIT:-2000}" || true
fi

echo "== smoke =="
HTML=$(curl -sk --http1.1 -A 'vilmed-warmup/2.0' "$BASE/" 2>/dev/null || true)
SLIDE=$(echo "$HTML" | grep -c 'slide-lcp-img' || true)
MAIN=$(echo "$HTML" | grep -c '<main' || true)
SIZE=$(echo "$HTML" | wc -c | tr -d ' ')
DEFER=$(echo "$HTML" | grep -c ' defer' || true)
echo "  git HEAD: $(git log -1 --oneline)"
echo "  HTML bytes: $SIZE (target < 550000 after cssinliner)"
echo "  slide-lcp-img: $SLIDE (target >= 1)"
echo "  main landmark: $MAIN (target >= 1)"
echo "  defer scripts: $DEFER (target >= 5 on homepage)"
bash "$DIR/prod-composite-check.sh" "$BASE" || true

if [[ "$SLIDE" -lt 1 ]]; then
	echo "  FAIL: slide-lcp-img missing — git pull did not apply?"
	exit 1
fi

echo "Done."

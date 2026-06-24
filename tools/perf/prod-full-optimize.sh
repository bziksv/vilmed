#!/bin/bash
# Полный post-deploy цикл: deploy + checker + webp.
#
#   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
#   bash tools/perf/prod-full-optimize.sh
#   WEBP_LIMIT=3000 bash tools/perf/prod-full-optimize.sh
set -euo pipefail

ROOT="${1:-/var/www/vilmed_ru_usr/data/www/vilmed.ru}"
BASE="${2:-https://vilmed.ru}"
DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$ROOT"

WEBP_LIMIT="${WEBP_LIMIT:-2000}" bash "$DIR/prod-deploy.sh" "$ROOT" "$BASE"
bash "$DIR/prod-checker-fixes.sh" "$ROOT" || true

if [[ -f bitrix/html_pages/.enabled ]]; then
  bash "$DIR/prod-invalidate-home.sh" "$BASE" "$ROOT" || true
fi

echo
echo "== html_pages files =="
find bitrix/html_pages -type f ! -name '.enabled' 2>/dev/null | wc -l | xargs echo "  cached:"

echo
echo "PSI: https://pagespeed.web.dev/analysis?url=${BASE}/"
echo "Done."

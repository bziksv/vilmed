#!/bin/bash
# Сброс только кеша главной (после правок слайдера / CSS / LCP).
#
#   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
#   bash tools/perf/prod-invalidate-home.sh https://vilmed.ru
set -euo pipefail

BASE="${1:-https://vilmed.ru}"
ROOT="${2:-/var/www/vilmed_ru_usr/data/www/vilmed.ru}"

cd "$ROOT"

INDEX_CACHE="bitrix/html_pages/vilmed.ru/index@.html"
if [[ -f "$INDEX_CACHE" ]]; then
  rm -f "$INDEX_CACHE"
  echo "Removed: $INDEX_CACHE"
else
  echo "No index cache file (composite OFF or not warmed yet)"
fi

UA='Mozilla/5.0 (compatible; vilmed-warmup/2.0)'
echo "Re-warming / ..."
for i in 1 2 3; do
  ttfb=$(curl -sk --http1.1 -A "$UA" -o /dev/null -w '%{time_starttransfer}' --max-time 120 "${BASE}/" 2>/dev/null || echo '?')
  echo "  hit $i: TTFB=${ttfb}s"
done

echo "Done."

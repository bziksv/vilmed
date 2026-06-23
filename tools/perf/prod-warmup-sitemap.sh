#!/bin/bash
# Дополнительный прогрев: топ категорий из sitemap-iblock-24.xml
#
#   bash tools/perf/prod-warmup-sitemap.sh
#   bash tools/perf/prod-warmup-sitemap.sh https://vilmed.ru 30
set -euo pipefail

BASE="${1:-https://vilmed.ru}"
LIMIT="${2:-25}"
UA='Mozilla/5.0 (compatible; vilmed-warmup/2.0)'
SITEMAP="${3:-sitemap-iblock-24.xml}"

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

if [[ ! -f "$SITEMAP" ]]; then
  echo "ERROR: $SITEMAP not found"
  exit 1
fi

mapfile -t URLS < <(
  grep -oE 'https://vilmed\.ru/catalog/[^/<]+/' "$SITEMAP" 2>/dev/null \
    | sort -u \
    | head -n "$LIMIT" \
    | sed "s|https://vilmed.ru||"
)

echo "== Sitemap warmup: ${#URLS[@]} catalog sections from $SITEMAP =="

for path in "${URLS[@]}"; do
  url="${BASE}${path}"
  ttfb=$(curl -sk --http1.1 -A "$UA" -o /dev/null -w '%{time_starttransfer}' --max-time 120 "$url" 2>/dev/null || echo '?')
  echo "  $path  TTFB:${ttfb}s"
done

echo "Done. Cached files: $(find bitrix/html_pages -type f ! -name '.enabled' 2>/dev/null | wc -l | tr -d ' ')"

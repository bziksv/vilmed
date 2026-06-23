#!/bin/bash
# Проверка композитного кеша (с сервера или снаружи).
#   bash tools/perf/prod-composite-check.sh https://vilmed.ru
set -euo pipefail

BASE="${1:-https://vilmed.ru}"
UA='Mozilla/5.0 (compatible; vilmed-warmup/2.0)'

URLS=(
  "/"
  "/catalog/"
  "/catalog/oftalmologiya/"
  "/product/beskontaktnyy-tonometr-nct-200-shin-nippon/"
)

echo "== Composite check: $BASE =="
echo "Ожидание: X-Bitrix-Composite: Cache или warm TTFB < ~0.8s"
echo

composite_hits=0
fast_hits=0

for path in "${URLS[@]}"; do
  url="${BASE}${path}"
  echo "--- $path ---"
  for i in 1 2 3; do
    headers=$(curl -skI --http1.1 -A "$UA" --max-time 120 "$url" 2>/dev/null || true)
    ttfb=$(curl -sk --http1.1 -A "$UA" -o /dev/null -w '%{time_starttransfer}' --max-time 120 "$url" 2>/dev/null || echo '?')
    comp=$(echo "$headers" | grep -i '^X-Bitrix-Composite:' | tr -d '\r' || true)
    powered=$(echo "$headers" | grep -i '^X-Powered-By:' | tr -d '\r' || true)

    fast_note=''
    if awk -v t="$ttfb" 'BEGIN { exit !(t+0 > 0 && t+0 < 0.85) }' 2>/dev/null; then
      fast_hits=$((fast_hits + 1))
      fast_note='  [fast]'
    fi

    if [[ -n "$comp" ]]; then
      composite_hits=$((composite_hits + 1))
      echo "  hit $i: TTFB=${ttfb}s  $comp${fast_note}"
    else
      echo "  hit $i: TTFB=${ttfb}s  (no Cache header) ${powered:-}${fast_note}"
    fi
  done
  echo
done

echo "== Summary =="
if [[ "$composite_hits" -gt 0 ]]; then
  echo "  OK: X-Bitrix-Composite: Cache seen ($composite_hits responses)"
elif [[ "$fast_hits" -ge 4 ]]; then
  echo "  OK: TTFB fast ($fast_hits hits) — композит работает (режим Авто)"
else
  echo "  WARN: мало быстрых ответов"
  echo "  → bash tools/perf/prod-warmup.sh $BASE"
  echo "  → ls -la bitrix/html_pages/.enabled"
  echo "  → find bitrix/html_pages -type f ! -name '.enabled' | head"
fi

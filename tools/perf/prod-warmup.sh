#!/bin/bash
# Прогрев композитного кеша Bitrix после деплоя / очистки html_pages.
#
# На сервере:
#   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
#   bash tools/perf/prod-warmup.sh https://vilmed.ru
set -euo pipefail

BASE="${1:-https://vilmed.ru}"
UA='Mozilla/5.0 (compatible; vilmed-warmup/2.0)'
HITS="${WARMUP_HITS:-3}"

URLS=(
  "/"
  "/catalog/"
  "/catalog/oftalmologiya/"
  "/catalog/lor/"
  "/catalog/khirurgiya/"
  "/vendors/"
  "/product/beskontaktnyy-tonometr-nct-200-shin-nippon/"
  "/product/avtorefkeratometr-bez-stolika-poverennyy-rmk-200-kitay/"
  "/personal/cart/"
)

echo "== Warmup: $BASE (hits per URL: $HITS) =="
echo "UA: $UA"
echo

for path in "${URLS[@]}"; do
  url="${BASE}${path}"
  last_ttfb='?'
  comp=''

  for ((i=1; i<=HITS; i++)); do
    headers=$(curl -skI --http1.1 -A "$UA" --max-time 120 "$url" 2>/dev/null || true)
    ttfb=$(curl -sk --http1.1 -A "$UA" -o /dev/null -w '%{time_starttransfer}' --max-time 120 "$url" 2>/dev/null || echo '?')
    last_ttfb="$ttfb"
    comp=$(echo "$headers" | grep -i '^X-Bitrix-Composite:' | tr -d '\r' || true)
  done

  if [[ -n "$comp" ]]; then
    echo "  $path  TTFB:${last_ttfb}s  $comp"
  else
    echo "  $path  TTFB:${last_ttfb}s"
  fi
done

echo
echo "Done. Проверка: bash tools/perf/prod-composite-check.sh $BASE"
echo "Файлы кеша: find bitrix/html_pages -type f ! -name '.enabled' | wc -l"

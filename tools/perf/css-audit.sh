#!/bin/bash
# Аудит CSS: размеры файлов шаблона + bundle на prod.
#
#   bash tools/perf/css-audit.sh
#   bash tools/perf/css-audit.sh https://vilmed.ru
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TPL="$ROOT/bitrix/templates/elektro_flat"
BASE="${1:-https://vilmed.ru}"
UA='Mozilla/5.0 (compatible; vilmed-warmup/2.0)'

echo "== CSS source files =="
for f in colors.css template_styles.css css/template_styles.personal.css css/font-awesome.min.css; do
  if [[ -f "$TPL/$f" ]]; then
    kb=$(awk -v s="$(wc -c < "$TPL/$f")" 'BEGIN { printf "%.1f", s/1024 }')
    printf "  %6s KB  %s\n" "$kb" "$f"
  fi
done

echo
echo "== Prod CSS bundle (homepage) =="
HTML=$(curl -sk --http1.1 -A "$UA" --max-time 60 "$BASE/" 2>/dev/null || true)
href=$(echo "$HTML" | grep -oE 'href="[^"]+/bitrix/cache/css/[^"]+\.css[^"]*"' | head -1 | sed 's/href="//;s/"$//')
if [[ -n "$href" ]]; then
  case "$href" in
    http*) url="$href" ;;
    /*) url="${BASE}${href}" ;;
    *) url="${BASE}/${href}" ;;
  esac
  headers=$(curl -skI -A "$UA" -H 'Accept-Encoding: gzip' --max-time 20 "$url" 2>/dev/null || true)
  echo "  $href"
  echo "$headers" | grep -iE '^(HTTP|content-length|content-encoding)' | sed 's/^/    /'
else
  echo "  WARN: no bitrix/cache/css link found"
fi

echo
echo "Conditional CSS loads:"
echo "  template_styles.personal.css — only /personal/*"
echo "  bread.css — breadcrumb setting"
echo "  slick/slider/fancybox — home + catalog"

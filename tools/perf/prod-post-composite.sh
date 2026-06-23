#!/bin/bash
# Post-composite цикл на сервере после git pull.
#
#   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
#   bash tools/perf/prod-post-composite.sh
set -euo pipefail

ROOT="${1:-/var/www/vilmed_ru_usr/data/www/vilmed.ru}"
BASE="${2:-https://vilmed.ru}"
DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$ROOT"

echo "== 1. Apache reload (opcache) =="
apache2ctl configtest
systemctl reload apache2

echo
echo "== 2. WebP batch =="
if [[ -f "$ROOT/tools/perf/webp-warmup.php" ]]; then
  php "$ROOT/tools/perf/webp-warmup.php" --limit=1000 || true
else
  echo "  skip: tools/perf/webp-warmup.php not found"
fi

echo
echo "== 3. Composite warmup =="
bash "$DIR/prod-warmup.sh" "$BASE"

echo
echo "== 4. Composite check =="
bash "$DIR/prod-composite-check.sh" "$BASE" || true

echo
echo "== 5. html_pages on disk =="
if [[ -f bitrix/html_pages/.enabled ]]; then
  echo "  .enabled: yes"
else
  echo "  .enabled: MISSING — включите композит в /bitrix/admin/composite.php"
fi
find bitrix/html_pages -type f ! -name '.enabled' 2>/dev/null | wc -l | xargs echo "  cached files:"

echo
echo "Done."

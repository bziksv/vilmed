#!/bin/bash
# Выкат с локальной машины по SSH (нужен ключ: ssh-copy-id root@217.28.220.186).
#
#   bash tools/perf/remote-rollout.sh
#   bash tools/perf/remote-rollout.sh root@217.28.220.186
set -euo pipefail

SSH_HOST="${1:-root@217.28.220.186}"
ROOT="/var/www/vilmed_ru_usr/data/www/vilmed.ru"

echo "== remote-rollout → $SSH_HOST =="
ssh -o BatchMode=yes "$SSH_HOST" "bash -s" "$ROOT" <<'EOF'
set -euo pipefail
ROOT="$1"
cd "$ROOT"
bash tools/perf/prod-rollout.sh "$ROOT" https://vilmed.ru
EOF

echo "== external check =="
UA='Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36'
curl -sk --http1.1 -A "$UA" -o /dev/null -w "mobile TTFB: %{time_starttransfer}s\n" https://vilmed.ru/
curl -sk --http1.1 -A 'vilmed-warmup/2.0' https://vilmed.ru/ | grep -oE 'slide-lcp-img|<main id="main-content"' | head -3

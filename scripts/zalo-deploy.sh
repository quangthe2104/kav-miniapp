#!/usr/bin/env bash
# Build the Zalo Mini App bundle and upload it to Zalo as a Testing version (runs on the cPanel host or any Node 20+ box).
#
#   scripts/zalo-deploy.sh --token <ZALO_ACCESS_TOKEN>   # first time / when the zmp session expired
#   scripts/zalo-deploy.sh [--desc "mô tả phiên bản"]    # later deploys
#
# Access token: developers.zalo.me → Công cụ → API Explorer → chọn app "KAV Parent Consent" → Lấy Access Token.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP_ID="${APP_ID:-2203119465038830853}"
ZMP_DIR="${ZMP_DIR:-$HOME/zmp-tools}"
export PATH="/usr/local/bin:$PATH"

TOKEN=""
DESC="build $(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || date +%F)"
while [ $# -gt 0 ]; do
  case "$1" in
    --token) TOKEN="$2"; shift 2 ;;
    --desc) DESC="$2"; shift 2 ;;
    *) echo "Tham số không hợp lệ: $1" >&2; exit 1 ;;
  esac
done

if [ ! -x "$ZMP_DIR/node_modules/.bin/zmp" ]; then
  echo "[zalo-deploy] Cài zmp-cli vào $ZMP_DIR"
  mkdir -p "$ZMP_DIR"
  (cd "$ZMP_DIR" && { [ -f package.json ] || npm init -y >/dev/null; } && npm install --no-audit --no-fund zmp-cli@latest)
fi
ZMP="$ZMP_DIR/node_modules/.bin/zmp"

cd "$ROOT/miniapp"
grep -q '^APP_ID=' .env 2>/dev/null || echo "APP_ID=$APP_ID" >> .env

if [ -n "$TOKEN" ]; then
  echo "[zalo-deploy] zmp login (Mini App $APP_ID)"
  "$ZMP" login --app-id "$APP_ID" --token "$TOKEN"
fi

echo "[zalo-deploy] npm ci + build:zalo"
npm ci --no-audit --no-fund
npm run build:zalo

echo "[zalo-deploy] zmp deploy (Testing): $DESC"
"$ZMP" deploy --passive --existing --testing --outputDir dist-zalo --desc "$DESC"

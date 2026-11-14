#!/usr/bin/env bash
# Chạy trên server IONOS trước khi chép code mới: bật chế độ bảo trì.
# Lần deploy đầu tiên chưa có code thì bỏ qua.
set -euo pipefail

if [ ! -f artisan ] || [ ! -f .env ]; then
    echo "Chưa có bản cài đặt, bỏ qua bước bảo trì."
    exit 0
fi

source "$(dirname "$0")/php-bin.sh"

"$PHP" artisan down --retry=10 || true

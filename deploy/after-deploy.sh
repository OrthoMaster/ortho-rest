#!/usr/bin/env bash
# Chạy trên server IONOS sau khi đã chép code mới.
set -euo pipefail

source "$(dirname "$0")/php-bin.sh"

if [ ! -f .env ]; then
    echo "Thiếu file .env trên server. Tạo .env (xem README, mục Deploy) rồi chạy lại." >&2
    exit 1
fi

# Dù bước nào lỗi cũng mở lại trang, để không kẹt ở chế độ bảo trì.
trap '"$PHP" artisan up || true' EXIT

# storage/ và public/uploads/ không chép từ git, lần đầu phải tạo.
mkdir -p \
    storage/app/public storage/app/private \
    storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/framework/testing \
    storage/logs bootstrap/cache public/uploads

"$PHP" -v | head -1
"$PHP" artisan migrate --force
"$PHP" artisan optimize

echo "Deploy xong."

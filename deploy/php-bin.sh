#!/usr/bin/env bash
# Tìm lệnh PHP 8.4 trên hosting IONOS.
# Hợp đồng trước 15/09/2025: php8.4-cli; từ 15/09/2025: php8.4. Lệnh `php` trần là bản cũ.
# Đặt biến PHP_BIN (GitHub → Settings → Variables) để chỉ định thẳng.

if [ -n "${PHP_BIN:-}" ]; then
    PHP="$PHP_BIN"
elif command -v php8.4-cli > /dev/null 2>&1; then
    PHP=php8.4-cli
elif command -v php8.4 > /dev/null 2>&1; then
    PHP=php8.4
else
    echo "Không tìm thấy PHP 8.4 (php8.4-cli hoặc php8.4). Đặt biến PHP_BIN." >&2
    exit 1
fi

export PHP

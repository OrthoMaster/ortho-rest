# Liresto

Web và quản lý cho nhà hàng, nhiều quán trên một hệ thống. Laravel 13, MySQL.

Cần PHP 8.4 trở lên (`composer.lock` dùng Symfony 8), Composer và MySQL 8. Không cần Node/npm.

## Chạy ở local

```bash
git pull
composer setup          # cài vendor, tạo .env, tạo APP_KEY
```

Sửa `DB_USERNAME`, `DB_PASSWORD` trong `.env` nếu MySQL của bạn không phải root không mật khẩu, rồi:

```bash
mysql -u root -p -e "CREATE DATABASE liresto CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed
php artisan serve
```

Mở <http://127.0.0.1:8000>. Luôn dùng một địa chỉ (`127.0.0.1` hoặc `localhost`), vì cookie đăng nhập gắn theo địa chỉ.

## Tài khoản chạy thử

Mật khẩu đều là `password`.

| Email | Vai trò | Sau khi đăng nhập |
| --- | --- | --- |
| `super@liresto.test` | Super admin | `/super/restaurants` |
| `owner@quan-demo.test` | Chủ quán | `/quan-demo/admin` |
| `manager@quan-demo.test` | Quản lý | `/quan-demo/admin` |
| `staff@quan-demo.test` | Nhân viên, không thấy Website và Menu | `/quan-demo/admin` |
| `owner@bun-cha.test` | Chủ quán có domain riêng | `/bun-cha-ha-noi/admin` |

Web công khai: <http://127.0.0.1:8000/quan-demo>.

Domain riêng: <http://bun-cha.localhost:8000>. Link cũ <http://127.0.0.1:8000/bun-cha-ha-noi> chuyển 301 sang đó. Chrome, Edge và Firefox tự trỏ `*.localhost` về máy mình. Safari thì thêm `127.0.0.1 bun-cha.localhost` vào file hosts.

Email quên mật khẩu không gửi thật ở local, link nằm trong `storage/logs/laravel.log`.

## Lệnh hay dùng

```bash
php artisan test                        # chạy test, dùng SQLite trong RAM
php artisan migrate:fresh --seed        # xoá sạch DB, tạo lại dữ liệu thử
php artisan liresto:super-admin a@b.vn  # tạo super admin khi không chạy seed (production)
```

## Cấu trúc

| Đường dẫn | Nội dung |
| --- | --- |
| `app/Tenancy/` | Nhà hàng hiện tại, Global Scope, trait `BelongsToRestaurant` |
| `app/Modules/` | `ModuleManager`, Installer và route của từng module |
| `config/modules.php` | Danh sách module, phụ thuộc, mục menu |
| `config/liresto.php` | Domain chính, slug không được dùng |
| `routes/web.php` | Domain riêng, domain chính, super admin |
| `routes/tenant_public.php` | Web công khai, nạp cho cả slug và domain riêng |
| `routes/tenant_admin.php` | Phần quản lý của quán |
| `lang/vi/` | Mọi chữ giao diện |
| `public/css/app.css` | CSS phần quản lý, không cần build |

## Quy tắc khi thêm code

- Bảng mới của quán có `restaurant_id`, model dùng trait `BelongsToRestaurant`. Khi không có nhà hàng hiện tại, truy vấn trả về rỗng; đọc xuyên quán phải gọi rõ `forRestaurant()`.
- `User` và `RestaurantDomain` cố ý không dùng trait vì phải đọc được trước khi biết nhà hàng. Trong một quán, lấy user qua `$restaurant->users()`.
- Link của quán sinh bằng `tenant_route('public.home')`, không dùng `route()` trực tiếp, để đúng cả slug lẫn domain riêng.
- Chữ giao diện qua `__('ui....')`. Test `LanguageTest` báo lỗi nếu thiếu key trong `lang/vi`.
- Route mới ở cấp một phải thêm tên vào `reserved_slugs` trong `config/liresto.php`.
- Host của nền tảng khai trong `MAIN_DOMAINS`. Sau khi đổi trên server, chạy lại `php artisan route:cache` nếu đang dùng.

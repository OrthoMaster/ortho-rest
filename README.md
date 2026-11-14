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

Seed tạo một nhà hàng **Sài Gòn** (`/saigon`) như đã hoạt động 3 tháng tính tới lúc chạy: menu có tuỳ chọn, 2 khu (Trong quán T1–T10, Ngoài sân S1–S10), một Bếp, mở trưa và tối, nghỉ thứ Hai; có đơn tại bàn, đặt bàn, đơn mang đi, thu ngân, sự cố, ưu đãi, đơn nghỉ phép, lịch làm đã công bố tới hết tháng. Chạy mất khoảng 1 phút. Tạo lại từ đầu: `php artisan migrate:fresh --seed`.

Mật khẩu đều là `password` (đặt `SEED_PASSWORD` trong `.env` để đổi; production bắt buộc đặt mới seed được).

| Email | Vai trò | Sau khi đăng nhập |
| --- | --- | --- |
| `fx.tiennguyen92@gmail.com` | Super admin (đăng nhập ở `/login`) | `/super/restaurants` |
| `chunhahang@saigon.restaurant` | Chủ quán | `/saigon/admin` |
| `quanly@saigon.restaurant` | Quản lý | `/saigon/admin` |
| `bep1@saigon.restaurant`, `bep2@…` | Đầu bếp | `/saigon/admin` |
| `nhanvien1@saigon.restaurant` … `nhanvien5@…` | Nhân viên phục vụ | `/saigon/admin` |

Người của quán đăng nhập ở <http://127.0.0.1:8000/saigon/login>. Web công khai: <http://127.0.0.1:8000/saigon>.

Trang đặt bàn online: <http://127.0.0.1:8000/saigon/dat-ban>. Email gửi khách (đặt bàn) dùng máy chủ riêng của quán nếu đã cài ở Email trong quản lý, chưa cài thì dùng `MAIL_*` trong `.env` (tên người gửi là tên quán).

Email quên mật khẩu không gửi thật ở local, link nằm trong `storage/logs/laravel.log`.

## Lệnh hay dùng

Test chỉ chạy trên MySQL, dùng tài khoản trong `.env`. Tạo database test một lần:

```bash
mysql -u root -p -e "CREATE DATABASE liresto_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

```bash
php artisan test                        # chạy test trên database liresto_test của MySQL
php artisan migrate:fresh --seed        # xoá sạch DB, tạo lại dữ liệu thử
php artisan liresto:super-admin a@b.vn  # tạo super admin khi không chạy seed (production)
php artisan liresto:vapid               # tạo khoá thông báo đẩy, chép VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY vào .env (một lần)
```

Thông báo đẩy và cài ứng dụng (PWA) cần HTTPS (ở local thì `127.0.0.1`/`localhost` được). Chưa có khoá VAPID thì không hiện lời mời bật thông báo và không gửi gì; nút Cài ứng dụng vẫn có.

## Deploy (IONOS Web Expert)

Push lên `main` là GitHub Actions tự chạy (`.github/workflows/deploy.yml`): test + pint, qua hết thì bật bảo trì, `rsync` code vào thư mục trên server, chạy `deploy/after-deploy.sh` (migrate, `optimize`, mở lại trang). Test lỗi thì không deploy. Chạy tay được ở tab Actions → Run workflow.

Trên server, `.env`, `storage/` và `public/uploads/` không bao giờ bị ghi đè. Thư viện (`vendor/`) cài sẵn trên GitHub, server không cần Composer.

**Cài lần đầu**

1. Panel IONOS: chọn PHP 8.4 cho domain; tạo database MySQL 8; trỏ domain vào thư mục `<DEPLOY_PATH>/public`; bật SSL.
2. Tạo SSH key riêng cho GitHub (trên máy mình): `ssh-keygen -t ed25519 -f liresto_deploy -N ""`, rồi thêm nội dung `liresto_deploy.pub` vào `~/.ssh/authorized_keys` trên IONOS.
3. GitHub → Settings → Environments → `production`:
   - Secrets: `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY` (nội dung file `liresto_deploy`), `SSH_KNOWN_HOSTS` (kết quả `ssh-keyscan <SSH_HOST>`), `DEPLOY_PATH` (đường dẫn thư mục trên server, ví dụ `liresto`)
   - Variables: `APP_URL` (để kiểm tra `/up` sau khi deploy), `PHP_BIN` (không bắt buộc: `php8.4-cli` hay `php8.4`, để trống thì tự tìm)
4. Tạo `<DEPLOY_PATH>/.env` trên server từ `.env.example`: `APP_KEY` (tạo ở máy mình bằng `php artisan key:generate --show`), `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `MAIN_DOMAINS`, `DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` (lấy ở panel IONOS → Bases de données), `MAIL_*`, `SEED_PASSWORD`.
5. Chạy workflow lần đầu, rồi SSH vào chạy một lần: `php8.4 artisan liresto:vapid` (chép khoá vào `.env`), `php8.4 artisan liresto:super-admin <email>`, rồi `php8.4 artisan optimize` để nạp lại `.env`. Sửa `.env` trên server lúc nào cũng phải chạy lại `optimize`.

## Cấu trúc

| Đường dẫn | Nội dung |
| --- | --- |
| `app/Tenancy/` | Nhà hàng hiện tại, Global Scope, trait `BelongsToRestaurant` |
| `app/Modules/` | `ModuleManager`, và model, controller, route, Installer của từng module |
| `resources/views/modules/` | Màn hình của từng module |
| `config/modules.php` | Danh sách module, phụ thuộc, mục menu |
| `config/liresto.php` | Domain chính, slug không được dùng |
| `routes/web.php` | Domain riêng, domain chính, super admin |
| `routes/tenant_public.php` | Web công khai, nạp cho cả slug và domain riêng |
| `routes/tenant_admin.php` | Phần quản lý của quán |
| `lang/vi/` | Mọi chữ giao diện |
| `public/css/app.css`, `public/js/app.js` | CSS, bảng màu Liresto và JS giao diện, không cần build |
| `app/Tenancy/RestaurantTheme.php` | Đổi màu quán thành biến CSS |
| `app/Tenancy/RestaurantLogo.php` | Lưu, thay, xoá logo quán |
| `public/images/` | Logo Liresto và icon logo tạm |
| `public/uploads/` | File quán tải lên, không nằm trong git. Khi deploy phải giữ lại thư mục này |

## Quy tắc khi thêm code

- Bảng mới của quán có `restaurant_id`, model dùng trait `BelongsToRestaurant`. Khi không có nhà hàng hiện tại, truy vấn trả về rỗng; đọc xuyên quán phải gọi rõ `forRestaurant()`.
- `User` và `RestaurantDomain` cố ý không dùng trait vì phải đọc được trước khi biết nhà hàng. Trong một quán, lấy user qua `$restaurant->users()`.
- Link của quán sinh bằng `tenant_route('public.home')`, không dùng `route()` trực tiếp, để đúng cả slug lẫn domain riêng.
- Chữ giao diện qua `__('ui....')`. Test `LanguageTest` báo lỗi nếu thiếu key trong `lang/vi`.
- Route mới ở cấp một phải thêm tên vào `reserved_slugs` trong `config/liresto.php`.
- Màu chỉ dùng biến CSS trong `public/css/app.css`. Màu Liresto là pastel xanh. Trang của quán tự đổi theo màu quán lưu ở cột `theme`, không phải sửa từng view.
- Host của nền tảng khai trong `MAIN_DOMAINS`. Sau khi đổi trên server, chạy lại `php artisan route:cache` nếu đang dùng.

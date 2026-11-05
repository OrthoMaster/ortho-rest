# Liresto

Web và quản lý cho nhà hàng, nhiều quán trên một hệ thống. Laravel 13, MySQL, PHP 8.4. Không dùng queue, không có bước build frontend.

Cách chạy và cấu trúc thư mục xem `README.md`. Chức năng và các quyết định đã chốt nằm ở tài liệu "Liresto – Danh sách chức năng" trong Project.

- Bảng mới của quán có `restaurant_id`, model dùng trait `BelongsToRestaurant`. Đọc xuyên quán phải gọi rõ `forRestaurant()`.
- `User` và `RestaurantDomain` cố ý không dùng trait. Trong một quán, lấy user qua `$restaurant->users()`.
- Link của quán sinh bằng `tenant_route()`.
- Chữ giao diện qua `__()` và `lang/vi`, không viết cứng trong Blade.
- Màu chỉ dùng biến CSS trong `public/css/app.css`, không viết mã màu trong Blade. Màu Liresto là pastel xanh; trang của quán tự đổi theo màu quán.
- Route mới ở cấp một phải thêm tên vào `reserved_slugs` trong `config/liresto.php`.
- Đã đẩy lên `main` thì không sửa migration cũ, tạo migration mới.
- Trước khi xong: `php artisan test` và `vendor/bin/pint --test`.

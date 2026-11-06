# Liresto

Web và quản lý cho nhà hàng, nhiều quán trên một hệ thống. Laravel 13, MySQL, PHP 8.4. Không dùng queue, không có bước build frontend.

Cách chạy và cấu trúc thư mục xem `README.md`. Chức năng và các quyết định đã chốt nằm ở tài liệu "Liresto – Danh sách chức năng" trong Project.

- Bảng mới của quán có `restaurant_id`, model dùng trait `BelongsToRestaurant`. Đọc xuyên quán phải gọi rõ `forRestaurant()`.
- `User`, `UserDevice` và `RestaurantDomain` cố ý không dùng trait. Trong một quán, lấy user qua `$restaurant->users()`.
- Model mà người trong quán sửa được thì dùng trait `RecordsActivity` để ghi nhật ký thay đổi (khai `activityFields()`). Dữ liệu mẫu bọc trong `ActivityLog::quietly()`.
- Người của quán đăng nhập ở `/{slug}/login`; `/login` chỉ cho super admin.
- Link của quán sinh bằng `tenant_route()`.
- Chữ giao diện qua `__()` và `lang/vi`, không viết cứng trong Blade.
- Danh sách: nếu dòng mở được chi tiết thì bấm cả dòng, không chỉ phần tên (dùng class `row-link`), vì trên điện thoại rất khó chạm trúng link nhỏ.
- Dòng có nhiều thao tác thì gom vào nút 3 chấm dọc ở cuối dòng (`partials/kebab`).
- Thao tác Xoá luôn có chữ màu danger: `is-danger` trong menu 3 chấm, `link-danger` cho link, `btn-danger` cho nút.
- Nút chỉ có icon phải có nền hoặc viền thấy rõ vùng chạm, tối thiểu 44px trên điện thoại (class `row-btn`).
- Đổi thứ tự bằng kéo thả, không dùng nút lên xuống: thêm `data-sortable`, `data-sort-url`, `data-sort-id` và `partials/drag-handle`. Trên điện thoại giữ lâu vào dòng cũng kéo được.
- Lựa chọn bật/tắt luôn là toggle: `input[type=checkbox]` đã tự hiển thị thành toggle, không làm kiểu checkbox khác.
- Nút + và − của ô số dùng chung class `spin-group`, giống ô Giờ nhận.
- Chỗ tải ảnh dùng `partials/image-upload`.
- Ô bắt buộc có dấu * đỏ (`partials/required`): `partials/field` và `select` tự thêm khi bắt buộc; `date-picker`, `time-picker`, `number-stepper`, `textarea` truyền `'required' => true`.
- Danh sách không hiện số điện thoại đầy đủ của khách, dùng `mask_phone()` (chỉ 3 số cuối). Số đầy đủ chỉ ở trang chi tiết.
- Lọc danh sách dùng `partials/filter-bar` + `partials/filter-multi` (chọn nhiều, luôn có ô tìm): máy tính một hàng; điện thoại chỉ còn hàng ngày và nút Lọc mở bảng từ dưới lên. Mỗi giá trị đang lọc là một pill (`filter_pills()`), nhiều thì xuống dòng, bấm ✕ để bỏ. Ô ngày giữ độ dài vừa nội dung, không kéo hết dòng. Không xếp nhiều hàng nút và ô chọn.
- Chọn ngày dùng lịch `partials/date-picker`, hiện thứ và không hiện năm. Giờ và phút là hai ô riêng, phút bước 10, riêng giờ ca bước 5 (`partials/time-picker`).
- Trang con (ví dụ Sửa món là con của Menu) luôn có nút icon quay lại trang cha ở header: `partials/page-title`.
- Form chỉnh sửa thêm `data-guard`: có thay đổi chưa lưu thì hỏi lại trước khi rời trang.
- Màu chỉ dùng biến CSS trong `public/css/app.css`, không viết mã màu trong Blade. Màu Liresto là pastel xanh; trang của quán tự đổi theo màu quán.
- Route mới ở cấp một phải thêm tên vào `reserved_slugs` trong `config/liresto.php`.
- Đã đẩy lên `main` thì không sửa migration cũ, tạo migration mới.
- Trước khi xong: `php artisan test` (chỉ test trên MySQL, database `liresto_test`) và `vendor/bin/pint --test`. Chỉ đẩy lên khi cả hai đều qua.

<?php

return [
    'title' => 'Website',
    'tabs' => ['booking' => 'Web đặt trước'],
    'closed' => 'Nghỉ',

    'fields' => [
        'name' => 'Họ tên',
        'phone' => 'Số điện thoại',
        'email' => 'Email',
        'notes' => 'Lời nhắn',
    ],

    // Trang đặt bàn mẫu.
    'booking' => [
        'title' => 'Đặt bàn',
        'lead' => 'Chọn số người, ngày và giờ, quán sẽ giữ bàn cho bạn.',
        'step_when' => 'Chọn thời gian',
        'your_info' => 'Thông tin của bạn',
        'name_placeholder' => 'Tên người đặt',
        'phone_placeholder' => 'Số điện thoại',
        'email_placeholder' => 'Nhận email xác nhận (không bắt buộc)',
        'submit' => 'Gửi yêu cầu đặt bàn',
        'submit_direct' => 'Đặt bàn',
        'confirm_note' => 'Quán sẽ xác nhận và báo lại cho bạn.',
        'call' => 'Gọi quán',
        'hours' => 'Giờ mở cửa',
        'back_home' => 'Về trang quán',
    ],

    // Chữ của script đặt bàn (dùng cho cả trang mẫu và trang tự tạo).
    'widget' => [
        'party' => 'Số người',
        'fewer' => 'Bớt một người',
        'more' => 'Thêm một người',
        'people' => 'người',
        'date' => 'Ngày',
        'today' => 'Hôm nay',
        'closed' => 'Nghỉ',
        'pick_date' => 'Chọn ngày',
        'pick_date_first' => 'Chọn ngày để xem giờ còn trống.',
        'month' => 'Tháng :month/:year',
        'prev_month' => 'Tháng trước',
        'next_month' => 'Tháng sau',
        'time' => 'Giờ',
        'loading' => 'Đang xem giờ còn trống…',
        'no_slots' => 'Ngày này đã hết chỗ cho số người bạn chọn. Thử ngày khác hoặc gọi quán.',
        'day_closed' => 'Quán nghỉ ngày này.',
        'full' => 'Hết chỗ',
        'pick_time' => 'Chọn giờ bạn muốn đến.',
        'required' => 'Vui lòng nhập :field.',
        'bad_phone' => 'Số điện thoại chưa đúng.',
        'time_taken' => 'Giờ này vừa hết chỗ, bạn chọn giờ khác nhé.',
        'sending' => 'Đang gửi…',
        'sent_pending' => 'Đã gửi yêu cầu đặt bàn. Quán sẽ xác nhận và báo lại cho bạn.',
        'sent_confirmed' => 'Đặt bàn thành công. Hẹn gặp bạn!',
        'sent_test' => 'Gửi thử thành công: trang hoạt động tốt. Đây là bản thử, không có đặt bàn nào được tạo.',
        'expired' => 'Trang đã mở quá lâu. Tải lại trang rồi gửi lại nhé.',
        'failed' => 'Gửi chưa được. Kiểm tra mạng rồi thử lại, hoặc gọi quán.',
        'online_off' => 'Quán đang tạm ngưng nhận đặt bàn online. Gọi quán để đặt: :phone',
        'test_banner' => 'Bản xem thử: gửi thử không tạo đặt bàn thật.',
    ],

    // Trang khách xem đặt bàn của mình.
    'status' => [
        'title' => 'Đặt bàn của bạn',
        'code' => 'Mã đặt bàn',
        'when' => 'Thời gian',
        'party' => ':count người',
        'area' => 'Khu vực',
        'notes' => 'Lời nhắn',
        'name' => 'Người đặt',
        'states' => [
            'pending' => 'Quán đã nhận yêu cầu và sẽ xác nhận sớm.',
            'confirmed' => 'Quán đã xác nhận. Hẹn gặp bạn!',
            'rejected' => 'Rất tiếc, quán không còn chỗ vào giờ này.',
            'cancelled' => 'Đặt bàn này đã huỷ.',
            'no_show' => 'Đặt bàn này đã qua giờ hẹn.',
            'seated' => 'Bạn đã đến quán. Chúc ngon miệng!',
            'completed' => 'Cảm ơn bạn đã đến quán.',
        ],
        'cancel' => 'Huỷ đặt bàn',
        'cancel_title' => 'Huỷ đặt bàn?',
        'cancel_confirm' => 'Quán sẽ nhận được thông báo và giữ chỗ cho khách khác.',
        'cancel_keep' => 'Không huỷ',
        'cannot_cancel' => 'Đặt bàn này không huỷ được nữa. Gọi quán nếu cần.',
        'cancelled_done' => 'Đã huỷ đặt bàn.',
        'change_hint' => 'Muốn đổi giờ hay số người, vui lòng gọi quán.',
        'book_again' => 'Đặt bàn khác',
    ],

    'footer' => [
        'trouble' => 'Không đặt được?',
        'call' => 'Gọi :phone',
        'simple' => 'Dùng trang đặt bàn đơn giản',
    ],

    // Quản lý: Website → Web đặt trước.
    'admin' => [
        'lead' => 'Trang khách đặt bàn online. Dùng trang mẫu của Liresto (theo màu và logo của quán), hoặc tự tạo trang riêng bằng AI.',
        'link' => 'Link trang đặt bàn',
        'copy' => 'Sao chép',
        'copied' => 'Đã chép',
        'open' => 'Mở trang',
        'mode_title' => 'Trang đang dùng',
        'mode_default' => 'Trang mẫu của Liresto',
        'mode_default_hint' => 'Theo màu và logo của quán, luôn chạy đúng.',
        'mode_custom' => 'Trang tự tạo',
        'mode_custom_hint' => 'Trang riêng quán tự tạo (thường nhờ AI), đã xem thử và đăng.',
        'mode_custom_none' => 'Chưa có trang tự tạo nào được đăng.',
        'mode_saved' => 'Đã đổi trang đặt bàn đang dùng.',
        'preview_default' => 'Xem trang mẫu',
        'reservation_off' => 'Đặt bàn online đang tắt (Đặt bàn trước → Cài đặt). Trang vẫn mở được nhưng báo khách gọi quán.',

        'steps_title' => 'Tự tạo trang bằng AI',
        'step_prompt' => '1. Chép đoạn yêu cầu (prompt) bên dưới, dán vào ChatGPT, Claude hoặc Gemini. Thêm mô tả kiểu trang bạn muốn ở cuối.',
        'step_upload' => '2. Lưu mã AI trả về thành file .html rồi tải lên (hoặc dán mã). Liresto kiểm tra ngay.',
        'step_test' => '3. Xem thử, chọn ngày giờ và gửi thử một lượt (không tạo đặt bàn thật).',
        'step_publish' => '4. Gửi thử thành công thì bấm Đăng.',
        'prompt_title' => 'Prompt cho AI',
        'copy_prompt' => 'Chép prompt',
        'upload_title' => 'Tải trang lên',
        'upload_file' => 'File .html',
        'or_paste' => 'hoặc dán mã HTML',
        'paste_placeholder' => '<!doctype html>…',
        'upload' => 'Tải lên và kiểm tra',
        'upload_needed' => 'Chọn file hoặc dán mã HTML.',
        'uploaded' => 'Đã tải lên bản nháp và kiểm tra.',

        'draft_title' => 'Bản nháp',
        'no_draft' => 'Chưa có bản nháp. Tải trang lên để bắt đầu.',
        'draft_meta' => ':file · tải lên :time bởi :user',
        'checks' => 'Kiểm tra',
        'has_errors' => 'Còn lỗi cần sửa: nhờ AI sửa theo các dòng đỏ rồi tải lại.',
        'not_tested' => 'Chưa gửi thử. Bấm Xem thử, chọn ngày giờ và gửi thử một lượt.',
        'tested' => 'Đã gửi thử thành công lúc :time.',
        'preview' => 'Xem thử',
        'publish' => 'Đăng trang này',
        'publish_title' => 'Đăng trang đặt bàn?',
        'publish_confirm' => 'Khách mở link đặt bàn sẽ thấy trang này ngay.',
        'published' => 'Đã đăng trang đặt bàn mới.',
        'cannot_publish' => 'Chưa đăng được: cần hết lỗi và gửi thử thành công.',
        'discard' => 'Bỏ bản nháp',
        'discard_title' => 'Bỏ bản nháp?',
        'discard_confirm' => 'Bản nháp sẽ bị xoá. Trang đang chạy không đổi.',
        'discarded' => 'Đã bỏ bản nháp.',

        'live_title' => 'Đang chạy',
        'live_meta' => 'Đăng :time bởi :user',
        'versions_title' => 'Bản đã đăng trước',
        'restore' => 'Lấy lại làm bản nháp',
        'restored' => 'Đã lấy bản cũ làm bản nháp. Xem thử rồi đăng lại.',
        'no_versions' => 'Chưa có bản nào.',

        'log_mode' => 'Đổi trang đặt bàn sang: :mode',
        'log_upload' => 'Tải lên bản nháp trang đặt bàn',
        'log_publish' => 'Đăng trang đặt bàn tự tạo',
        'log_discard' => 'Bỏ bản nháp trang đặt bàn',
    ],

    'check' => [
        'too_big' => 'File lớn quá :size.',
        'not_html' => 'Không phải trang HTML.',
        'no_form' => 'Thiếu form đặt bàn: <form data-liresto-booking>.',
        'many_forms' => 'Chỉ được có một form data-liresto-booking.',
        'form' => 'Có form đặt bàn.',
        'field_ok' => 'Có ô :field.',
        'field_missing' => 'Thiếu ô :field (bắt buộc).',
        'field_optional' => 'Không có ô :field (không bắt buộc).',
        'when_ok' => 'Có chỗ đặt bộ chọn ngày giờ của Liresto.',
        'when_missing' => 'Thiếu chỗ đặt bộ chọn ngày giờ: <div data-liresto-when></div> trong form.',
        'submit_ok' => 'Có nút gửi.',
        'submit_missing' => 'Thiếu nút gửi trong form.',
        'action_ignored' => 'Form có action: sẽ bị bỏ qua, Liresto tự gửi.',
        'other_forms' => 'Có form khác ngoài form đặt bàn: trang đặt bàn chỉ được có một form.',
        'sensitive' => 'Có ô mật khẩu, số thẻ hoặc mã OTP: trang đặt bàn không được thu những thông tin này.',
        'external_script' => 'Có script tải từ nơi khác: sẽ bị chặn khi chạy.',
        'frames' => 'Có iframe hoặc nhúng trang khác: sẽ bị chặn khi chạy.',
        'no_name' => 'Không thấy {{ten_quan}}: tên quán đang viết cứng, đổi tên quán thì trang không đổi theo.',
    ],

    'prompt_unknown' => '(chưa có)',
    'prompt' => <<<'PROMPT'
Bạn là nhà thiết kế web. Hãy viết MỘT file HTML duy nhất (CSS viết trong thẻ <style>) cho trang đặt bàn của nhà hàng ":name". Trang chạy trên hệ thống Liresto.

Thông tin quán (dùng chữ giữ chỗ, KHÔNG viết cứng, Liresto tự thay khi hiển thị):
- {{ten_quan}}: tên quán (hiện tại: :name)
- {{logo}}: link ảnh logo, dùng trong <img src="{{logo}}">
- {{mau_chinh}}: màu chủ đạo của quán (hiện tại: :primary), {{mau_phu}}: màu phụ (hiện tại: :secondary)
- {{so_dien_thoai}}: số điện thoại (hiện tại: :phone)
- {{email}}: email của quán (hiện tại: :email)
- {{dia_chi}}: địa chỉ (hiện tại: :address)
- {{gio_mo_cua}}: giờ mở cửa (hiện tại: :hours)
- {{link_trang_chu}}: link trang chủ của quán

BẮT BUỘC (thiếu thì trang không đăng được):
1. Đúng một form: <form data-liresto-booking> (không đặt action, không đặt method).
2. Trong form có:
   - <input name="name" required> họ tên
   - <input name="phone" type="tel" required> số điện thoại
   - <input name="email" type="email"> email (không bắt buộc)
   - <textarea name="notes"></textarea> lời nhắn (không bắt buộc, nhãn là "Lời nhắn", không có placeholder)
   - <div data-liresto-when></div>: để TRỐNG, Liresto tự chèn vào đây bộ chọn số người, ô ngày (bấm mở lịch, chỉ ngày quán nhận đặt mới chọn được) và các giờ (giờ đã đầy bị khoá)
   - một nút <button type="submit">
3. Có thể thêm <div data-liresto-result></div> ở chỗ muốn hiện thông báo gửi thành công hoặc lỗi.
4. KHÔNG tự viết lịch, ô chọn ngày giờ hay số người. KHÔNG viết JavaScript gửi form: Liresto lo việc gửi và báo lỗi.
5. KHÔNG có form nào khác, KHÔNG có ô mật khẩu, số thẻ, mã OTP.
6. KHÔNG tải script từ nơi khác, KHÔNG dùng iframe. Được dùng Google Fonts và ảnh https.
7. KHÔNG viết cứng giờ mở cửa, giá, số chỗ: dùng chữ giữ chỗ ở trên.

Thiết kế:
- Tiếng Việt, ưu tiên điện thoại (một cột, chữ to dễ đọc, nút dễ bấm), máy tính thì đẹp và thoáng.
- Màu chủ đạo là {{mau_chinh}}: dùng cho nút gửi, điểm nhấn, viền ô đang chọn, tiêu đề nổi bật. {{mau_phu}} chỉ dùng phụ (đường kẻ, chữ nhỏ). Viết thẳng trong CSS, ví dụ: background: {{mau_chinh}};
- Chữ trên nền {{mau_chinh}} phải đọc rõ (màu sáng thì chữ tối, màu đậm thì chữ trắng).
- Logo {{logo}} đặt trên cùng, canh giữa, trong khung form đặt bàn, không viền (có thể đổ bóng nhẹ). Thông tin liên hệ đặt ở chân trang, không lặp lại tên quán: {{dia_chi}} một dòng; {{so_dien_thoai}} và {{email}} chung một dòng; {{gio_mo_cua}} một dòng. Mỗi mục có icon nhỏ phía trước.
- Khi gửi bị lỗi, cuộn trang tới lỗi đầu tiên.
- Có thể chỉnh màu bộ chọn của Liresto bằng biến CSS: --lb-primary (nền nút đang chọn), --lb-on-primary (chữ trên nền đó), --lb-radius (bo góc), --lb-line (màu viền); và các class .lb-chip, .lb-time, .lb-label.
- Chỉ trả về mã HTML hoàn chỉnh, bắt đầu bằng <!doctype html>.

Mô tả trang tôi muốn:
PROMPT,
];

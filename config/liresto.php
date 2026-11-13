<?php

return [

    /*
    | Các host của chính nền tảng. Mọi host khác được coi là domain riêng của
    | nhà hàng và phải có trong bảng restaurant_domains.
    */
    'main_domains' => array_values(array_filter(array_map(
        fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('MAIN_DOMAINS', 'localhost,127.0.0.1')),
    ))),

    // Slug không được dùng vì trùng đường dẫn hệ thống hoặc tiền tố ngôn ngữ.
    'reserved_slugs' => [
        'admin', 'api', 'css', 'en', 'favicon', 'forgot-password', 'images', 'impersonate',
        'internal', 'ja', 'js', 'ko', 'login', 'logout', 'profile', 'reset-password',
        'robots', 'sitemap', 'storage', 'super', 'up', 'uploads', 'vi', 'zh',
    ],

    'slug_pattern' => '[a-z0-9]+(?:-[a-z0-9]+)*',

    'module_cache_seconds' => 3600,

    // Mã giảm giá làm sau. Bật lại thì hiện tab và lựa chọn Mã khi tạo đơn.
    'discount_codes' => false,

    // Đĩa lưu file quán tải lên. Đổi sang s3 khi có Bizfly Simple Storage.
    'uploads_disk' => env('UPLOADS_DISK', 'public'),

    // Mật khẩu tài khoản của dữ liệu mẫu (php artisan db:seed). Production phải đặt mới seed được.
    'seed_password' => env('SEED_PASSWORD'),

    // Thông báo đẩy (Web Push). Tạo khoá một lần bằng `php artisan liresto:vapid`.
    'push' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'hello@liresto.vn')),
    ],

];

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
        'robots', 'sitemap', 'storage', 'super', 'up', 'vi', 'zh',
    ],

    'slug_pattern' => '[a-z0-9]+(?:-[a-z0-9]+)*',

    'module_cache_seconds' => 3600,

];

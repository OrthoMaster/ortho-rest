<?php

return [
    'confirmed' => ':Attribute nhập lại không khớp.',
    'current_password' => 'Mật khẩu hiện tại không đúng.',
    'email' => ':Attribute không đúng định dạng email.',
    'image' => ':Attribute phải là ảnh.',
    'max' => [
        'file' => ':Attribute không được lớn hơn :max KB.',
        'string' => ':Attribute không được dài quá :max ký tự.',
    ],
    'mimes' => ':Attribute phải là file PNG, JPG hoặc WEBP.',
    'min' => ['string' => ':Attribute phải có ít nhất :min ký tự.'],
    'not_in' => ':Attribute này không dùng được.',
    'password' => [
        'letters' => ':Attribute phải có ít nhất một chữ cái.',
        'mixed' => ':Attribute phải có cả chữ hoa và chữ thường.',
        'numbers' => ':Attribute phải có ít nhất một chữ số.',
        'symbols' => ':Attribute phải có ít nhất một ký hiệu.',
        'uncompromised' => ':Attribute này đã bị lộ, hãy chọn cái khác.',
    ],
    'regex' => ':Attribute không đúng định dạng.',
    'required' => 'Vui lòng nhập :attribute.',
    'string' => ':Attribute phải là chuỗi ký tự.',
    'unique' => ':Attribute này đã được dùng.',
    'uploaded' => ':Attribute tải lên không thành công. Hãy thử file nhỏ hơn.',

    'custom' => [
        'slug' => ['regex' => 'Slug chỉ gồm chữ thường không dấu, số và dấu gạch ngang.'],
        'domain' => ['regex' => 'Domain không hợp lệ, ví dụ đúng: nhahang.vn'],
        'primary' => ['regex' => 'Màu chính không hợp lệ.'],
        'secondary' => ['regex' => 'Màu phụ không hợp lệ.'],
    ],

    'attributes' => [
        'current_password' => 'mật khẩu hiện tại',
        'domain' => 'domain',
        'email' => 'email',
        'logo' => 'logo',
        'name' => 'tên',
        'owner_email' => 'email chủ quán',
        'owner_name' => 'tên chủ quán',
        'owner_password' => 'mật khẩu chủ quán',
        'password' => 'mật khẩu',
        'primary' => 'màu chính',
        'secondary' => 'màu phụ',
        'slug' => 'slug',
    ],
];

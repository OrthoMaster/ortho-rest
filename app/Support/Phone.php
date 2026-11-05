<?php

namespace App\Support;

class Phone
{
    public const RULE = 'regex:/^(\+?84|0)[0-9 .\-]{8,12}$/';

    /** Đưa số điện thoại về dạng 0xxxxxxxxx để cùng một khách chỉ có một hồ sơ. */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return str_starts_with($digits, '84') ? '0'.substr($digits, 2) : $digits;
    }
}

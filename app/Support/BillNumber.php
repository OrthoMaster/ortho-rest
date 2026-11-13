<?php

namespace App\Support;

/** Số hoá đơn của máy tính tiền nhập tay (không bắt buộc), để đối soát doanh thu với Liresto. */
class BillNumber
{
    public const RULES = ['nullable', 'string', 'max:40'];

    /** Bỏ khoảng trắng đầu cuối; rỗng thì không lưu. */
    public static function clean(?string $number): ?string
    {
        $number = trim((string) $number);

        return $number === '' ? null : $number;
    }
}

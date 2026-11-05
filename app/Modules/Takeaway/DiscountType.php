<?php

namespace App\Modules\Takeaway;

enum DiscountType: string
{
    case Percent = 'percent';
    case Amount = 'amount';

    /** Số tiền giảm, không bao giờ vượt quá tạm tính. */
    public function amountOff(int $value, int $subtotal): int
    {
        $amount = $this === self::Percent ? (int) round($subtotal * $value / 100) : $value;

        return min($amount, $subtotal);
    }

    public function label(int $value): string
    {
        return $this === self::Percent
            ? __('takeaway.discount_percent_label', ['value' => $value])
            : __('takeaway.discount_amount_label', ['amount' => vnd($value)]);
    }
}

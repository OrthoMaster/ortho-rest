<?php

namespace App\Models;

use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** Máy chủ email riêng của quán và các email nhận thông báo. Mật khẩu SMTP được mã hoá. */
class MailSetting extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected $hidden = ['smtp_password'];

    protected function casts(): array
    {
        return [
            'use_smtp' => 'boolean',
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
            'notify_emails' => 'array',
        ];
    }

    public static function current(): self
    {
        return self::firstOrNew();
    }

    /** Dùng máy chủ riêng khi đã bật và nhập đủ. */
    public function usesSmtp(): bool
    {
        return $this->use_smtp && filled($this->smtp_host) && filled($this->smtp_port) && filled($this->from_address);
    }
}

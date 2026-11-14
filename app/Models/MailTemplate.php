<?php

namespace App\Models;

use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** Mẫu email quán đã sửa. Chưa sửa thì dùng mẫu mặc định trong lang/vi/mail.php. */
class MailTemplate extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];
}

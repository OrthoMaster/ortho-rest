<?php

namespace App\Modules\DineIn\Models;

use App\Models\User;
use App\Tenancy\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sự cố khi phục vụ một bàn: loại, nội dung, ai ghi, lúc nào. */
class Incident extends Model
{
    use BelongsToRestaurant;

    public const KINDS = ['slow', 'wrong', 'complaint', 'broken', 'other'];

    protected $table = 'dinein_incidents';

    protected $guarded = ['id'];

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dinein_table_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kindLabel(): string
    {
        return __('dinein.incident_kinds.'.$this->kind);
    }
}

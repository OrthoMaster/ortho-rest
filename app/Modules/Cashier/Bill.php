<?php

namespace App\Modules\Cashier;

use App\Modules\DineIn\Models\DiningTable;
use App\Modules\Takeaway\Models\TakeawayOrder;
use App\Modules\Takeaway\Models\TakeawayOrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Một phiếu cần thu tiền ở quầy: lượt bàn (Lấy đơn tại bàn) hoặc đơn mang đi.
 * Gói chung để màn hình Thu ngân và Đối soát xử lý hai loại như nhau.
 */
class Bill
{
    public function __construct(public readonly DiningTable|TakeawayOrder $model) {}

    /** "t12" là lượt bàn 12, "o5" là đơn mang đi 5. */
    public function key(): string
    {
        return ($this->isTable() ? 't' : 'o').$this->model->getKey();
    }

    public function isTable(): bool
    {
        return $this->model instanceof DiningTable;
    }

    /** "Bàn A2" hoặc "Đơn M003_13" */
    public function title(): string
    {
        return $this->isTable()
            ? __('dinein.table_title', ['table' => $this->model->label])
            : __('takeaway.number', ['code' => $this->model->code()]);
    }

    /** Tên khách nếu có. */
    public function customer(): ?string
    {
        return $this->model->customer?->name;
    }

    /** Lúc phiếu sẵn sàng thu: đóng bàn, hoặc giờ nhận của đơn mang đi. */
    public function at(): CarbonImmutable
    {
        return $this->isTable()
            ? ($this->model->closed_at ?? CarbonImmutable::parse($this->model->created_at))
            : CarbonImmutable::parse($this->model->pickup_at);
    }

    /** Tình trạng phiếu lúc chờ thu: bàn đã đóng / đang phục vụ, đơn chờ khách lấy / đã giao. */
    public function state(): string
    {
        if ($this->isTable()) {
            return $this->model->closed_at
                ? __('cashier.state_closed', ['time' => $this->model->closed_at->format('H:i')])
                : __('cashier.state_open', ['time' => $this->model->created_at->format('H:i')]);
        }

        return __('cashier.state_order', ['status' => $this->model->status->label(), 'time' => $this->model->pickup_at->format('H:i')]);
    }

    public function subtotal(): int
    {
        return $this->isTable() ? $this->model->subtotal() : $this->model->subtotal;
    }

    public function discount(): int
    {
        return $this->isTable() ? $this->model->discount() : $this->model->discount_amount;
    }

    public function total(): int
    {
        return $this->subtotal() - $this->discount();
    }

    /** @return Collection<int, array{name: string, options: list<string>, quantity: int, total: int}> */
    public function lines(): Collection
    {
        if ($this->isTable()) {
            return $this->model->billLines()->values();
        }

        return $this->model->items->map(fn (TakeawayOrderItem $item) => [
            'name' => $item->name,
            'options' => $item->optionLines(),
            'quantity' => $item->quantity,
            'total' => $item->lineTotal(),
        ])->values();
    }

    public function paidAt(): ?CarbonImmutable
    {
        return $this->model->paid_at;
    }

    public function billNumber(): ?string
    {
        return $this->model->bill_number;
    }

    public function payer(): ?string
    {
        return $this->model->payer?->name;
    }

    /** @param  iterable<Model>  $models  @return Collection<int, self> */
    public static function wrap(iterable $models): Collection
    {
        return collect($models)->map(fn (DiningTable|TakeawayOrder $model) => new self($model))->values();
    }
}

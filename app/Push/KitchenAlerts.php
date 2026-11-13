<?php

namespace App\Push;

use App\Models\PushSubscription;
use App\Models\User;
use App\Modules\DineIn\LineStatus;
use App\Modules\DineIn\Models\OrderLine;
use App\Modules\DineIn\Models\Station;
use App\Modules\Schedule\Duty;
use App\Modules\Takeaway\Models\TakeawayOrderItem;
use App\Modules\Takeaway\OrderStatus;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Thông báo đẩy của Màn hình khu, cùng người nhận với chuông báo trên trang:
 * - món mới (tại bàn, mang đi hôm nay) cho người đang được phân công ở khu đó;
 * - món đã xong cho người phục vụ khu vực bàn trong ca, khu vực không ai được phân
 *   công (hoặc tắt Lịch làm việc) thì cho người đã gọi món.
 * Gom lại trong một lượt bấm, gửi sau khi đã trả trang về để người bấm không phải chờ.
 */
class KitchenAlerts
{
    /** @var array<int, list<string>> khu => các dòng "Bàn A5 · 2 × Phở bò" */
    private array $fresh = [];

    /** @var array<int, array{area: int, label: string, ordered: list<int>, lines: list<string>}> */
    private array $ready = [];

    private ?int $actor = null;

    private bool $scheduled = false;

    public function __construct(private WebPush $push) {}

    /** Gắn vào sự kiện của món: gọi trong AppServiceProvider::boot. */
    public static function listen(): void
    {
        OrderLine::created(function (OrderLine $line) {
            if ($line->status === LineStatus::New && $line->dinein_station_id) {
                app(self::class)->addFresh($line->dinein_station_id, __('push.table', ['table' => $line->diningTable->label]).' · '.self::dish($line));
            }
        });

        OrderLine::updated(function (OrderLine $line) {
            if ($line->wasChanged('status') && $line->status === LineStatus::Done) {
                app(self::class)->addReady($line);
            }
        });

        TakeawayOrderItem::created(function (TakeawayOrderItem $item) {
            $order = $item->order;

            if ($item->dinein_station_id && $item->done_at === null && $order->pickup_date->isToday()
                && in_array($order->status, [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Ready], true)) {
                app(self::class)->addFresh($item->dinein_station_id, __('push.order', ['code' => $order->code()]).' · '.self::dish($item));
            }
        });
    }

    public function addFresh(int $station, string $text): void
    {
        if ($this->active()) {
            $this->fresh[$station][] = $text;
            $this->schedule();
        }
    }

    public function addReady(OrderLine $line): void
    {
        if (! $this->active()) {
            return;
        }

        $table = $line->diningTable;
        $this->ready[$table->id] ??= ['area' => $table->area_id, 'label' => $table->label, 'ordered' => [], 'lines' => []];
        $this->ready[$table->id]['ordered'][] = $line->ordered_by;
        $this->ready[$table->id]['lines'][] = self::dish($line);
        $this->schedule();
    }

    /** Gửi những gì đã gom. Lỗi gửi chỉ ghi log, không làm hỏng thao tác của người bấm. */
    public function flush(): void
    {
        [$fresh, $ready] = [$this->fresh, $this->ready];
        $this->fresh = $this->ready = [];
        $this->scheduled = false;

        try {
            $this->sendFresh($fresh);
            $this->sendReady($ready);
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            $this->actor = null;
        }
    }

    /** @param array<int, list<string>> $fresh */
    private function sendFresh(array $fresh): void
    {
        if ($fresh === [] || ! has_module('schedule')) {
            return;
        }

        $onDuty = Duty::current()->whereIn('dinein_station_id', array_keys($fresh))->get(['user_id', 'dinein_station_id'])->groupBy('dinein_station_id');

        foreach (Station::whereKey(array_keys($fresh))->get() as $station) {
            $this->deliver($onDuty->get($station->id, collect())->pluck('user_id')->all(), [
                'title' => __('push.fresh_title', ['station' => $station->name]),
                'body' => $this->body($fresh[$station->id]),
                'url' => route('tenant.admin.kitchen.show', $station),
                'tag' => 'station-'.$station->id,
            ]);
        }
    }

    /** @param array<int, array{area: int, label: string, ordered: list<int>, lines: list<string>}> $ready */
    private function sendReady(array $ready): void
    {
        $staffed = Duty::staffedAreaIds();

        foreach ($ready as $id => $table) {
            $users = in_array($table['area'], $staffed, true)
                ? Duty::current()->where('area_id', $table['area'])->pluck('user_id')->all()
                : $table['ordered'];

            $this->deliver($users, [
                'title' => __('push.ready_title', ['table' => $table['label']]),
                'body' => $this->body($table['lines']),
                'url' => route('tenant.admin.dinein.tables.show', $table['label']),
                'tag' => 'ready-'.$id,
            ], 'dinein.order');
        }
    }

    /** @param list<int> $userIds */
    private function deliver(array $userIds, array $message, ?string $ability = null): void
    {
        $ids = array_values(array_diff(array_unique(array_filter($userIds)), [$this->actor]));

        if ($ability) {
            $ids = tenant()->users()->whereKey($ids)->get()->filter(fn (User $user) => $user->can($ability))->modelKeys();
        }

        $subscriptions = PushSubscription::whereIn('user_id', $ids ?: [0])->get();

        $this->push->send($subscriptions, [...$message, 'icon' => route('tenant.app-icon', ['size' => 192])]);
    }

    /** "2 × Cơm tấm (Sườn, Không ớt)" */
    private static function dish(OrderLine|TakeawayOrderItem $line): string
    {
        $options = $line->optionText();

        return $line->quantity.' × '.$line->name.($options !== '' ? ' ('.$options.')' : '');
    }

    /** @param list<string> $lines */
    private function body(array $lines): string
    {
        $shown = array_slice($lines, 0, 4);

        if (count($lines) > 4) {
            $shown[] = __('push.more', ['count' => count($lines) - 4]);
        }

        return implode("\n", $shown);
    }

    /** Chưa có khoá VAPID (hoặc không ở trong quán nào) thì bỏ qua. */
    private function active(): bool
    {
        $this->actor ??= auth()->id();

        return WebPush::enabled() && tenant() !== null;
    }

    /** Lần đầu có việc trong lượt bấm thì hẹn gửi sau khi trả trang. */
    private function schedule(): void
    {
        if (! $this->scheduled) {
            $this->scheduled = true;
            defer(fn () => $this->flush());
        }
    }
}

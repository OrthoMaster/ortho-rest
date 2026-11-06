<?php

namespace App\Modules\Reservation;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\Models\ReservationStatusLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Mọi thao tác ghi đặt bàn đi qua đây. Hàng của quán (rồi đến khu vực) bị
 * khoá trong transaction, nên hai người đặt cùng lúc phải xếp hàng và người
 * sau thấy chỗ người trước đã giữ.
 *
 * Chỗ còn tính trên tổng chỗ ngồi của quán, chỉ đếm đặt bàn đã được nhận
 * (đã xác nhận, đã đến), mỗi lượt giữ chỗ theo thời lượng lượt bàn đang cài.
 * Một bàn khách ngồi chung một khu vực: không chọn khu vực thì phải có ít
 * nhất một khu vực còn đủ chỗ cho cả nhóm, chọn thì khu vực đó phải đủ. Nhân viên được nhận quá
 * chỗ khi đã xác nhận ($data['overbook']), khách online thì không.
 */
class ReservationBooking
{
    public function __construct(private ModuleManager $modules) {}

    /**
     * @param  array{area_id: ?int, reserved_at: mixed, party_size: int, customer_name: string, customer_phone: ?string, customer_email?: ?string, notes?: ?string, locale?: string}  $data
     */
    public function create(array $data, ReservationSource $source, ?User $user = null): Reservation
    {
        $reservation = DB::transaction(function () use ($data, $source, $user): Reservation {
            $online = $source === ReservationSource::Online;

            // Số chỗ tính chung cả quán: khoá hàng của quán trước (cùng thứ tự với đơn mang đi).
            $this->lockRestaurant();
            $area = $this->lockArea($data['area_id'] ?? null);
            $start = CarbonImmutable::parse($data['reserved_at']);
            $end = $this->endOf($start);

            if ($online) {
                $this->assertOnlineAllowed($start, $end, (int) $data['party_size']);
            } else {
                $this->assertOpen($start);
            }

            if ($online || empty($data['overbook'])) {
                $this->assertSeats($area, $start, $end, (int) $data['party_size']);
            }

            $status = $online && $this->settings()['auto_confirm'] ? ReservationStatus::Confirmed : $source->initialStatus();

            $reservation = Reservation::create([
                'customer_id' => Customer::remember($data['customer_name'], $data['customer_phone'], $data['customer_email'] ?? null)->getKey(),
                'area_id' => $area?->getKey(),
                'created_by' => $user?->getKey(),
                'reserved_at' => $start,
                'ends_at' => $end,
                'party_size' => $data['party_size'],
                'status' => $status,
                'source' => $source,
                'notes' => $data['notes'] ?? null,
                'locale' => $data['locale'] ?? 'vi',
            ]);

            $this->log($reservation, null, $status, $user);

            return $reservation;
        });

        $this->notify($reservation);

        return $reservation;
    }

    /** @param array{area_id: ?int, reserved_at: mixed, party_size: int, customer_name: string, customer_phone: ?string, customer_email?: ?string, notes?: ?string} $data */
    public function update(Reservation $reservation, array $data, ?User $user = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $data, $user): Reservation {
            $this->lockRestaurant();
            $area = $this->lockArea($data['area_id'] ?? null);
            $reservation = Reservation::whereKey($reservation->getKey())->lockForUpdate()->with(['customer', 'area'])->firstOrFail();

            if (! $reservation->status->holdsSeats()) {
                throw ValidationException::withMessages(['reserved_at' => __('reservation.not_editable')]);
            }

            $start = CarbonImmutable::parse($data['reserved_at']);
            $end = $this->endOf($start);

            if (! $start->equalTo($reservation->reserved_at)) {
                $this->assertOpen($start);
            }

            if (empty($data['overbook'])) {
                $this->assertSeats($area, $start, $end, (int) $data['party_size'], $reservation);
            }

            $customer = $this->sameCustomer($reservation->customer, $data)
                ? tap($reservation->customer)->update(array_filter(['email' => $data['customer_email'] ?? null]))
                : Customer::remember($data['customer_name'], $data['customer_phone'], $data['customer_email'] ?? null);
            $changes = array_values(array_filter([
                $customer->getKey() !== $reservation->customer_id
                    ? __('reservation.change_customer', ['from' => trim($reservation->customer->name.' '.$reservation->customer->phone), 'to' => trim($customer->name.' '.$customer->phone)]) : null,
                ! $start->equalTo($reservation->reserved_at)
                    ? __('reservation.change_time', ['from' => $reservation->reserved_at->format('H:i d/m'), 'to' => $start->format('H:i d/m')]) : null,
                (int) $data['party_size'] !== $reservation->party_size
                    ? __('reservation.change_party', ['from' => $reservation->party_size, 'to' => $data['party_size']]) : null,
                $area?->getKey() !== $reservation->area_id
                    ? __('reservation.change_area', ['from' => $reservation->area?->name ?? __('reservation.no_area'), 'to' => $area?->name ?? __('reservation.no_area')]) : null,
                ($data['notes'] ?? null) !== $reservation->notes ? __('reservation.change_notes') : null,
            ]));

            $reservation->update([
                'customer_id' => $customer->getKey(),
                'area_id' => $area?->getKey(),
                'reserved_at' => $start,
                'ends_at' => $end,
                'party_size' => $data['party_size'],
                'notes' => $data['notes'] ?? null,
            ]);

            if ($changes !== []) {
                $reservation->edits()->create([
                    'restaurant_id' => $reservation->restaurant_id,
                    'user_id' => $user?->getKey(),
                    'changes' => $changes,
                ]);

                ActivityLog::record($reservation->restaurant_id, 'reservation', __('activity.updated', ['name' => $reservation->activityName()]), $changes, $reservation);
            }

            return $reservation;
        });
    }

    public function changeStatus(Reservation $reservation, ReservationStatus $status, ?User $user = null, bool $overbook = false): Reservation
    {
        $reservation = DB::transaction(function () use ($reservation, $status, $user, $overbook): Reservation {
            $this->lockRestaurant();
            $reservation = Reservation::whereKey($reservation->getKey())->lockForUpdate()->with('area')->firstOrFail();
            $from = $reservation->status;

            if (! $from->canBecome($status)) {
                throw ValidationException::withMessages(['status' => __('reservation.bad_transition')]);
            }

            // Đặt online đang chờ chưa giữ chỗ: lúc nhận mới kiểm tra còn chỗ.
            if (! $overbook && $from === ReservationStatus::Pending && in_array($status, ReservationStatus::holding(), true)) {
                $this->assertSeats($reservation->area, $reservation->reserved_at->toImmutable(), $reservation->ends_at->toImmutable(), $reservation->party_size, $reservation);
            }

            $reservation->update(['status' => $status]);
            $this->log($reservation, $from, $status, $user);

            return $reservation;
        });

        $this->notify($reservation);

        return $reservation;
    }

    /**
     * Chỗ còn vào giờ này: tổng của quán và từng khu vực đang mở. Chỗ của khu
     * vực không vượt chỗ còn của cả quán.
     *
     * @return array{total: int, left: int, largest: int, areas: list<array{id: int, name: string, left: int}>}
     */
    public function availability(CarbonImmutable $start, ?Reservation $ignore = null): array
    {
        $end = $this->endOf($start);
        $total = Area::totalSeats();
        $left = max(0, $total - $this->peak(Reservation::query(), $start, $end, $ignore));

        $areas = $this->areasLeft($start, $end, $left, $ignore);

        return ['total' => $total, 'left' => $left, 'largest' => (int) max([0, ...array_column($areas, 'left')]), 'areas' => $areas];
    }

    /**
     * Chỗ còn từng khu vực đang mở, không vượt chỗ còn của cả quán (đặt bàn
     * chưa chọn khu vực cũng chiếm chỗ của quán).
     *
     * @return list<array{id: int, name: string, left: int}>
     */
    private function areasLeft(CarbonImmutable $start, CarbonImmutable $end, int $totalLeft, ?Reservation $ignore): array
    {
        return Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Area $area) => [
                'id' => $area->getKey(),
                'name' => $area->name,
                'left' => min(max(0, $totalLeft), max(0, $area->capacity - $this->peak(Reservation::where('area_id', $area->getKey()), $start, $end, $ignore))),
            ])->all();
    }

    private function largestAreaLeft(CarbonImmutable $start, CarbonImmutable $end, int $totalLeft, ?Reservation $ignore): int
    {
        return (int) max([0, ...array_column($this->areasLeft($start, $end, $totalLeft, $ignore), 'left')]);
    }

    /** Nhân viên nhập tay: giờ đến phải nằm trong giờ mở cửa của quán. */
    private function assertOpen(CarbonImmutable $start): void
    {
        $hours = tenant()->hoursOn($start);

        if ($hours['closed'] !== null) {
            throw ValidationException::withMessages(['reserved_at' => $hours['closed']]);
        }

        if (! tenant()->opensAt($start)) {
            throw ValidationException::withMessages(['reserved_at' => __('reservation.time_closed', ['hours' => self::hoursText($hours['periods'])])]);
        }
    }

    /** @param list<array{start: string, end: string}> $periods */
    public static function hoursText(array $periods): string
    {
        return collect($periods)->map(fn (array $period) => substr($period['start'], 0, 5).'–'.substr($period['end'], 0, 5))->implode(', ');
    }

    private function turn(): int
    {
        return (int) $this->settings()['turn_minutes'];
    }

    private function lockRestaurant(): void
    {
        Restaurant::whereKey(tenant()->getKey())->lockForUpdate()->first();
    }

    /** Cùng khách cũ khi tên và số điện thoại không đổi (khách không có số thì không tạo khách mới mỗi lần sửa). */
    private function sameCustomer(Customer $customer, array $data): bool
    {
        return $customer->name === $data['customer_name'] && $customer->phone === ($data['customer_phone'] ?? null);
    }

    private function lockArea(?int $id): ?Area
    {
        if ($id === null) {
            return null;
        }

        return Area::whereKey($id)->where('is_active', true)->lockForUpdate()->firstOr(
            fn () => throw ValidationException::withMessages(['area_id' => __('reservation.area_unavailable')]),
        );
    }

    private function endOf(CarbonImmutable $start): CarbonImmutable
    {
        return $start->addMinutes($this->turn());
    }

    private function assertSeats(?Area $area, CarbonImmutable $start, CarbonImmutable $end, int $party, ?Reservation $ignore = null): void
    {
        $total = Area::totalSeats();
        $peak = $this->peak(Reservation::query(), $start, $end, $ignore);

        if ($peak + $party > $total) {
            throw ValidationException::withMessages(['reserved_at' => __('reservation.full', ['left' => max(0, $total - $peak)])]);
        }

        if ($area === null) {
            $largest = $this->largestAreaLeft($start, $end, $total - $peak, $ignore);

            if ($largest < $party) {
                throw ValidationException::withMessages(['reserved_at' => __('reservation.split', ['left' => $total - $peak, 'party' => $party])]);
            }

            return;
        }

        $peak = $this->peak(Reservation::where('area_id', $area->getKey()), $start, $end, $ignore);

        if ($peak + $party > $area->capacity) {
            throw ValidationException::withMessages(['reserved_at' => __('reservation.area_full', [
                'area' => $area->name,
                'left' => max(0, $area->capacity - $peak),
            ])]);
        }
    }

    /**
     * Số khách cao nhất cùng lúc trong khoảng này: chỉ cần xét lúc mở đầu và lúc
     * mỗi bàn khác vào. Mỗi bàn giữ chỗ theo thời lượng lượt bàn đang cài, nên
     * đổi thời lượng thì các đặt bàn đã có cũng tính lại theo.
     */
    private function peak(Builder $query, CarbonImmutable $start, CarbonImmutable $end, ?Reservation $ignore = null, ?array $statuses = null): int
    {
        $turn = $this->turn();
        $overlapping = $query
            ->whereIn('status', $statuses ?? ReservationStatus::holding())
            ->where('reserved_at', '<', $end)
            ->where('reserved_at', '>', $start->subMinutes($turn))
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->get(['reserved_at', 'party_size']);

        $moments = $overlapping->pluck('reserved_at')->filter(fn ($moment) => $moment > $start)->push($start);

        return (int) $moments->map(fn ($moment) => $overlapping
            ->filter(fn ($other) => $other->reserved_at <= $moment && $other->reserved_at->copy()->addMinutes($turn) > $moment)
            ->sum('party_size'))->max();
    }

    /** Quy định chỉ áp cho khách đặt online; nhân viên nhập tay thì không bị chặn. */
    private function assertOnlineAllowed(CarbonImmutable $start, CarbonImmutable $end, int $party): void
    {
        $settings = $this->settings();
        $today = CarbonImmutable::today();

        $error = match (true) {
            ! $settings['online_enabled'] => 'reservation.online_off',
            $start->lt(CarbonImmutable::now()) => 'reservation.too_soon',
            $start->startOfDay()->lt($today->addDays((int) $settings['min_notice_days'])) => 'reservation.too_soon',
            $start->startOfDay()->gt($today->addDays((int) $settings['max_advance_days'])) => 'reservation.too_far',
            ! tenant()->isOpenBetween($start, $end) => 'reservation.closed',
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['reserved_at' => __($error)]);
        }

        $limit = self::onlineSeats($settings);
        // Hạn mức online đếm cả lượt đang chờ, để web không nhận quá số yêu cầu cho phép.
        $peak = $this->peak(Reservation::where('source', ReservationSource::Online), $start, $end, null, ReservationStatus::active());

        if ($peak + $party > $limit) {
            throw ValidationException::withMessages(['reserved_at' => __('reservation.online_full', ['left' => max(0, $limit - $peak)])]);
        }
    }

    /** Số chỗ cho đặt online, không vượt tổng chỗ ngồi hiện có. */
    public static function onlineSeats(array $settings): int
    {
        $total = Area::totalSeats();

        return min($total, (int) ($settings['online_seats'] ?? $total));
    }

    private function log(Reservation $reservation, ?ReservationStatus $from, ReservationStatus $to, ?User $user): void
    {
        ReservationStatusLog::create([
            'reservation_id' => $reservation->getKey(),
            'user_id' => $user?->getKey(),
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }

    /** Email gửi sau khi trả trang. Zalo thêm ở đây khi có tài khoản ZBS. */
    private function notify(Reservation $reservation): void
    {
        $email = $reservation->customer->email;

        if (! $email || ! in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::Rejected], true)) {
            return;
        }

        $restaurantName = tenant()->name;

        defer(fn () => Notification::route('mail', $email)->notify(
            (new ReservationStatusNotification($reservation, $restaurantName))->locale($reservation->locale),
        ));
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return $this->modules->settings(tenant(), 'reservation');
    }
}

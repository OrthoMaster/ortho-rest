<?php

namespace App\Modules\Reservation;

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
 * Mọi thao tác ghi đặt bàn đi qua đây. Hàng của khu vực bị khoá trong
 * transaction, nên hai người đặt cùng lúc vào một khu vực phải xếp hàng và
 * người sau thấy chỗ người trước đã giữ.
 */
class ReservationBooking
{
    public function __construct(private ModuleManager $modules) {}

    /**
     * @param  array{area_id: int, reserved_at: mixed, party_size: int, customer_name: string, customer_phone: string, customer_email?: ?string, notes?: ?string, locale?: string}  $data
     */
    public function create(array $data, ReservationSource $source, ?User $user = null): Reservation
    {
        $reservation = DB::transaction(function () use ($data, $source, $user): Reservation {
            $online = $source === ReservationSource::Online;

            // Số chỗ online tính chung cả quán: khoá hàng của quán trước (cùng thứ tự với đơn mang đi).
            if ($online) {
                Restaurant::whereKey(tenant()->getKey())->lockForUpdate()->first();
            }

            $area = $this->lockArea($data['area_id']);
            $start = CarbonImmutable::parse($data['reserved_at']);
            $end = $this->endOf($start);

            if ($online) {
                $this->assertOnlineAllowed($start, $end, (int) $data['party_size']);
            }

            $this->assertSeats($area, $start, $end, (int) $data['party_size']);

            $status = $online && $this->settings()['auto_confirm'] ? ReservationStatus::Confirmed : $source->initialStatus();

            $reservation = Reservation::create([
                'customer_id' => Customer::remember($data['customer_name'], $data['customer_phone'], $data['customer_email'] ?? null)->getKey(),
                'area_id' => $area->getKey(),
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

    /** @param array{area_id: int, reserved_at: mixed, party_size: int, customer_name: string, customer_phone: string, customer_email?: ?string, notes?: ?string} $data */
    public function update(Reservation $reservation, array $data): Reservation
    {
        return DB::transaction(function () use ($reservation, $data): Reservation {
            $area = $this->lockArea($data['area_id']);
            $reservation = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! $reservation->status->holdsSeats()) {
                throw ValidationException::withMessages(['reserved_at' => __('reservation.not_editable')]);
            }

            $start = CarbonImmutable::parse($data['reserved_at']);
            $end = $this->endOf($start);

            $this->assertSeats($area, $start, $end, (int) $data['party_size'], $reservation);

            $reservation->update([
                'customer_id' => Customer::remember($data['customer_name'], $data['customer_phone'], $data['customer_email'] ?? null)->getKey(),
                'area_id' => $area->getKey(),
                'reserved_at' => $start,
                'ends_at' => $end,
                'party_size' => $data['party_size'],
                'notes' => $data['notes'] ?? null,
            ]);

            return $reservation;
        });
    }

    public function changeStatus(Reservation $reservation, ReservationStatus $status, ?User $user = null): Reservation
    {
        $reservation = DB::transaction(function () use ($reservation, $status, $user): Reservation {
            $reservation = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            $from = $reservation->status;

            if (! $from->canBecome($status)) {
                throw ValidationException::withMessages(['status' => __('reservation.bad_transition')]);
            }

            $reservation->update(['status' => $status]);
            $this->log($reservation, $from, $status, $user);

            return $reservation;
        });

        $this->notify($reservation);

        return $reservation;
    }

    private function lockArea(int $id): Area
    {
        return Area::whereKey($id)->where('is_active', true)->lockForUpdate()->firstOr(
            fn () => throw ValidationException::withMessages(['area_id' => __('reservation.area_unavailable')]),
        );
    }

    private function endOf(CarbonImmutable $start): CarbonImmutable
    {
        return $start->addMinutes((int) $this->settings()['turn_minutes']);
    }

    private function assertSeats(Area $area, CarbonImmutable $start, CarbonImmutable $end, int $party, ?Reservation $ignore = null): void
    {
        $peak = $this->peak(Reservation::where('area_id', $area->getKey()), $start, $end, $ignore);

        if ($peak + $party > $area->capacity) {
            throw ValidationException::withMessages(['reserved_at' => __('reservation.area_full', [
                'area' => $area->name,
                'left' => max(0, $area->capacity - $peak),
            ])]);
        }
    }

    /** Số khách cao nhất cùng lúc trong khoảng này: chỉ cần xét lúc mở đầu và lúc mỗi bàn khác vào. */
    private function peak(Builder $query, CarbonImmutable $start, CarbonImmutable $end, ?Reservation $ignore = null): int
    {
        $overlapping = $query
            ->whereIn('status', ReservationStatus::active())
            ->where('reserved_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->get(['reserved_at', 'ends_at', 'party_size']);

        $moments = $overlapping->pluck('reserved_at')->filter(fn ($moment) => $moment > $start)->push($start);

        return (int) $moments->map(fn ($moment) => $overlapping
            ->filter(fn ($other) => $other->reserved_at <= $moment && $other->ends_at > $moment)
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
        $peak = $this->peak(Reservation::where('source', ReservationSource::Online), $start, $end);

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

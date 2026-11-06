<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ActivityController extends Controller
{
    /** Xem tối đa chừng này ngày một lần, để không tải quá nhiều dòng. */
    public const MAX_DAYS = 31;

    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $area = in_array($request->query('area'), ActivityLog::AREAS, true) ? $request->query('area') : null;
        $users = tenant()->users()->orderBy('name')->get();
        $userId = $users->contains('id', $request->integer('user')) ? $request->integer('user') : null;
        [$from, $to] = $this->range($request);

        $logs = ActivityLog::with(['user', 'impersonator'])
            ->whereBetween('created_at', [$from, $to->endOfDay()])
            ->when($area, fn ($query) => $query->where('area', $area))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.activity', [
            'logs' => $logs,
            'area' => $area,
            'users' => $users,
            'userId' => $userId,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} mặc định hôm nay, dài quá thì cắt bớt ngày đầu */
    private function range(Request $request): array
    {
        $parse = function (string $key) use ($request): ?CarbonImmutable {
            try {
                return $request->filled($key) ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query($key)) : null;
            } catch (Throwable) {
                return null;
            }
        };

        $to = $parse('to') ?? $parse('from') ?? CarbonImmutable::today();
        $from = $parse('from') ?? $to;

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }

        return [$from, $to];
    }
}

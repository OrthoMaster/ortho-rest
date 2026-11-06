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
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $area = in_array($request->query('area'), ActivityLog::AREAS, true) ? $request->query('area') : null;
        $users = tenant()->users()->orderBy('name')->get();
        $userId = $users->contains('id', $request->integer('user')) ? $request->integer('user') : null;

        try {
            $date = $request->filled('date') ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('date')) : null;
        } catch (Throwable) {
            $date = null;
        }

        $logs = ActivityLog::with(['user', 'impersonator'])
            ->when($date, fn ($query) => $query->whereBetween('created_at', [$date, $date->endOfDay()]))
            ->when($area, fn ($query) => $query->where('area', $area))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.activity', ['logs' => $logs, 'area' => $area, 'users' => $users, 'userId' => $userId, 'date' => $date ?: null]);
    }
}

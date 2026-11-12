<?php

namespace App\Modules\Schedule\Http;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use App\Modules\Schedule\LeaveBook;
use App\Modules\Schedule\LeaveStatus;
use App\Modules\Schedule\Models\LeaveRequest;
use App\Modules\Schedule\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Đơn xin nghỉ. Ai cũng gửi được đơn cho mình; người có quyền duyệt thấy mọi đơn,
 * nhập đơn thay, duyệt có lương (trừ phép) hoặc không lương, hoặc từ chối.
 */
class LeaveController extends Controller
{
    /**
     * Đơn xin nghỉ (người duyệt): lịch tuần nhỏ đếm số người nghỉ theo ca và bộ phận
     * (đã duyệt, chờ duyệt) để biết còn đủ người không, và danh sách đơn.
     * Người không có quyền duyệt chuyển sang Nghỉ phép (phần của mình).
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $request->user()->can('schedule.leave')) {
            return redirect()->route('tenant.admin.schedule.leave.mine');
        }

        $filter = $request->query('status') === 'all' ? 'all' : 'pending';

        // Lọc theo một ngày: đơn có ngày nghỉ trùng ngày đó (chọn ở ô ngày hoặc bấm cột trên lịch).
        try {
            $day = $request->filled('day') ? CarbonImmutable::parse((string) $request->query('day'))->startOfDay() : null;
        } catch (\Throwable) {
            $day = null;
        }

        $leaves = LeaveRequest::with(['user.department', 'creator', 'decider'])
            ->when($filter === 'pending', fn ($query) => $query->where('status', LeaveStatus::Pending))
            ->when($day, fn ($query) => $query->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day))
            ->orderByRaw('status = ? desc', [LeaveStatus::Pending->value])
            ->orderByDesc('starts_on')
            ->limit(200)
            ->get();

        $pending = $leaves->where('status', LeaveStatus::Pending);

        return view('modules.schedule.leave.index', [
            'leaves' => $leaves,
            'approver' => true,
            'filter' => $filter,
            'filterDay' => $day,
            ...$this->calendar($request),
            // Cho hộp thoại duyệt: phép còn lại và số ô đang xếp lịch của từng đơn chờ.
            'balances' => $pending->mapWithKeys(fn ($leave) => [$leave->id => LeaveBook::balance($leave->user)]),
            'conflicts' => $pending->mapWithKeys(fn ($leave) => [$leave->id => LeaveBook::scheduled($leave)->count()]),
        ]);
    }

    /**
     * Nghỉ phép: phép còn lại, lịch sử phép và các đơn của chính mình.
     * Người duyệt chọn được người khác (?user=) để xem và chỉnh phép còn lại.
     */
    public function mine(Request $request): View
    {
        $viewer = $request->user();
        $approver = $viewer->can('schedule.leave');
        $isOwner = $viewer->role === UserRole::Owner;

        $users = $approver ? $this->takers($request) : collect();

        if ($approver && $request->filled('user')) {
            $user = $users->get((int) $request->query('user')) ?? abort(404);
        } else {
            // Chủ quán mở Nghỉ phép thì xem người đầu danh sách.
            $user = $isOwner && $approver ? $users->first() : $viewer;
        }

        // Bên phải: đơn đang chờ (nếu có) ở trên, dưới là Lịch sử phép hoặc Các đơn đã xin, có trang.
        $view = $request->query('view') === 'requests' ? 'requests' : 'history';
        $data = ['person' => $user, 'approver' => $approver, 'users' => $users, 'view' => $view, 'balance' => null, 'pending' => collect(), 'list' => null, 'hasData' => false, 'from' => null, 'to' => null];

        if ($user === null) {
            return view('modules.schedule.leave.mine', $data);
        }

        $data['balance'] = LeaveBook::balance($user);

        // Lọc theo khoảng ngày (để tra cứu khi có nhiều đơn).
        try {
            $from = $request->filled('from') ? CarbonImmutable::parse((string) $request->query('from'))->startOfDay() : null;
            $to = $request->filled('to') ? CarbonImmutable::parse((string) $request->query('to'))->endOfDay() : null;
        } catch (\Throwable) {
            $from = $to = null;
        }
        $data['from'] = $from;
        $data['to'] = $to;
        $data['pending'] = LeaveRequest::with(['decider', 'user', 'creator'])->where('user_id', $user->id)->where('status', LeaveStatus::Pending)->orderBy('starts_on')->get();

        if ($view === 'requests') {
            $query = LeaveRequest::with(['decider', 'user', 'creator'])->where('user_id', $user->id)->where('status', '!=', LeaveStatus::Pending);
            $data['hasData'] = (clone $query)->exists();
            $data['list'] = $query
                ->when($from, fn ($query) => $query->whereDate('ends_on', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('starts_on', '<=', $to))
                ->orderByDesc('starts_on')->paginate(15)->withQueryString();
        } else {
            $history = collect(LeaveBook::history($user));
            $data['hasData'] = $history->isNotEmpty();
            $history = $history->filter(fn ($entry) => (! $from || $entry['at'] >= $from) && (! $to || $entry['at'] <= $to));
            $page = LengthAwarePaginator::resolveCurrentPage();
            $data['list'] = (new LengthAwarePaginator($history->forPage($page, 15)->values(), $history->count(), 15, $page, ['path' => $request->url()]))->withQueryString();
        }

        return view('modules.schedule.leave.mine', $data);
    }

    /**
     * Người có phép và xin nghỉ được: nhân viên đang làm (kể cả người đang xem nếu không phải chủ quán).
     * Chủ quán không có phép nên không nằm trong danh sách.
     *
     * @return Collection<int, User>
     */
    private function takers(Request $request): Collection
    {
        $users = Roster::users()->reject(fn ($user) => $user->role === UserRole::Owner);

        if ($request->user()->role !== UserRole::Owner) {
            $users->put($request->user()->id, $request->user());
        }

        return $users->sortBy('name');
    }

    /** Chỉnh phép còn lại của một người thành số mới, kèm lý do; ghi lịch sử và nhật ký. */
    public function adjust(Request $request, int $user): RedirectResponse
    {
        $person = tenant()->users()->findOrFail($user);
        abort_if($person->role === UserRole::Owner || LeaveBook::balance($person) === null, 422);

        $data = $request->validateWithBag('adjust', [
            'left' => ['required', 'numeric', 'between:-365,365'],
            'reason' => ['required', 'string', 'max:300'],
        ]);

        $adjustment = LeaveBook::adjust($person, (float) $data['left'], $data['reason'], $request->user());
        $back = redirect()->route('tenant.admin.schedule.leave.mine', $person->is($request->user()) ? [] : ['user' => $person->id]);

        if ($adjustment === null) {
            return $back->with('status', __('leave.adjust_same'));
        }

        ActivityLog::record(tenant()->getKey(), 'schedule', __('leave.log_adjusted', [
            'name' => $person->name, 'from' => LeaveBook::format($adjustment->before), 'to' => LeaveBook::format($adjustment->after), 'reason' => $adjustment->reason,
        ]));

        return $back->with('status', __('leave.adjusted', ['name' => $person->name, 'days' => LeaveBook::format($adjustment->after)]));
    }

    /**
     * Lịch tuần nhỏ: mỗi ngày, mỗi ca, mỗi bộ phận có bao nhiêu người nghỉ đã duyệt và chờ duyệt.
     *
     * @return array<string, mixed>
     */
    private function calendar(Request $request): array
    {
        try {
            // Đang lọc một ngày thì lịch mở ở tuần của ngày đó.
            $date = CarbonImmutable::parse((string) $request->query('date', $request->query('day', 'today')));
        } catch (\Throwable) {
            $date = CarbonImmutable::today();
        }

        $days = Roster::week($date);
        $shifts = Roster::shiftsByWeekday();
        $users = Roster::users();
        $leaves = LeaveRequest::with('user')->activeBetween($days[0], $days[6])->get();
        $slots = [];
        $counts = [];

        foreach ($days as $day) {
            $key = $day->toDateString();
            $slots[$key] = Roster::slots($day, $shifts);

            foreach ($slots[$key] as $slot) {
                foreach ($leaves as $leave) {
                    if ($leave->covers($day, $slot['key'])) {
                        $status = $leave->status === LeaveStatus::Approved ? 'approved' : 'pending';
                        $counts[$key][$slot['key']][$leave->user->department_id ?? 0][$status] ??= 0;
                        $counts[$key][$slot['key']][$leave->user->department_id ?? 0][$status]++;
                    }
                }
            }
        }

        // Hàng là bộ phận (kèm số người); người chưa có bộ phận gom một hàng cuối.
        $groups = Department::orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Department $department) => ['id' => $department->id, 'name' => $department->name, 'tone' => $department->tone, 'people' => $users->where('department_id', $department->id)->count()]);
        if ($users->whereNull('department_id')->isNotEmpty() || $leaves->contains(fn ($leave) => $leave->user->department_id === null)) {
            $groups->push(['id' => 0, 'name' => __('staff.no_department'), 'tone' => 0, 'people' => $users->whereNull('department_id')->count()]);
        }

        return ['date' => $days[0], 'days' => $days, 'calendarSlots' => $slots, 'counts' => $counts, 'groups' => $groups];
    }

    public function create(Request $request): View
    {
        $approver = $request->user()->can('schedule.leave');

        return view('modules.schedule.leave.form', [
            'approver' => $approver,
            'users' => $approver ? $this->takers($request) : collect(),
            'shifts' => Roster::shiftsByWeekday(),
            'balance' => LeaveBook::balance($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $viewer = $request->user();
        $approver = $viewer->can('schedule.leave');

        $data = $request->validate([
            'user_id' => [$viewer->role === UserRole::Owner ? 'required' : 'nullable', 'integer', Rule::in($approver ? $this->takers($request)->keys()->all() : [$viewer->id])],
            // Nhân viên chỉ xin nghỉ từ hôm nay; người duyệt nhập thay được cả ngày đã qua.
            'starts_on' => ['required', 'date', ...($approver ? [] : ['after_or_equal:today'])],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'whole_day' => ['boolean'],
            'shifts' => ['array'],
            'shifts.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:500'],
            'action' => ['nullable', Rule::in(['send', 'approve'])],
            'is_paid' => ['boolean'],
        ]);

        $userId = (int) ($data['user_id'] ?? $viewer->id);
        $from = CarbonImmutable::parse($data['starts_on'])->startOfDay();
        $to = CarbonImmutable::parse($data['ends_on'])->startOfDay();
        $shiftIds = null;

        if ($from->diffInDays($to) > 30) {
            throw ValidationException::withMessages(['ends_on' => __('leave.range_too_long')]);
        }

        if (! $request->boolean('whole_day')) {
            if (! $from->equalTo($to)) {
                throw ValidationException::withMessages(['ends_on' => __('leave.range_one_day')]);
            }

            $valid = Roster::shiftsByWeekday()[$from->dayOfWeekIso]->pluck('id')->all();
            $shiftIds = array_values(array_intersect($valid, array_map('intval', $data['shifts'] ?? [])));

            if ($shiftIds === []) {
                throw ValidationException::withMessages(['shifts' => __('leave.pick_shift')]);
            }
        }

        $overlap = LeaveRequest::where('user_id', $userId)->activeBetween($from, $to)->get()->contains(function (LeaveRequest $other) use ($shiftIds) {
            return $shiftIds === null || $other->isWholeDay() || array_intersect($shiftIds, $other->shift_ids) !== [];
        });

        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => __('leave.overlap')]);
        }

        // Người duyệt bấm Duyệt: tạo đơn đã duyệt luôn, bỏ người đó khỏi các ô lịch trùng ngày nghỉ.
        $approveNow = $approver && ($data['action'] ?? null) === 'approve';

        $leave = DB::transaction(function () use ($userId, $from, $to, $shiftIds, $data, $viewer, $approveNow, $request): LeaveRequest {
            $leave = LeaveRequest::create([
                'user_id' => $userId,
                'starts_on' => $from->toDateString(),
                'ends_on' => $to->toDateString(),
                'shift_ids' => $shiftIds,
                'days' => LeaveBook::days($from, $to, $shiftIds),
                'reason' => filled($data['reason'] ?? null) ? trim($data['reason']) : null,
                'status' => $approveNow ? LeaveStatus::Approved : LeaveStatus::Pending,
                'is_paid' => $approveNow ? $request->boolean('is_paid') : null,
                'decided_by' => $approveNow ? $viewer->id : null,
                'decided_at' => $approveNow ? now() : null,
                'created_by' => $viewer->id,
            ]);

            if ($approveNow) {
                LeaveBook::scheduled($leave)->each->delete();
            }

            return $leave;
        });

        $name = $leave->user->name;
        ActivityLog::record(tenant()->getKey(), 'schedule', __('leave.log_created', ['name' => $name, 'period' => $leave->periodText()]));

        if ($approveNow) {
            ActivityLog::record(tenant()->getKey(), 'schedule', __('leave.log_approved', ['name' => $name, 'period' => $leave->periodText(), 'paid' => mb_strtolower(__($leave->is_paid ? 'leave.paid' : 'leave.unpaid'))]));

            return redirect()->route('tenant.admin.schedule.leave.index')->with('status', __('leave.approved', ['name' => $name]));
        }

        return redirect()->route($approver ? 'tenant.admin.schedule.leave.index' : 'tenant.admin.schedule.leave.mine')->with('status', __('leave.sent'));
    }

    /** Duyệt (có lương hoặc không) hoặc từ chối. Duyệt thì bỏ người đó khỏi các ô lịch trùng ngày nghỉ. */
    public function decide(Request $request, LeaveRequest $leave): RedirectResponse
    {
        abort_unless($leave->status === LeaveStatus::Pending, 422);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'is_paid' => ['boolean'],
            'decision_note' => ['nullable', 'string', 'max:300'],
        ]);
        $approve = $data['action'] === 'approve';

        DB::transaction(function () use ($request, $leave, $data, $approve): void {
            $leave->update([
                'status' => $approve ? LeaveStatus::Approved : LeaveStatus::Rejected,
                'is_paid' => $approve ? $request->boolean('is_paid') : null,
                'decision_note' => filled($data['decision_note'] ?? null) ? trim($data['decision_note']) : null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            if ($approve) {
                LeaveBook::scheduled($leave)->each->delete();
            }
        });

        $name = $leave->user->name;
        ActivityLog::record(tenant()->getKey(), 'schedule', $approve
            ? __('leave.log_approved', ['name' => $name, 'period' => $leave->periodText(), 'paid' => mb_strtolower(__($leave->is_paid ? 'leave.paid' : 'leave.unpaid'))])
            : __('leave.log_rejected', ['name' => $name, 'period' => $leave->periodText()]));

        return back()->with('status', __($approve ? 'leave.approved' : 'leave.rejected', ['name' => $name]));
    }

    /** Người gửi huỷ đơn đang chờ; người duyệt huỷ được cả đơn đã duyệt (phép được trả lại). */
    public function cancel(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $user = $request->user();
        $allowed = $user->can('schedule.leave')
            ? in_array($leave->status, [LeaveStatus::Pending, LeaveStatus::Approved], true)
            : $leave->user_id === $user->id && $leave->status === LeaveStatus::Pending;
        abort_unless($allowed, 403);

        $leave->update(['status' => LeaveStatus::Cancelled]);

        return back()->with('status', __('leave.cancelled'));
    }
}

<?php

namespace App\Modules\Schedule\Http;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\Schedule\LeaveBook;
use App\Modules\Schedule\LeaveStatus;
use App\Modules\Schedule\Models\LeaveRequest;
use App\Modules\Schedule\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request): View
    {
        $user = $request->user();
        $approver = $user->can('schedule.leave');
        $filter = $approver && $request->query('status') !== 'all' ? 'pending' : 'all';

        $leaves = LeaveRequest::with(['user.department', 'creator', 'decider'])
            ->when(! $approver, fn ($query) => $query->where('user_id', $user->id))
            ->when($filter === 'pending', fn ($query) => $query->where('status', LeaveStatus::Pending))
            ->orderByRaw('status = ? desc', [LeaveStatus::Pending->value])
            ->orderByDesc('starts_on')
            ->limit(200)
            ->get();

        $pending = $leaves->where('status', LeaveStatus::Pending);

        return view('modules.schedule.leave.index', [
            'leaves' => $leaves,
            'approver' => $approver,
            'filter' => $filter,
            'balance' => LeaveBook::balance($user),
            // Cho hộp thoại duyệt: phép còn lại và số ô đang xếp lịch của từng đơn chờ.
            'balances' => $approver ? $pending->mapWithKeys(fn ($leave) => [$leave->id => LeaveBook::balance($leave->user)]) : collect(),
            'conflicts' => $approver ? $pending->mapWithKeys(fn ($leave) => [$leave->id => LeaveBook::scheduled($leave)->count()]) : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        $approver = $request->user()->can('schedule.leave');

        return view('modules.schedule.leave.form', [
            'approver' => $approver,
            'users' => $approver ? Roster::users() : collect(),
            'shifts' => Roster::shiftsByWeekday(),
            'balance' => LeaveBook::balance($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $viewer = $request->user();
        $approver = $viewer->can('schedule.leave');

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::in($approver ? Roster::users()->keys()->all() : [$viewer->id])],
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

        return redirect()->route('tenant.admin.schedule.leave.index')->with('status', __('leave.sent'));
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

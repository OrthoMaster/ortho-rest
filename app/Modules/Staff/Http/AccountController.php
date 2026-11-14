<?php

namespace App\Modules\Staff\Http;

use App\Auth\DeviceGuard;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Support\Activity\ActivityFormat;
use App\Support\Permissions;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Tài khoản nhân viên của quán. Chủ quán sửa thông tin của mình ở trang Tài khoản. */
class AccountController extends Controller
{
    public function __construct(private DeviceGuard $devices) {}

    public function index(): View
    {
        $accounts = tenant()->users()->with('positions', 'department')
            ->orderByRaw('role = ? desc', [UserRole::Owner->value])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('modules.staff.accounts', ['accounts' => $accounts]);
    }

    public function create(): View
    {
        return $this->form(new User);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $account = new User(['name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'], 'password' => $data['password']]);
        $account->restaurant_id = tenant()->getKey();
        $account->role = UserRole::Staff;
        $account->permissions = $data['permissions'] ?: null;
        $account->forceFill($data['contract']);
        $account->save();
        $account->positions()->sync($data['positions']);

        ActivityLog::record(tenant()->getKey(), 'staff', __('staff.log_created', ['name' => $account->name]), [
            __('activity.fields.positions').': '.$this->positionNames($data['positions']),
            ...ActivityFormat::permissions([], $data['permissions']),
        ]);

        return redirect()->route('tenant.admin.staff.accounts.index')->with('status', __('staff.account_saved', ['name' => $account->name]));
    }

    public function edit(int $account): View
    {
        return $this->form($this->find($account));
    }

    public function update(Request $request, int $account): RedirectResponse
    {
        $account = $this->find($account);
        $data = $this->validated($request, $account);
        $before = ['name' => $account->name, 'phone' => $account->phone, 'email' => $account->email, 'positions' => $account->positions->pluck('id')->sort()->values()->all(), 'permissions' => $account->permissions ?? []];
        $contractBefore = $this->contractText($account);

        $account->fill(['name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email']]);
        $account->forceFill($data['contract']);

        // Quyền riêng của module đang tắt không hiện trên form: giữ nguyên.
        $hidden = array_values(array_diff($before['permissions'], Permissions::visibleKeys(tenant())));
        $account->permissions = array_values(array_unique([...$data['permissions'], ...$hidden])) ?: null;

        if (filled($data['password'])) {
            $account->password = $data['password'];
        }

        $account->save();
        $account->positions()->sync($data['positions']);

        $lines = [];

        foreach (['name', 'phone', 'email'] as $field) {
            if ($before[$field] !== $account->{$field}) {
                $lines[] = __('activity.change', ['field' => __('activity.fields.'.$field), 'from' => $before[$field] ?: __('activity.empty'), 'to' => $account->{$field} ?: __('activity.empty')]);
            }
        }

        $after = collect($data['positions'])->sort()->values()->all();

        if ($before['positions'] !== $after) {
            $lines[] = __('activity.change', ['field' => __('activity.fields.positions'), 'from' => $this->positionNames($before['positions']), 'to' => $this->positionNames($after)]);
        }

        foreach ($this->contractText($account) as $field => $text) {
            if ($contractBefore[$field] !== $text) {
                $lines[] = __('activity.change', ['field' => __('staff.'.$field), 'from' => $contractBefore[$field], 'to' => $text]);
            }
        }

        $lines = [...$lines, ...ActivityFormat::permissions($before['permissions'], $account->permissions ?? [])];

        // Đặt mật khẩu mới thì đăng xuất mọi thiết bị đang dùng mật khẩu cũ.
        if (filled($data['password'])) {
            $lines[] = __('staff.log_password');
            $this->signOutEverywhere($account);
        }

        // Tắt "Đang làm việc": không đăng nhập được nữa, đăng xuất ngay mọi thiết bị.
        if ($request->has('is_active') && $account->is_active !== $request->boolean('is_active') && ! $account->is($request->user())) {
            $account->forceFill(['is_active' => $request->boolean('is_active')])->save();
            $lines[] = __($account->is_active ? 'staff.log_activated' : 'staff.log_deactivated', ['name' => $account->name]);

            if (! $account->is_active) {
                $this->signOutEverywhere($account);
            }
        }

        if ($lines !== []) {
            ActivityLog::record(tenant()->getKey(), 'staff', __('staff.log_updated', ['name' => $account->name]), $lines);
        }

        return redirect()->route('tenant.admin.staff.accounts.index')->with('status', __('staff.account_saved', ['name' => $account->name]));
    }

    /** Ngừng làm việc: không đăng nhập được nữa, đăng xuất ngay mọi thiết bị. Cho làm lại thì mở lại. */
    public function toggleActive(Request $request, int $account): RedirectResponse
    {
        $account = $this->find($account);
        abort_if($account->is($request->user()), 403);

        $account->is_active = ! $account->is_active;
        $account->save();

        if (! $account->is_active) {
            $this->signOutEverywhere($account);
        }

        $key = $account->is_active ? 'activated' : 'deactivated';
        ActivityLog::record(tenant()->getKey(), 'staff', __('staff.log_'.$key, ['name' => $account->name]));

        return back()->with('status', __('staff.'.$key, ['name' => $account->name]));
    }

    private function find(int $id): User
    {
        // Chủ quán không sửa ở đây.
        return tenant()->users()->where('role', '!=', UserRole::Owner->value)->findOrFail($id);
    }

    private function form(User $account): View
    {
        return view('modules.staff.account-form', [
            'account' => $account,
            'positions' => Position::orderBy('sort_order')->orderBy('id')->get(),
            'departments' => Department::orderBy('sort_order')->orderBy('id')->get(),
            'groups' => Permissions::groupsFor(tenant()),
        ]);
    }

    /** @return array{name: string, phone: string, email: ?string, password: ?string, positions: list<int>, permissions: list<string>, contract: array<string, mixed>} */
    private function validated(Request $request, ?User $account = null): array
    {
        $request->merge(['phone' => filled($request->input('phone')) ? Phone::normalize((string) $request->input('phone')) : null]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', Phone::RULE, Rule::unique('users')->where('restaurant_id', tenant()->getKey())->ignore($account)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->where('restaurant_id', tenant()->getKey())->ignore($account)],
            'password' => [$account ? 'nullable' : 'required', 'string', Password::min(8)],
            'positions' => ['array'],
            'positions.*' => ['integer', Rule::exists('positions', 'id')->where('restaurant_id', tenant()->getKey())],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::visibleKeys(tenant()))],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('restaurant_id', tenant()->getKey())],
            'started_on' => ['nullable', 'date', 'before_or_equal:'.today()->addYear()->toDateString()],
            'paid_leave_per_month' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'contract_hours' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'contract_period' => ['nullable', Rule::in(['week', 'month'])],
        ]);

        return [
            'name' => trim($data['name']),
            'phone' => $data['phone'],
            'email' => filled($data['email'] ?? null) ? Str::lower($data['email']) : null,
            'password' => $data['password'] ?? null,
            'positions' => array_values(array_unique(array_map('intval', $data['positions'] ?? []))),
            'permissions' => array_values(array_unique($data['permissions'] ?? [])),
            'contract' => [
                'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
                'started_on' => $data['started_on'] ?? null,
                'paid_leave_per_month' => isset($data['paid_leave_per_month']) ? round((float) $data['paid_leave_per_month'], 2) : null,
                'contract_hours' => isset($data['contract_hours']) ? round((float) $data['contract_hours'], 1) : null,
                'contract_period' => isset($data['contract_hours']) ? ($data['contract_period'] ?? 'week') : null,
            ],
        ];
    }

    /** @return array<string, string> thông tin hợp đồng dạng chữ, để ghi nhật ký */
    private function contractText(User $account): array
    {
        $empty = __('activity.empty');

        return [
            'department' => Department::find($account->department_id)?->name ?? $empty,
            'started_on' => $account->started_on ? Carbon::parse($account->started_on)->format('d/m/Y') : $empty,
            'paid_leave_per_month' => $account->paid_leave_per_month !== null ? number_format((float) $account->paid_leave_per_month, 2, ',', '.') : $empty,
            'contract_hours' => $account->contract_hours !== null
                ? __('staff.hours_per.'.($account->contract_period ?? 'week'), ['hours' => rtrim(rtrim(number_format((float) $account->contract_hours, 1, ',', '.'), '0'), ',')])
                : $empty,
        ];
    }

    private function positionNames(array $ids): string
    {
        $names = Position::whereIn('id', $ids)->orderBy('sort_order')->pluck('name')->implode(', ');

        return $names !== '' ? $names : __('activity.empty');
    }

    private function signOutEverywhere(User $account): void
    {
        foreach ($account->devices as $device) {
            $this->devices->revoke($device);
        }

        $account->forceFill(['remember_token' => Str::random(60)])->save();
    }
}

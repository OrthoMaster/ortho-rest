<?php

namespace App\Modules\Schedule\Http;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Modules\DineIn\Models\Station;
use App\Modules\ModuleManager;
use App\Modules\Reservation\Models\Area;
use App\Modules\Schedule\Models\Task;
use App\Modules\Schedule\Roster;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cài đặt lịch: bộ phận của từng khu, việc trong ca của từng khu (hoặc việc chung),
 * và số giờ một ngày công.
 */
class SettingsController extends Controller
{
    public function index(): View
    {
        $tasks = Task::orderBy('sort_order')->orderBy('id')->get()->groupBy(fn (Task $task) => $task->targetKey());

        return view('modules.schedule.settings', [
            'date' => today()->toImmutable(),
            'stations' => has_module('dinein') ? Station::orderBy('sort_order')->orderBy('id')->get() : collect(),
            'areas' => Area::where('is_active', true)->whereNotNull('code')->orderBy('sort_order')->orderBy('id')->get(),
            'departments' => Department::orderBy('sort_order')->orderBy('id')->get(),
            'tasks' => $tasks,
            'workdayHours' => Roster::workdayMinutes() / 60,
            'includeOwner' => Roster::includesOwner(),
        ]);
    }

    public function update(Request $request, ModuleManager $modules): RedirectResponse
    {
        $restaurantId = tenant()->getKey();
        $data = $request->validate([
            'workday_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'include_owner' => ['boolean'],
            'departments' => ['array'],
            'departments.*' => ['array'],
            'departments.*.*' => ['integer', Rule::exists('departments', 'id')->where('restaurant_id', $restaurantId)],
        ]);

        $modules->updateSettings(tenant(), 'schedule', [
            'workday_hours' => round((float) $data['workday_hours'], 1),
            'include_owner' => $request->boolean('include_owner'),
        ]);

        // Khu không có trong form (không chọn bộ phận nào) thì về "bộ phận nào cũng được".
        foreach (Roster::targets()->except('g')->keys() as $key) {
            $ids = array_values(array_unique(array_map('intval', $data['departments'][$key] ?? [])));
            $model = match (true) {
                str_starts_with($key, 's') && has_module('dinein') => Station::find((int) substr($key, 1)),
                str_starts_with($key, 'a') => Area::find((int) substr($key, 1)),
                default => null,
            };
            $model?->update(['department_ids' => $ids ?: null]);
        }

        return back()->with('status', __('schedule.settings_saved'));
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $data = $this->validatedTask($request, 'task-new');

        Task::create([...$data, 'sort_order' => SortOrder::next(Task::query())]);

        return back()->with('status', __('schedule.task_saved'));
    }

    public function updateTask(Request $request, Task $task): RedirectResponse
    {
        $task->update(['name' => $this->validatedTask($request, 'task-'.$task->id, $task)['name']]);

        return back()->with('status', __('schedule.task_saved'));
    }

    public function reorderTasks(Request $request): Response
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        SortOrder::reorder(Task::query(), array_map('intval', $ids));

        return response()->noContent();
    }

    /** Xoá việc thì phân công vào việc đó cũng bỏ. */
    public function destroyTask(Task $task): RedirectResponse
    {
        $task->delete();

        return back()->with('status', __('schedule.task_deleted'));
    }

    /** @return array{name: string, dinein_station_id: ?int, area_id: ?int} */
    private function validatedTask(Request $request, string $bag, ?Task $task = null): array
    {
        $data = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:60'],
            'target' => [$task ? 'nullable' : 'required', 'string', 'max:12'],
        ]);
        $target = $task?->targetKey() ?? $data['target'];

        $columns = Roster::laneColumns($target);
        $valid = match (true) {
            $target === Roster::GENERAL => true,
            $columns['dinein_station_id'] !== null => has_module('dinein') && Station::whereKey($columns['dinein_station_id'])->exists(),
            $columns['area_id'] !== null => Area::whereKey($columns['area_id'])->exists(),
            default => false,
        };
        abort_unless($valid, 422);

        return ['name' => trim($data['name']), 'dinein_station_id' => $columns['dinein_station_id'], 'area_id' => $columns['area_id']];
    }
}

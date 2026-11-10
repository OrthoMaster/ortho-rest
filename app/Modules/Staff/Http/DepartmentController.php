<?php

namespace App\Modules\Staff\Http;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Bộ phận theo hợp đồng (Bếp, Phục vụ...): mỗi người một bộ phận chính, mỗi bộ phận một màu trên lịch. */
class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('modules.staff.departments', [
            'departments' => Department::withCount(['users' => fn ($query) => $query->where('is_active', true)])->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Department::create([...$this->validated($request, null, 'department-new'), 'sort_order' => SortOrder::next(Department::query())]);

        return back()->with('status', __('staff.department_saved'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validated($request, $department, 'department-'.$department->id));

        return back()->with('status', __('staff.department_saved'));
    }

    public function reorder(Request $request): Response
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        SortOrder::reorder(Department::query(), array_map('intval', $ids));

        return response()->noContent();
    }

    /** Người thuộc bộ phận bị xoá thì về "chưa có bộ phận". */
    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return back()->with('status', __('staff.department_deleted'));
    }

    /** @return array{name: string, tone: int} */
    private function validated(Request $request, ?Department $department, string $bag): array
    {
        $data = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:60', Rule::unique('departments')->where('restaurant_id', tenant()->getKey())->ignore($department)],
            'tone' => ['nullable', 'integer', 'between:1,'.Department::TONES],
        ], ['name.unique' => __('staff.department_taken')]);

        return ['name' => trim($data['name']), 'tone' => (int) ($data['tone'] ?? $department?->tone ?? Department::nextTone())];
    }
}

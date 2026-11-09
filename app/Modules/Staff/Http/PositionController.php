<?php

namespace App\Modules\Staff\Http;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Support\Permissions;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(): View
    {
        return view('modules.staff.positions', [
            'positions' => Position::withCount('users')->orderBy('sort_order')->orderBy('id')->get(),
            'total' => count(array_merge(...array_values(array_map('array_keys', Permissions::groupsFor(tenant()))))),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Position(['permissions' => []]));
    }

    public function store(Request $request): RedirectResponse
    {
        $position = new Position([...$this->validated($request), 'sort_order' => (int) Position::max('sort_order') + 1]);
        $position->save();

        return redirect()->route('tenant.admin.staff.positions.index')->with('status', __('staff.position_saved', ['name' => $position->name]));
    }

    public function edit(Position $position): View
    {
        return $this->form($position);
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $data = $this->validated($request, $position);

        // Quyền của module đang tắt không hiện trên form: giữ nguyên để bật lại module thì vẫn còn.
        $hidden = array_values(array_diff($position->permissions, $this->visibleKeys()));
        $position->update(['name' => $data['name'], 'permissions' => array_values(array_unique([...$data['permissions'], ...$hidden]))]);

        return redirect()->route('tenant.admin.staff.positions.index')->with('status', __('staff.position_saved', ['name' => $position->name]));
    }

    public function destroy(Position $position): RedirectResponse
    {
        $position->delete();

        return back()->with('status', __('staff.position_deleted', ['name' => $position->name]));
    }

    public function reorder(Request $request): Response
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        SortOrder::reorder(Position::query(), array_map('intval', $ids));

        return response()->noContent();
    }

    private function form(Position $position): View
    {
        return view('modules.staff.position-form', [
            'position' => $position,
            'groups' => Permissions::groupsFor(tenant()),
        ]);
    }

    /** @return array{name: string, permissions: list<string>} */
    private function validated(Request $request, ?Position $position = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('positions')->where('restaurant_id', tenant()->getKey())->ignore($position)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($this->visibleKeys())],
        ]);

        return ['name' => trim($data['name']), 'permissions' => array_values(array_unique($data['permissions'] ?? []))];
    }

    /** @return list<string> */
    private function visibleKeys(): array
    {
        return array_merge(...array_values(array_map('array_keys', Permissions::groupsFor(tenant()))));
    }
}

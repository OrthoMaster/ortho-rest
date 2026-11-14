<?php

namespace App\Http\Controllers\Super;

use App\Enums\RestaurantStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Modules\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RestaurantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $restaurants = Restaurant::query()
            ->with(['domains', 'users' => fn ($query) => $query->where('role', UserRole::Owner)])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%$search%")
                ->orWhere('slug', 'like', "%$search%")))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('super.restaurants.index', compact('restaurants', 'search'));
    }

    public function create(): View
    {
        return view('super.restaurants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            ...$this->slugRules(),
            'owner_name' => ['required', 'string', 'max:255'],
            // Quán mới chưa có ai: email đã dùng ở quán khác vẫn được (mỗi quán một tài khoản riêng).
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_password' => ['required', Password::min(8)],
        ]);

        $restaurant = DB::transaction(function () use ($data): Restaurant {
            $restaurant = Restaurant::create(['name' => $data['name'], 'slug' => $data['slug']]);

            $owner = new User([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $data['owner_password'],
            ]);
            $owner->restaurant_id = $restaurant->getKey();
            $owner->role = UserRole::Owner;
            $owner->save();

            return $restaurant;
        });

        return redirect()->route('super.restaurants.show', $restaurant)->with('status', __('ui.super.created'));
    }

    public function show(Restaurant $restaurant, ModuleManager $modules): View
    {
        $restaurant->load(['domains' => fn ($query) => $query->orderBy('id')]);

        return view('super.restaurants.show', [
            'restaurant' => $restaurant,
            'owner' => $restaurant->owner(),
            'modules' => $modules,
        ]);
    }

    public function update(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            ...$this->slugRules($restaurant),
        ]);

        $restaurant->update($data);

        return back()->with('status', __('ui.super.saved'));
    }

    public function lock(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->forceFill(['status' => RestaurantStatus::Locked])->save();

        return back()->with('status', __('ui.super.locked'));
    }

    public function unlock(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->forceFill(['status' => RestaurantStatus::Active])->save();

        return back()->with('status', __('ui.super.unlocked'));
    }

    /** @return array<string, list<mixed>> */
    private function slugRules(?Restaurant $restaurant = null): array
    {
        return ['slug' => [
            'required', 'string', 'min:2', 'max:60',
            'regex:#^'.config('liresto.slug_pattern').'$#',
            Rule::notIn(config('liresto.reserved_slugs')),
            Rule::unique('restaurants', 'slug')->ignore($restaurant),
        ]];
    }

    /** Chủ quán khoá thiết bị rồi mất máy: super admin mở lại. */
    public function unlockDevices(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->owner()?->forceFill(['devices_locked' => false])->save();

        return back()->with('status', __('ui.super.devices_unlocked'));
    }
}

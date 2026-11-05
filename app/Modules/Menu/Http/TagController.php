<?php

namespace App\Modules\Menu\Http;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Models\MenuTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(): View
    {
        return view('modules.menu.tags', ['tags' => MenuTag::orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        MenuTag::create($request->validate(['name' => ['required', 'string', 'max:60']]));

        return back()->with('status', __('menu.tag_saved'));
    }

    public function edit(MenuTag $tag): View
    {
        return view('modules.menu.tag-form', compact('tag'));
    }

    public function update(Request $request, MenuTag $tag): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            $this->deleteImage($tag);
            $tag->image_path = $request->file('image')?->store(
                'restaurants/'.tenant()->getKey().'/menu',
                config('liresto.uploads_disk'),
            ) ?: null;
        }

        $tag->name = $request->input('name');
        $tag->image_only = $request->boolean('image_only') && $tag->image_path !== null;
        $tag->save();

        return redirect()->route('tenant.admin.menu.tags.index')->with('status', __('menu.tag_saved'));
    }

    public function destroy(MenuTag $tag): RedirectResponse
    {
        $this->deleteImage($tag);
        $tag->delete();

        return redirect()->route('tenant.admin.menu.tags.index')->with('status', __('menu.tag_deleted'));
    }

    private function deleteImage(MenuTag $tag): void
    {
        if ($tag->image_path) {
            Storage::disk(config('liresto.uploads_disk'))->delete($tag->image_path);
        }
    }
}

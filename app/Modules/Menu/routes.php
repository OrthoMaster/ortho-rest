<?php

use App\Modules\Menu\Http\CategoryController;
use App\Modules\Menu\Http\ItemController;
use App\Modules\Menu\Http\ItemOptionController;
use App\Modules\Menu\Http\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware('module:menu')
    ->prefix('menu')
    ->name('menu.')
    ->group(function () {
        // Xem menu và báo hết món / còn món: bếp, phục vụ... không cần quyền sửa menu.
        Route::middleware('can_any:menu.manage,menu.availability')->group(function () {
            Route::get('/', [ItemController::class, 'index'])->name('index');
            Route::post('items/{item}/toggle', [ItemController::class, 'toggle'])->name('items.toggle');
        });

        Route::middleware('can:menu.manage')->group(function () {
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::post('categories/{category}/items/reorder', [ItemController::class, 'reorder'])->name('items.reorder');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('tags', [TagController::class, 'index'])->name('tags.index');
            Route::post('tags', [TagController::class, 'store'])->name('tags.store');
            Route::get('tags/{tag}/edit', [TagController::class, 'edit'])->name('tags.edit');
            Route::put('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
            Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

            Route::get('items/create', [ItemController::class, 'create'])->name('items.create');
            Route::post('items', [ItemController::class, 'store'])->name('items.store');
            Route::get('items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
            Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
            Route::get('items/{item}/options', [ItemOptionController::class, 'edit'])->name('items.options');
            Route::put('items/{item}/options', [ItemOptionController::class, 'update'])->name('items.options.update');
            Route::post('items/{item}/options/copy', [ItemOptionController::class, 'copy'])->name('items.options.copy');
            Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
        });
    });

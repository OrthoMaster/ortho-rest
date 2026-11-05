<?php

use App\Modules\Menu\Http\CategoryController;
use App\Modules\Menu\Http\ItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:menu', 'role:owner,manager'])
    ->prefix('menu')
    ->name('menu.')
    ->group(function () {
        Route::get('/', [ItemController::class, 'index'])->name('index');

        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::post('categories/{category}/move', [CategoryController::class, 'move'])->name('categories.move');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('items/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('items', [ItemController::class, 'store'])->name('items.store');
        Route::get('items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::post('items/{item}/toggle', [ItemController::class, 'toggle'])->name('items.toggle');
        Route::post('items/{item}/move', [ItemController::class, 'move'])->name('items.move');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    });

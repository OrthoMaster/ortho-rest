<?php

// Web công khai. File này được nạp hai lần: domain-chinh/{slug}/... và domain riêng.

use App\Http\Controllers\PublicSite\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('public.home');

<?php

use App\Http\Controllers\Internal\CaddyDomainCheckController;
use Illuminate\Support\Facades\Route;

Route::get('internal/caddy/ask', CaddyDomainCheckController::class)
    ->middleware('throttle:120,1')
    ->name('internal.caddy.ask');

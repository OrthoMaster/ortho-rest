<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Kitchen\Screens;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        // Bật Màn hình khu vực: hiện các màn hình người này mở được để vào nhanh.
        $screens = has_module('kitchen') && $user->can('kitchen.screen');

        return view('admin.dashboard', [
            'restaurant' => tenant(),
            'user' => $user,
            'screens' => $screens,
            'stations' => $screens ? Screens::stationsFor($user) : collect(),
            'pending' => $screens ? Screens::pendingCounts() : [],
            'prep' => $screens && Screens::canPrep($user),
            'prepCount' => $screens && Screens::canPrep($user) ? Screens::prepOrders()->count() : 0,
        ]);
    }
}

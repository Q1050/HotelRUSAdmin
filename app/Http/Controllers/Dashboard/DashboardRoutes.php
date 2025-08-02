<?php

namespace App\Http\Controllers\Dashboard;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Resources\DashboardResource;
use App\Models\User;

class DashboardRoutes extends Controller
{
    public function mainScreen(Request $request)
    {
        $user = User::find($request->user()->id);
        return inertia::render('Dashboard/Dashboard', [
          'DashboardModel' => (new DashboardResource($user))->resolve(),
        ]);
    }
    public function GuestScreen(Request $request)
    {
        return inertia::render('Dashboard/Guests/Guests');
    }
    public function RoomsScreen(Request $request)
    {
        return inertia::render('Dashboard/Rooms/Rooms');
    }
    public function BookingsScreen(Request $request)
    {
        return inertia::render('Bookings');
    }
    public function SettingScreen(Request $request)
    {
        return inertia::render('Settings');
    }
    public function LogoutScreen(Request $request)
    {
        return inertia::render('Logout');
    }
}

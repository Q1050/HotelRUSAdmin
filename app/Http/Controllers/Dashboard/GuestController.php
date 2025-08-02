<?php

namespace App\Http\Controllers\Dashboard;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
class GuestController extends Controller
{
    public function show(Request $request, $id)
    {
        return inertia::render('Dashboard/Guests/GuestDetails', ['id' => $id]);
    }
}

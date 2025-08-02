<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Auth\Middleware\Authenticate;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard.main');
    }

    return redirect()->route('auth.register');
});


Route::prefix('authenticate')->name('auth.')->middleware('guest')->group(function () {

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');;
    Route::get('/register', function () {
        return Inertia::render('Auth/Register');
    })->name('register');
    Route::post('/register', function () {
        return Inertia::render('Auth/Register');
    })->name('register');
});
Route::prefix('dashboard')->name('dashboard.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Dashboard\DashboardRoutes::class, 'mainScreen'])->name('main');
    Route::get('/guests', [\App\Http\Controllers\Dashboard\DashboardRoutes::class, 'GuestScreen'])->name('guest');
    Route::get('/guests/{id}', [\App\Http\Controllers\Dashboard\GuestController::class, 'show'])->name('guest');
    Route::get('/rooms', [\App\Http\Controllers\Dashboard\DashboardRoutes::class, 'RoomsScreen'])->name('rooms');
    Route::get('/bookings', [\App\Http\Controllers\Dashboard\DashboardRoutes::class, 'BookingsScreen'])->name('bookings');
    Route::get('/settings', [\App\Http\Controllers\Dashboard\DashboardRoutes::class, 'SettingScreen'])->name('settings');
    Route::get('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

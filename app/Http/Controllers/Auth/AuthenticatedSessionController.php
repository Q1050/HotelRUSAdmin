<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use App\Notifications\AdminTwoFactorCode;
use App\Services\Security\AuditLogger;
use Illuminate\Support\Facades\Hash;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();
        $request->session()->regenerate();
        $user=$request->user();
        AuditLogger::record($request,'login_password_verified','authentication','normal','Password verified for '.$user->email.'.',$user);
        if($user->role==='super_admin'&&$user->two_factor_enabled){$code=(string)random_int(100000,999999);$user->forceFill(['two_factor_code_hash'=>Hash::make($code),'two_factor_code_expires_at'=>now()->addMinutes(10)])->save();$request->session()->put('two_factor_user_id',$user->id);$user->notify(new AdminTwoFactorCode($code));Auth::logout();return redirect()->route('two-factor.challenge');}
        $request->user()->forceFill(['last_login_at' => now()])->save();
        AuditLogger::record($request,'login_succeeded','authentication','normal','Staff signed in successfully.',$request->user());
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if($request->user())AuditLogger::record($request,'logout','authentication','normal','Staff signed out.',$request->user());
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: ['webhooks/locks/*','webhooks/stripe']);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff.permission' => \App\Http\Middleware\EnsureStaffPermission::class,
            'guest.device' => \App\Http\Middleware\EnsureGuestDevice::class,
            'hotel' => \App\Http\Middleware\SetCurrentHotel::class,
            'hotel.feature' => \App\Http\Middleware\EnsureHotelFeature::class,
            'hotel.public' => \App\Http\Middleware\ResolvePublicHotel::class,
            'hotel.limit' => \App\Http\Middleware\EnsureHotelLimit::class,
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

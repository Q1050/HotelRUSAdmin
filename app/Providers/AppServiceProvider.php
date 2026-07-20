<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\Locks\LockProvider::class, \App\Services\Locks\ProviderManager::class);
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        RateLimiter::for('privacy-export', fn ($request) => Limit::perDay(3)->by('export:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('privacy-deletion', fn ($request) => Limit::perHour(2)->by('deletion:'.($request->user()?->id ?? $request->ip())));
    }
}

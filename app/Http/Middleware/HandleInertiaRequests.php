<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user() ? $request->user()->toArray() + ['permissions' => collect(\App\Support\StaffPermissions::ALL)->filter(fn ($permission) => $request->user()->hasPermission($permission))->values()] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'generatedKey' => fn () => $request->session()->get('generatedKey'),
            ],
            'system' => [
                'version' => config('version.number'),
                'releaseName' => config('version.name'),
            ],
            'hotel' => fn () => $request->user()?->hotel ? [
                'id' => $request->user()->hotel->id, 'name' => $request->user()->hotel->name, 'slug' => $request->user()->hotel->slug, 'currency' => $request->user()->hotel->currency,
                'features' => $request->user()->hotel->enabledFeatures(),
                'plan' => $request->user()->hotel->subscription?->plan?->name,
                'subscription' => $request->user()->hotel->subscription ? [
                    'status' => $request->user()->hotel->subscription->lifecycleState(),
                    'readOnly' => $request->user()->hotel->subscription->isReadOnly(),
                    'trialEndsAt' => $request->user()->hotel->subscription->trial_ends_at?->toISOString(),
                    'accessEndsAt' => $request->user()->hotel->subscription->accessEndsAt()?->toISOString(),
                    'retentionDays' => config('subscriptions.retention_days'),
                ] : null,
                'branding' => \App\Services\PropertySettings::publicData($request->user()->hotel),
            ] : null,
            'impersonating' => fn () => $request->session()->has('platform_actor_id'),
            'notifications' => fn () => $request->user() ? [
                'unreadCount' => $request->user()->unreadNotifications()->count(),
                'latest' => $request->user()->notifications()->latest()->limit(5)->get()->map(fn ($notification) => [
                    'id' => $notification->id,
                    'message' => $notification->data['message'] ?? 'New notification',
                    'url' => $notification->data['url'] ?? null,
                    'read' => (bool) $notification->read_at,
                ]),
            ] : ['unreadCount' => 0, 'latest' => []],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}

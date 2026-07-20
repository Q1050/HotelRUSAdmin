<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\HotelBackup;
use App\Models\HotelFeatureOverride;
use App\Models\HotelSubscription;
use App\Models\LockDevice;
use App\Models\LockProviderConfig;
use App\Models\Plan;
use App\Models\Room;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Operations\OperationsMonitor;
use App\Services\PropertySettings;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class HotelController extends Controller
{
    public function overview(OperationsMonitor $monitor): Response
    {
        $hotels = Hotel::with('subscription.plan')->latest('id')->get();
        $subscriptions = HotelSubscription::query();

        return Inertia::render('Platform/Overview', [
            'stats' => [
                'hotels' => $hotels->count(), 'activeHotels' => $hotels->where('status', 'active')->count(),
                'activeSubscriptions' => (clone $subscriptions)->whereIn('status', ['active', 'trialing', 'grace'])->count(),
                'attentionSubscriptions' => (clone $subscriptions)->whereIn('status', ['past_due', 'cancelled'])->count(),
                'rooms' => Room::withoutGlobalScopes()->count(), 'staff' => User::withoutGlobalScopes()->whereNotNull('hotel_id')->count(), 'customRoles' => StaffRole::withoutGlobalScopes()->count(),
            ],
            'plans' => Plan::withCount('subscriptions')->orderBy('id')->get()->map(fn (Plan $plan) => ['id' => $plan->id, 'name' => $plan->name, 'subscribers' => $plan->subscriptions_count]),
            'recentHotels' => $hotels->take(5)->map(fn (Hotel $hotel) => ['id' => $hotel->id, 'name' => $hotel->name, 'slug' => $hotel->slug, 'status' => $hotel->status, 'plan' => $hotel->subscription?->plan?->name, 'subscriptionStatus' => $hotel->subscription?->status, 'createdAt' => $hotel->created_at?->toISOString()])->values(),
            'recentActivity' => $this->activityItems(6),
            'operations' => $hotels->map(function (Hotel $hotel) use ($monitor) {
                $summary = $monitor->summary($hotel);

                return ['id' => $hotel->id, 'name' => $hotel->name, ...$summary];
            })->filter(fn ($item) => $item['status'] === 'attention')->values(),
            'backups' => $hotels->map(function (Hotel $hotel) {
                $backup = HotelBackup::withoutGlobalScopes()->where('hotel_id', $hotel->id)->latest()->first();
                $next = now()->setTime(1, 0);
                if (now()->gte($next)) {
                    $next->addDay();
                }

                return ['hotelId' => $hotel->id, 'hotelName' => $hotel->name, 'status' => $backup?->status ?? 'missing', 'sizeBytes' => $backup?->size_bytes ?? 0, 'records' => data_get($backup?->manifest, 'records', 0), 'files' => data_get($backup?->manifest, 'files', 0), 'missingFiles' => count(data_get($backup?->manifest, 'missing_files', [])), 'createdAt' => $backup?->created_at?->toISOString(), 'verifiedAt' => $backup?->verified_at?->toISOString(), 'ageHours' => $backup?->created_at?->diffInHours(now()), 'nextScheduledAt' => $next->toISOString(), 'needsAttention' => ! $backup || $backup->status !== 'verified' || ! $backup->verified_at || $backup->created_at->lt(now()->subHours(config('operations.backup_stale_hours'))) || $backup->size_bytes > config('operations.backup_max_mb') * 1048576 || count(data_get($backup->manifest, 'missing_files', [])) > 0];
            })->values(),
        ]);
    }

    public function plans(): Response
    {
        $groups = [
            ['key' => 'foundation', 'name' => 'Foundation', 'description' => 'Core property and administrative capabilities.', 'features' => [Features::CORE, Features::SECURITY, Features::REPORTS]],
            ['key' => 'operations', 'name' => 'Hotel Operations', 'description' => 'Daily room servicing and engineering workflows.', 'features' => [Features::HOUSEKEEPING, Features::MAINTENANCE]],
            ['key' => 'food_beverage', 'name' => 'Food & Beverage', 'description' => 'Menus, room-service ordering, and kitchen workflows.', 'features' => [Features::FOOD_BEVERAGE]],
            ['key' => 'guest_experience', 'name' => 'Guest Experience', 'description' => 'Mobile arrival, messaging, and notification tools.', 'features' => [Features::MOBILE, Features::PRE_ARRIVAL, Features::NOTIFICATIONS, Features::CONVERSATIONS]],
            ['key' => 'access', 'name' => 'Access & Locks', 'description' => 'Connected room access and smart-lock management.', 'features' => [Features::LOCKS]],
        ];
        return Inertia::render('Platform/Plans', [
            'features' => collect(Features::ALL)->map(fn ($feature) => ['key' => $feature, 'name' => str($feature)->replace('_', ' ')->title()->toString()]),
            'groups' => $groups,
            'plans' => Plan::with(['features'])->withCount('subscriptions')->orderBy('id')->get()->map(fn (Plan $plan) => [
                'id' => $plan->id, 'key' => $plan->key, 'name' => $plan->name, 'active' => $plan->active, 'limits' => $plan->limits, 'monthlyPrice' => $plan->monthly_price, 'stripePriceId' => $plan->stripe_price_id, 'subscribers' => $plan->subscriptions_count,
                'features' => $plan->features->where('enabled', true)->pluck('feature')->values(),
            ]),
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $this->validatePlan($request);
        DB::transaction(function () use ($data) {
            $plan = Plan::create(collect($data)->except('features')->all());
            $this->syncPlanFeatures($plan, $data['features'], ['rooms' => $data['rooms'] ?? null, 'staff' => $data['staff'] ?? null]);
        });
        return back()->with('success', 'Subscription plan created.');
    }

    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validatePlan($request, $plan);
        DB::transaction(function () use ($plan, $data) {
            $plan->update(collect($data)->except('features')->all());
            $this->syncPlanFeatures($plan, $data['features'], ['rooms' => $data['rooms'] ?? null, 'staff' => $data['staff'] ?? null]);
        });
        return back()->with('success', 'Plan modules and limits updated.');
    }

    public function duplicatePlan(Plan $plan): RedirectResponse
    {
        $copy = DB::transaction(function () use ($plan) {
            $key = $plan->key.'-copy'; $suffix = 2;
            while (Plan::where('key', $key)->exists()) $key = $plan->key.'-copy-'.$suffix++;
            $copy = Plan::create(['key' => $key, 'name' => $plan->name.' Copy', 'active' => false, 'limits' => $plan->limits, 'monthly_price' => $plan->monthly_price]);
            $this->syncPlanFeatures($copy, $plan->features()->where('enabled', true)->pluck('feature')->all());
            return $copy;
        });
        return back()->with('success', "{$copy->name} is ready to edit.");
    }

    private function validatePlan(Request $request, ?Plan $plan = null): array
    {
        return $request->validate(['key' => ['required', 'alpha_dash', 'max:80', Rule::unique('plans', 'key')->ignore($plan)], 'name' => ['required', 'string', 'max:120'], 'active' => ['required', 'boolean'], 'rooms' => ['nullable', 'integer', 'min:1'], 'staff' => ['nullable', 'integer', 'min:1'], 'monthly_price' => ['nullable', 'integer', 'min:0'], 'stripe_price_id' => ['nullable', 'string', 'max:255', Rule::unique('plans', 'stripe_price_id')->ignore($plan)], 'features' => ['required', 'array'], 'features.*' => ['string', Rule::in(Features::ALL)]], [], ['key' => 'plan key']);
    }

    private function syncPlanFeatures(Plan $plan, array $features, ?array $limits = null): void
    {
        $plan->features()->delete();
        foreach (array_unique($features) as $feature) $plan->features()->create(['feature' => $feature, 'enabled' => true]);
        if ($limits !== null) $plan->update(['limits' => $limits]);
    }

    public function activity(): Response
    {
        return Inertia::render('Platform/Activity', ['events' => $this->activityItems(100)]);
    }

    public function onboarding(Hotel $hotel): Response
    {
        $settings = $hotel->settings ?? [];
        $roomCount = Room::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count();
        $staffCount = User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count();
        $profileComplete = filled($settings['contact_email'] ?? null) && filled($settings['phone'] ?? null) && filled($settings['address'] ?? null) && filled($settings['city'] ?? null) && filled($settings['country'] ?? null);
        $adminReady = User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('role', 'super_admin')->where('status', 'active')->exists();
        $lockReady = ! $hotel->hasFeature(Features::LOCKS) || LockProviderConfig::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('connection_status', 'connected')->exists();
        $steps = [['key' => 'profile', 'name' => 'Property profile', 'complete' => $profileComplete], ['key' => 'administrator', 'name' => 'Property administrator', 'complete' => $adminReady], ['key' => 'rooms', 'name' => 'Room inventory', 'complete' => $roomCount > 0], ['key' => 'staff', 'name' => 'Operations team', 'complete' => $staffCount > 1], ['key' => 'locks', 'name' => 'Lock integration', 'complete' => $lockReady, 'optional' => true]];

        return Inertia::render('Platform/Onboarding', [
            'hotel' => ['id' => $hotel->id, 'name' => $hotel->name, 'slug' => $hotel->slug, 'timezone' => $hotel->timezone, 'currency' => $hotel->currency, 'settings' => $settings, 'branding' => \App\Services\PropertySettings::publicData($hotel), 'plan' => $hotel->subscription?->plan?->name, 'roomLimit' => $hotel->limit('rooms'), 'features' => $hotel->enabledFeatures()],
            'steps' => $steps, 'progress' => (int) round(collect($steps)->where('optional', '!=', true)->where('complete', true)->count() / 4 * 100),
            'counts' => ['rooms' => $roomCount, 'staff' => $staffCount], 'ready' => $profileComplete && $adminReady && $roomCount > 0 && $staffCount > 1, 'launched' => (bool) ($settings['onboarding_completed'] ?? false),
            'fcm' => app(\App\Services\Notifications\FcmClient::class)->details($hotel),
        ]);
    }

    public function updateOnboardingProfile(Request $request, Hotel $hotel): RedirectResponse
    {
        $data = $request->validate(['contact_email' => 'required|email|max:255', 'phone' => 'required|string|max:40', 'address' => 'required|string|max:255', 'city' => 'required|string|max:100', 'country' => 'required|string|max:100', 'website' => 'nullable|url|max:255', 'check_in_time' => 'required|date_format:H:i', 'check_out_time' => 'required|date_format:H:i', 'timezone' => 'required|timezone', 'currency' => 'required|string|size:3']);
        $hotel->update(['timezone' => $data['timezone'], 'currency' => strtoupper($data['currency']), 'settings' => array_merge($hotel->settings ?? [], collect($data)->except(['timezone', 'currency'])->all())]);
        $this->audit($request, $hotel, 'onboarding_profile_updated', "Onboarding profile updated for {$hotel->name}.");

        return back()->with('success', 'Property profile saved.');
    }

    public function updateBranding(Request $request, Hotel $hotel, PropertySettings $settings): RedirectResponse
    {
        $settings->update($request, $hotel);
        $this->audit($request, $hotel, 'property_branding_updated', "Branding and guest policies updated for {$hotel->name}.");

        return back()->with('success', 'Property branding saved.');
    }

    public function updateFcm(Request $request, Hotel $hotel, \App\Services\Notifications\FcmSettings $settings): RedirectResponse
    {
        $settings->save($request, $hotel);
        $this->audit($request, $hotel, 'fcm_configuration_updated', "Firebase configuration updated for {$hotel->name}.");

        return back()->with('success', 'Firebase configuration saved.');
    }

    public function testFcm(Request $request, Hotel $hotel, \App\Services\Notifications\FcmSettings $settings, \App\Services\Notifications\FcmClient $client): RedirectResponse
    {
        try {
            $settings->test($hotel, $client);

            return back()->with('success', 'Firebase authentication succeeded.');
        } catch (\Throwable$e) {
            return back()->withErrors(['fcm' => $e->getMessage()]);
        }
    }

    public function bulkRooms(Request $request, Hotel $hotel): RedirectResponse
    {
        $data = $request->validate(['prefix' => 'nullable|string|max:10', 'start' => 'required|integer|min:1|max:9999', 'count' => 'required|integer|min:1|max:250', 'floor' => 'required|integer|min:0|max:200', 'type' => 'required|string|max:50', 'price' => 'required|numeric|min:0']);
        $used = Room::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count();
        $limit = $hotel->limit('rooms');
        abort_if($limit !== null && $used + $data['count'] > $limit, 422, "This plan allows {$limit} rooms; only ".max(0, $limit - $used).' remain.');
        $numbers = collect(range($data['start'], $data['start'] + $data['count'] - 1))->map(fn ($number) => ($data['prefix'] ?? '').$number);
        abort_if(Room::withoutGlobalScopes()->where('hotel_id', $hotel->id)->whereIn('number', $numbers)->exists(), 422, 'One or more generated room numbers already exist for this hotel.');
        DB::transaction(fn () => $numbers->each(fn ($number) => Room::create(['hotel_id' => $hotel->id, 'number' => $number, 'type' => $data['type'], 'floor' => $data['floor'], 'status' => 'available', 'lock_status' => 'locked', 'price' => $data['price']])));
        $this->audit($request, $hotel, 'onboarding_rooms_created', count($numbers)." rooms created for {$hotel->name}.", ['first' => $numbers->first(), 'last' => $numbers->last(), 'count' => $numbers->count()]);

        return back()->with('success', $numbers->count().' rooms created.');
    }

    public function launch(Request $request, Hotel $hotel): RedirectResponse
    {
        $settings = $hotel->settings ?? [];
        $ready = filled($settings['contact_email'] ?? null) && filled($settings['phone'] ?? null) && filled($settings['address'] ?? null) && Room::withoutGlobalScopes()->where('hotel_id', $hotel->id)->exists() && User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('role', 'super_admin')->where('status', 'active')->exists() && User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count() > 1;
        abort_unless($ready, 422, 'Complete the required onboarding steps before launch.');
        $hotel->update(['settings' => array_merge($settings, ['onboarding_completed' => true, 'onboarding_completed_at' => now()->toISOString()])]);
        $this->audit($request, $hotel, 'hotel_onboarding_completed', "{$hotel->name} marked ready for operation.");

        return back()->with('success', 'Property onboarding completed.');
    }

    public function index(): Response
    {
        $hotels = Hotel::with(['subscription.plan', 'featureOverrides'])->latest('id')->get()->map(fn (Hotel $hotel) => [
            'id' => $hotel->id, 'name' => $hotel->name, 'slug' => $hotel->slug, 'status' => $hotel->status,
            'timezone' => $hotel->timezone, 'currency' => $hotel->currency,
            'planId' => $hotel->subscription?->plan_id, 'plan' => $hotel->subscription?->plan?->name,
            'subscriptionStatus' => $hotel->subscription?->status ?? 'inactive',
            'subscriptionState' => $hotel->subscription?->lifecycleState() ?? 'inactive',
            'trialStartedAt' => $hotel->subscription?->trial_started_at?->toISOString(),
            'trialEndsAt' => $hotel->subscription?->trial_ends_at?->toISOString(),
            'graceEndsAt' => $hotel->subscription?->accessEndsAt()?->toISOString(),
            'features' => collect(Features::ALL)->map(fn ($feature) => [
                'key' => $feature, 'name' => str($feature)->replace('_', ' ')->title()->toString(),
                'enabled' => $hotel->hasFeature($feature),
                'override' => optional($hotel->featureOverrides->firstWhere('feature', $feature))->enabled,
            ])->values(),
            'usage' => [
                'rooms' => Room::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count(),
                'staff' => User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count(),
                'guests' => Guest::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count(),
                'locks' => LockDevice::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count(),
            ],
            'limits' => ['rooms' => $hotel->limit('rooms'), 'staff' => $hotel->limit('staff')],
            'admin' => User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('role', 'super_admin')->oldest('id')->first()?->only(['id', 'name', 'email', 'status']),
            'superAdmins' => User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('role', 'super_admin')->get(['id', 'name', 'email', 'status']),
            'recoveryCandidates' => User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('status', 'active')->where('role', '!=', 'super_admin')->get(['id', 'name', 'email', 'role']),
            'createdAt' => $hotel->created_at?->toISOString(),
        ]);

        return Inertia::render('Platform/Hotels', [
            'hotels' => $hotels,
            'plans' => Plan::where('active', true)->with('features')->orderBy('id')->get()->map(fn (Plan $plan) => [
                'id' => $plan->id, 'key' => $plan->key, 'name' => $plan->name, 'limits' => $plan->limits,
                'features' => $plan->features->where('enabled', true)->pluck('feature')->values(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->mergeIfMissing(['subscription_mode' => 'trial', 'trial_days' => config('subscriptions.trial_days')]);
        $data = $request->validate([
            'name' => 'required|string|max:150', 'slug' => 'required|alpha_dash|max:100|unique:hotels,slug',
            'timezone' => 'required|timezone', 'currency' => 'required|string|size:3', 'plan_id' => 'required|exists:plans,id',
            'subscription_mode' => 'required|in:trial,active', 'trial_days' => 'required_if:subscription_mode,trial|nullable|integer|min:1|max:90',
            'admin_first_name' => 'required|string|max:100', 'admin_last_name' => 'required|string|max:100',
            'admin_email' => 'required|email|max:255|unique:users,email', 'admin_password' => ['required', 'confirmed', Password::defaults()],
        ]);
        DB::transaction(function () use ($data, $request) {
            $hotel = Hotel::create(['name' => $data['name'], 'slug' => $data['slug'], 'timezone' => $data['timezone'], 'currency' => strtoupper($data['currency']), 'status' => 'active']);
            $trial = $data['subscription_mode'] === 'trial';
            HotelSubscription::create(['hotel_id' => $hotel->id, 'plan_id' => $data['plan_id'], 'status' => $trial ? 'trialing' : 'active', 'trial_started_at' => $trial ? now() : null, 'trial_ends_at' => $trial ? now()->addDays($data['trial_days']) : null]);
            User::create(['hotel_id' => $hotel->id, 'firstName' => $data['admin_first_name'], 'lastName' => $data['admin_last_name'], 'name' => $data['admin_first_name'].' '.$data['admin_last_name'], 'email' => $data['admin_email'], 'password' => $data['admin_password'], 'role' => 'super_admin', 'status' => 'active', 'email_verified_at' => now()]);
            $this->audit($request, $hotel, 'hotel_created', "Hotel {$hotel->name} created.", ['plan_id' => $data['plan_id']]);
        });

        return back()->with('success', $data['subscription_mode'] === 'trial' ? 'Hotel created with its free trial.' : 'Hotel and its first administrator were created.');
    }

    public function update(Request $request, Hotel $hotel): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('hotels', 'slug')->ignore($hotel)], 'status' => 'required|in:active,suspended', 'timezone' => 'required|timezone', 'currency' => 'required|string|size:3', 'plan_id' => 'required|exists:plans,id', 'subscription_status' => 'required|in:active,trialing,grace,past_due,cancelled,expired', 'trial_ends_at' => [Rule::requiredIf(in_array($request->input('subscription_status'), ['trialing', 'grace'], true)), 'nullable', 'date']]);
        $before = ['hotel' => $hotel->only(['name', 'slug', 'status']), 'plan_id' => $hotel->subscription?->plan_id, 'subscription_status' => $hotel->subscription?->status];
        DB::transaction(function () use ($hotel, $data) {
            $hotel->update(['name' => $data['name'], 'slug' => $data['slug'], 'status' => $data['status'], 'timezone' => $data['timezone'], 'currency' => strtoupper($data['currency'])]);
            $dates = $data['subscription_status'] === 'trialing'
                ? ['trial_started_at' => $hotel->subscription?->trial_started_at ?? now(), 'trial_ends_at' => $data['trial_ends_at'], 'grace_ends_at' => null]
                : ($data['subscription_status'] === 'grace' ? ['grace_ends_at' => $data['trial_ends_at']] : ['trial_started_at' => null, 'trial_ends_at' => null, 'grace_ends_at' => null]);
            HotelSubscription::updateOrCreate(['hotel_id' => $hotel->id], ['plan_id' => $data['plan_id'], 'status' => $data['subscription_status'], ...$dates]);
        });
        $this->audit($request, $hotel, 'hotel_updated', "Hotel {$hotel->name} subscription or account was updated.", ['before' => $before, 'after' => $data]);

        return back()->with('success', 'Hotel updated.');
    }

    public function feature(Request $request, Hotel $hotel, string $feature): RedirectResponse
    {
        abort_unless(in_array($feature, Features::ALL, true), 404);
        $mode = $request->validate(['mode' => 'required|in:inherit,enabled,disabled'])['mode'];
        if ($mode === 'inherit') {
            HotelFeatureOverride::where('hotel_id', $hotel->id)->where('feature', $feature)->delete();
        } else {
            HotelFeatureOverride::updateOrCreate(['hotel_id' => $hotel->id, 'feature' => $feature], ['enabled' => $mode === 'enabled']);
        }
        $this->audit($request, $hotel, 'feature_override_updated', "{$feature} set to {$mode} for {$hotel->name}.", ['feature' => $feature, 'mode' => $mode]);

        return back()->with('success', 'Module override updated.');
    }

    public function impersonate(Request $request, Hotel $hotel): RedirectResponse
    {
        $admin = User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('role', 'super_admin')->where('status', 'active')->oldest('id')->firstOrFail();
        $this->audit($request, $hotel, 'platform_impersonation_started', "Platform support entered {$hotel->name} as {$admin->email}.");
        $request->session()->put('platform_actor_id', $request->user()->id);
        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Support session started. Use the support-session banner when finished.');
    }

    public function recoverAdministrator(Request $request, Hotel $hotel): RedirectResponse
    {
        $data = $request->validate(['compromised_user_id' => ['required', 'integer'], 'replacement_user_id' => ['nullable', 'integer'], 'first_name' => ['required_without:replacement_user_id', 'nullable', 'string', 'max:100'], 'last_name' => ['required_without:replacement_user_id', 'nullable', 'string', 'max:100'], 'email' => ['required_without:replacement_user_id', 'nullable', 'email', 'max:255', 'unique:users,email'], 'password' => ['required_without:replacement_user_id', 'nullable', 'confirmed', Password::defaults()], 'platform_password' => ['required', 'current_password'], 'incident_reason' => ['required', 'string', 'min:20', 'max:2000'], 'confirmation' => ['required', Rule::in(['RECOVER'])]]);
        $compromised = User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('role', 'super_admin')->findOrFail($data['compromised_user_id']);
        abort_if(isset($data['replacement_user_id']) && (int) $data['replacement_user_id'] === $compromised->id, 422, 'Select a different replacement administrator.');
        $replacement = DB::transaction(function () use ($data, $hotel, $compromised) {
            $locked = User::withoutGlobalScopes()->lockForUpdate()->findOrFail($compromised->id);
            if ($data['replacement_user_id'] ?? null) {
                $replacement = User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('status', 'active')->findOrFail($data['replacement_user_id']);
                $replacement->update(['role' => 'super_admin', 'staff_role_id' => null]);
            } else {
                $replacement = User::create(['hotel_id' => $hotel->id, 'firstName' => $data['first_name'], 'lastName' => $data['last_name'], 'name' => $data['first_name'].' '.$data['last_name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => 'super_admin', 'staff_role_id' => null, 'status' => 'active', 'email_verified_at' => now()]);
            }$locked->update(['status' => 'suspended', 'two_factor_code_hash' => null, 'two_factor_code_expires_at' => null, 'remember_token' => null]);
            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')->where('tokenable_type', $locked->getMorphClass())->where('tokenable_id', $locked->id)->delete();
            }
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $locked->id)->delete();
            }

            return $replacement;
        });
        $this->audit($request, $hotel, 'compromised_admin_recovered', "Compromised administrator {$compromised->email} was suspended and replaced by {$replacement->email}.", ['compromised_user_id' => $compromised->id, 'replacement_user_id' => $replacement->id, 'reason' => $data['incident_reason'], 'session_tokens_revoked' => true], 'critical');
        $replacement->notify(new \App\Notifications\AdminRecoveryNotice($hotel->name, 'You are now the property Super Administrator following an account-security recovery.'));
        $contact = data_get($hotel->settings, 'contact_email');
        if ($contact && $contact !== $replacement->email) {
            Notification::route('mail', $contact)->notify(new \App\Notifications\AdminRecoveryNotice($hotel->name, "The compromised administrator {$compromised->email} was suspended and replaced."));
        }

        return back()->with('success', 'Compromised administrator suspended, access revoked, and replacement promoted.');
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $actorId = $request->session()->pull('platform_actor_id');
        abort_unless($actorId, 403);
        $actor = User::withoutGlobalScopes()->where('id', $actorId)->where('is_platform_admin', true)->firstOrFail();
        Auth::login($actor);
        $request->session()->regenerate();

        return redirect()->route('platform.hotels.index')->with('success', 'Support session ended.');
    }

    private function audit(Request $request, Hotel $hotel, string $action, string $description, array $metadata = [], string $severity = 'sensitive'): void
    {
        AuditEvent::withoutGlobalScopes()->create(['hotel_id' => $hotel->id, 'actor_id' => $request->user()->id, 'action' => $action, 'category' => 'platform', 'severity' => $severity, 'subject_type' => Hotel::class, 'subject_id' => $hotel->id, 'description' => $description, 'metadata' => $metadata, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'occurred_at' => now()]);
    }

    private function activityItems(int $limit)
    {
        $events = AuditEvent::withoutGlobalScopes()->with('actor')->where('category', 'platform')->latest('occurred_at')->limit($limit)->get();
        $hotels = Hotel::whereIn('id', $events->pluck('hotel_id'))->pluck('name', 'id');

        return $events->map(fn (AuditEvent $event) => ['id' => $event->id, 'action' => $event->action, 'description' => $event->description, 'hotel' => $hotels[$event->hotel_id] ?? 'Unknown property', 'actor' => $event->actor?->name ?? $event->actor?->email ?? 'System', 'severity' => $event->severity, 'occurredAt' => $event->occurred_at?->toISOString()])->values();
    }
}

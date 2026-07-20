<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AccountingProfile;
use App\Models\HotelIntegration;
use App\Models\IntegrationHealthCheck;
use App\Models\LockProviderConfig;
use App\Services\Locks\ProviderManager;
use App\Services\Notifications\FcmClient;
use App\Services\Operations\IntegrationCircuit;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationHealthController extends Controller
{
    public function index(IntegrationCircuit $circuit): Response
    {
        $this->discover($circuit);
        $checks = IntegrationHealthCheck::orderBy('service')->orderBy('label')->get()->map(fn ($health) => ['id' => $health->id, 'service' => $health->service, 'providerKey' => $health->provider_key, 'label' => $health->label, 'active' => $health->active, 'status' => $health->status, 'failures' => $health->consecutive_failures, 'threshold' => $health->failure_threshold, 'cooldownMinutes' => $health->cooldown_minutes, 'responseMs' => $health->last_response_ms, 'error' => $health->last_error, 'checkedAt' => $health->last_checked_at?->toIso8601String(), 'successAt' => $health->last_success_at?->toIso8601String(), 'circuitOpenUntil' => $health->circuit_open_until?->toIso8601String()]);

        return Inertia::render('Dashboard/Integrations/Health', ['checks' => $checks, 'summary' => ['healthy' => $checks->where('status', 'healthy')->count(), 'attention' => $checks->whereIn('status', ['degraded', 'circuit_open'])->count(), 'disabled' => $checks->where('active', false)->count(), 'untested' => $checks->where('status', 'untested')->count()]]);
    }

    public function test(Request $request, IntegrationHealthCheck $health, IntegrationCircuit $circuit, ProviderManager $locks, FcmClient $fcm): RedirectResponse
    {
        try {
            $circuit->execute($health->service, $health->provider_key, $health->label, function () use ($health, $locks, $fcm) {
                if ($health->service === 'storage') {
                    $path = 'health/integration-'.$health->hotel_id.'.tmp';
                    Storage::disk(config('filesystems.default'))->put($path, now()->toISOString());
                    Storage::disk(config('filesystems.default'))->delete($path);
                    return true;
                }
                if ($health->service === 'notifications' && $health->provider_key === 'fcm') {
                    return $fcm->test(app('currentHotel'));
                }
                if ($health->service === 'locks') {
                    return $locks->test(LockProviderConfig::where('key', $health->provider_key)->firstOrFail());
                }
                if ($health->service === 'accounting') {
                    $profile = AccountingProfile::firstOrFail();
                    if ($profile->driver === 'file') return true;
                    if ($profile->driver !== 'custom_webhook') throw new \RuntimeException('OAuth authorization is not configured for this provider.');
                    return Http::timeout(10)->head((string) data_get($profile->configuration, 'webhook_url'))->throw();
                }
                if ($health->service === 'email') {
                    abort_if(blank(config('mail.default')), 422, 'No mail transport is configured.');
                    return true;
                }
                throw new \RuntimeException('No health test is available for this integration.');
            });
            AuditLogger::record($request, 'integration_health_tested', 'operations', 'normal', "{$health->label} health test succeeded.", $health);
            return back()->with('success', "{$health->label} is healthy.");
        } catch (\Throwable $exception) {
            AuditLogger::record($request, 'integration_health_failed', 'operations', 'warning', "{$health->label} health test failed.", $health, null, ['error' => $exception->getMessage()]);
            return back()->withErrors(['integration' => $exception->getMessage()]);
        }
    }

    public function update(Request $request, IntegrationHealthCheck $health): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean'], 'failure_threshold' => ['required', 'integer', 'between:1,20'], 'cooldown_minutes' => ['required', 'integer', 'between:1,1440']]);
        $health->update($data);
        AuditLogger::record($request, 'integration_circuit_updated', 'operations', 'sensitive', "{$health->label} circuit settings updated.", $health);
        return back()->with('success', 'Integration protection settings saved.');
    }

    public function reset(Request $request, IntegrationHealthCheck $health, IntegrationCircuit $circuit): RedirectResponse
    {
        $circuit->reset($health);
        AuditLogger::record($request, 'integration_circuit_reset', 'operations', 'warning', "{$health->label} circuit manually restored.", $health);
        return back()->with('success', 'Circuit restored. Run a connection test before relying on the provider.');
    }

    private function discover(IntegrationCircuit $circuit): void
    {
        $circuit->provider('storage', 'default', 'Private file storage');
        $circuit->provider('email', 'mail', 'Email delivery');
        if (HotelIntegration::where('type', 'fcm')->exists()) $circuit->provider('notifications', 'fcm', 'Firebase Cloud Messaging');
        LockProviderConfig::where('active', true)->each(fn ($provider) => $circuit->provider('locks', $provider->key, $provider->name));
        if ($profile = AccountingProfile::first()) $circuit->provider('accounting', $profile->driver, ucfirst(str_replace('_', ' ', $profile->driver)).' accounting');
    }
}

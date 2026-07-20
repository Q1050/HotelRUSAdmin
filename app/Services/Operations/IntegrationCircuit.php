<?php

namespace App\Services\Operations;

use App\Models\IntegrationHealthCheck;
use Closure;
use RuntimeException;
use Throwable;

class IntegrationCircuit
{
    public function provider(string $service, string $key, string $label): IntegrationHealthCheck
    {
        return IntegrationHealthCheck::firstOrCreate(['service' => $service, 'provider_key' => $key], ['label' => $label]);
    }

    public function execute(string $service, string $key, string $label, Closure $operation): mixed
    {
        $health = $this->provider($service, $key, $label);
        if (! $health->active) {
            throw new RuntimeException("{$label} is disabled by an administrator.");
        }
        if ($health->circuit_open_until?->isFuture()) {
            throw new RuntimeException("{$label} is temporarily paused after repeated failures. Retry after {$health->circuit_open_until->toDateTimeString()}.");
        }
        $started = hrtime(true);
        try {
            $result = $operation();
            $health->update(['status' => 'healthy', 'consecutive_failures' => 0, 'last_response_ms' => $this->elapsed($started), 'last_error' => null, 'last_checked_at' => now(), 'last_success_at' => now(), 'circuit_open_until' => null]);
            return $result;
        } catch (Throwable $exception) {
            $failures = $health->consecutive_failures + 1;
            $open = $failures >= $health->failure_threshold;
            $health->update(['status' => $open ? 'circuit_open' : 'degraded', 'consecutive_failures' => $failures, 'last_response_ms' => $this->elapsed($started), 'last_error' => mb_substr($exception->getMessage(), 0, 2000), 'last_checked_at' => now(), 'last_failure_at' => now(), 'circuit_open_until' => $open ? now()->addMinutes($health->cooldown_minutes) : null]);
            throw $exception;
        }
    }

    public function reset(IntegrationHealthCheck $health): void
    {
        $health->update(['status' => 'untested', 'consecutive_failures' => 0, 'last_error' => null, 'circuit_open_until' => null]);
    }

    private function elapsed(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }
}

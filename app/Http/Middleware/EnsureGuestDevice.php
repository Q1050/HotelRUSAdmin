<?php

namespace App\Http\Middleware;

use App\Models\Guest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuestDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $guest = $request->user();
        $deviceId = $request->header('X-Device-ID');

        if (! $guest instanceof Guest || ! $deviceId) {
            return response()->json(['message' => 'A registered guest device is required.'], 401);
        }

        $device = $guest->devices()->where('device_id', $deviceId)->whereNull('revoked_at')->first();
        $token = $guest->currentAccessToken();
        if (! $device || ! $token || $token->name !== "guest-mobile:{$deviceId}") {
            return response()->json(['message' => 'This device session is no longer valid.'], 401);
        }

        $device->update(['last_seen_at' => now(), 'ip_address' => $request->ip()]);
        $request->attributes->set('guest_device', $device);

        return $next($request);
    }
}

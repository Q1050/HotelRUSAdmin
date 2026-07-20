<?php

namespace App\Services\Notifications;

use App\Models\Hotel;
use App\Models\HotelIntegration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmClient
{
    public function configured(?Hotel $hotel = null): bool { return $this->account($hotel) !== null; }
    public function source(?Hotel $hotel = null): string { return $this->configured($hotel) ? ($hotel?->inherits('fcm') ? 'organization' : 'hotel') : 'none'; }

    public function details(?Hotel $hotel = null): array
    {
        $integration = $hotel ? HotelIntegration::where('hotel_id', $hotel->id)->where('type', 'fcm')->first() : null;
        $configuration = $hotel?->inherits('fcm') ? data_get($hotel->organization?->credentials, 'fcm', []) : ($integration?->configuration ?? []);
        return ['source' => $this->source($hotel), 'configured' => $this->configured($hotel), 'active' => $hotel?->inherits('fcm') || ($integration?->active ?? false), 'project_id' => $configuration['project_id'] ?? null, 'client_email' => $configuration['client_email'] ?? null, 'api_key' => $configuration['api_key'] ?? null, 'messaging_sender_id' => $configuration['messaging_sender_id'] ?? null, 'android_app_id' => $configuration['android_app_id'] ?? null, 'ios_app_id' => $configuration['ios_app_id'] ?? null, 'has_private_key' => filled($configuration['private_key'] ?? null), 'connection_status' => $hotel?->inherits('fcm') ? 'inherited' : ($integration?->connection_status ?? 'unconfigured'), 'last_error' => $integration?->last_error, 'last_tested_at' => $integration?->last_tested_at?->toISOString()];
    }

    public function send(string $token, string $title, string $body, array $data = [], ?Hotel $hotel = null): void
    {
        $account = $this->account($hotel);
        if (! $account) throw new RuntimeException('Firebase Cloud Messaging is not configured.');
        Http::withToken($this->accessToken($account, $hotel))->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", ['message' => ['token' => $token, 'notification' => ['title' => $title, 'body' => $body], 'data' => collect($data)->map(fn ($value) => is_scalar($value) ? (string) $value : json_encode($value))->all(), 'android' => ['priority' => 'high'], 'apns' => ['payload' => ['aps' => ['sound' => 'default']]]]])->throw();
    }

    public function test(Hotel $hotel): void
    {
        $account = $this->account($hotel);
        if (! $account) throw new RuntimeException('Firebase Cloud Messaging is not configured.');
        $this->accessToken($account, $hotel, true);
    }

    private function account(?Hotel $hotel): ?array
    {
        if (! $hotel) return null;
        if ($hotel->inherits('fcm')) $configuration = data_get($hotel->organization?->credentials, 'fcm');
        else $configuration = HotelIntegration::where('hotel_id', $hotel->id)->where('type', 'fcm')->where('active', true)->first()?->configuration;
        return filled($configuration['project_id'] ?? null) && filled($configuration['client_email'] ?? null) && filled($configuration['private_key'] ?? null) ? $configuration : null;
    }

    private function accessToken(array $account, ?Hotel $hotel, bool $fresh = false): string
    {
        $owner = $hotel?->inherits('fcm') ? 'organization-'.$hotel?->organization_id : 'hotel-'.$hotel?->id;
        $key = 'fcm.oauth-token.'.$owner.'.'.hash('sha256', $account['client_email'].$account['project_id']);
        if ($fresh) Cache::forget($key);
        return Cache::remember($key, 3300, function () use ($account) {
            $now = time(); $uri = $account['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $header = $this->encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $claims = $this->encode(['iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => $uri, 'iat' => $now, 'exp' => $now + 3600]);
            if (! openssl_sign("{$header}.{$claims}", $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) throw new RuntimeException('The Firebase private key is invalid.');
            $jwt = "{$header}.{$claims}.".$this->base64($signature);
            return Http::asForm()->post($uri, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt])->throw()->json('access_token') ?? throw new RuntimeException('Firebase did not return an access token.');
        });
    }

    private function encode(array $value): string { return $this->base64(json_encode($value, JSON_UNESCAPED_SLASHES)); }
    private function base64(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
}

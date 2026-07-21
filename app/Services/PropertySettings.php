<?php

namespace App\Services;

use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PropertySettings
{
    public function update(Request $request, Hotel $hotel): array
    {
        $disk = config('filesystems.asset_disk', 'public');
        $data = $request->validate([
            'display_name' => 'required|string|max:150', 'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'support_email' => 'required|email|max:255', 'support_phone' => 'required|string|max:40', 'email_sender_name' => 'required|string|max:100', 'welcome_message' => 'nullable|string|max:1000',
            'id_requirement' => ['required', Rule::in(['required', 'optional', 'disabled'])], 'id_document_retention_days' => 'sometimes|integer|min:1|max:3650',
            'terms' => 'nullable|string|max:10000', 'privacy_policy' => 'nullable|string|max:10000', 'checkin_message' => 'nullable|string|max:1000', 'checkout_message' => 'nullable|string|max:1000',
            'request_update_message' => 'nullable|string|max:1000', 'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048', 'remove_logo' => 'sometimes|boolean',
        ]);
        $settings = $hotel->settings ?? [];
        if ($request->boolean('remove_logo') && ! empty($settings['logo_path'])) {
            Storage::disk($disk)->delete($settings['logo_path']);
            unset($settings['logo_path']);
        }
        if ($request->hasFile('logo')) {
            if (! empty($settings['logo_path'])) Storage::disk($disk)->delete($settings['logo_path']);
            $settings['logo_path'] = $request->file('logo')->store("hotels/{$hotel->id}/branding", $disk);
        }
        unset($data['logo'], $data['remove_logo']);
        $hotel->update(['settings' => array_merge($settings, ['branding' => $data])]);

        return $data;
    }

    public static function publicData(Hotel $hotel): array
    {
        $disk = config('filesystems.asset_disk', 'public');
        $settings = $hotel->settings ?? [];
        $localBranding = $settings['branding'] ?? [];
        $groupBranding = $hotel->inherits('branding') ? ($hotel->organization?->settings['branding'] ?? []) : [];
        $branding = array_replace($localBranding, $groupBranding);
        $integration = $hotel->integrations()->where('type', 'fcm')->where('active', true)->first();
        $fcm = $hotel->inherits('fcm') ? data_get($hotel->organization?->credentials, 'fcm', []) : ($integration?->configuration ?? []);
        $firebase = filled($fcm['project_id'] ?? null) && filled($fcm['api_key'] ?? null) && filled($fcm['messaging_sender_id'] ?? null) && filled($fcm['android_app_id'] ?? null) && filled($fcm['ios_app_id'] ?? null)
            ? collect($fcm)->only(['project_id', 'api_key', 'messaging_sender_id', 'android_app_id', 'ios_app_id'])->all() : null;

        return [
            'name' => $branding['display_name'] ?? $hotel->name, 'slug' => $hotel->slug,
            'logo_url' => ! empty($settings['logo_path']) ? Storage::disk($disk)->url($settings['logo_path']) : null,
            'primary_color' => $branding['primary_color'] ?? '#1E3A5F', 'accent_color' => $branding['accent_color'] ?? '#D4AF37',
            'support' => ['email' => $branding['support_email'] ?? $settings['contact_email'] ?? null, 'phone' => $branding['support_phone'] ?? $settings['phone'] ?? null],
            'welcome_message' => $branding['welcome_message'] ?? null,
            'policies' => ['id_requirement' => $branding['id_requirement'] ?? 'required', 'id_document_retention_days' => (int) ($branding['id_document_retention_days'] ?? 30), 'terms' => $branding['terms'] ?? null, 'privacy_policy' => $branding['privacy_policy'] ?? null],
            'operating_hours' => ['check_in' => $settings['check_in_time'] ?? null, 'check_out' => $settings['check_out_time'] ?? null],
            'firebase' => $firebase, 'currency' => $hotel->currency, 'timezone' => $hotel->timezone,
            'organization' => $hotel->organization?->only(['id', 'name']), 'inherited' => ['branding' => $hotel->inherits('branding'), 'fcm' => $hotel->inherits('fcm')],
        ];
    }
}

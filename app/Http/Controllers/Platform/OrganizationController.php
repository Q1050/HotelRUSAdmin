<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Platform/Organizations', [
            'organizations' => Organization::with(['hotels:id,organization_id,name,slug,status,organization_inheritance'])
                ->withCount('users')->orderBy('name')->get()->map(fn (Organization $organization) => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'status' => $organization->status,
                    'branding' => $organization->settings['branding'] ?? [],
                    'fcm' => [
                        'configured' => filled(data_get($organization->credentials, 'fcm.project_id')),
                        'projectId' => data_get($organization->credentials, 'fcm.project_id'),
                        'clientEmail' => data_get($organization->credentials, 'fcm.client_email'),
                        'hasPrivateKey' => filled(data_get($organization->credentials, 'fcm.private_key')),
                    ],
                    'administrators' => $organization->users_count,
                    'hotels' => $organization->hotels->map(fn (Hotel $hotel) => [
                        'id' => $hotel->id, 'name' => $hotel->name, 'slug' => $hotel->slug, 'status' => $hotel->status,
                        'inheritBranding' => $hotel->inherits('branding'), 'inheritFcm' => $hotel->inherits('fcm'),
                    ]),
                ]),
            'hotels' => Hotel::orderBy('name')->get(['id', 'organization_id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash', 'max:100', 'unique:organizations,slug'],
        ]);
        Organization::create($data + ['status' => 'active']);

        return back()->with('success', 'Hotel group created.');
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('organizations', 'slug')->ignore($organization)],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'display_name' => ['nullable', 'string', 'max:150'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:40'],
        ]);
        $organization->update([
            'name' => $data['name'], 'slug' => $data['slug'], 'status' => $data['status'],
            'settings' => array_replace_recursive($organization->settings ?? [], ['branding' => collect($data)->only(['display_name', 'primary_color', 'accent_color', 'support_email', 'support_phone'])->all()]),
        ]);

        return back()->with('success', 'Organization profile updated.');
    }

    public function fcm(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'string', 'max:255'],
            'client_email' => ['required', 'email', 'max:255'],
            'private_key' => ['nullable', 'string'],
            'token_uri' => ['nullable', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'messaging_sender_id' => ['nullable', 'string', 'max:255'],
            'android_app_id' => ['nullable', 'string', 'max:255'],
            'ios_app_id' => ['nullable', 'string', 'max:255'],
        ]);
        $existing = data_get($organization->credentials, 'fcm', []);
        if (blank($data['private_key'] ?? null)) {
            $data['private_key'] = $existing['private_key'] ?? null;
        } else {
            $data['private_key'] = str_replace('\\n', "\n", $data['private_key']);
        }
        $credentials = $organization->credentials ?? [];
        $credentials['fcm'] = array_filter($data, fn ($value) => $value !== null && $value !== '');
        $organization->update(['credentials' => $credentials]);

        return back()->with('success', 'Shared Firebase credentials saved securely.');
    }

    public function assign(Request $request, Hotel $hotel): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'inherit_branding' => ['required', 'boolean'],
            'inherit_fcm' => ['required', 'boolean'],
        ]);
        $hotel->update([
            'organization_id' => $data['organization_id'],
            'organization_inheritance' => $data['organization_id'] ? ['branding' => $data['inherit_branding'], 'fcm' => $data['inherit_fcm']] : null,
        ]);

        return back()->with('success', $data['organization_id'] ? 'Property group and inheritance updated.' : 'Property is now independent.');
    }
}

<?php

namespace Tests\Feature;

use App\Models\{Hotel, HotelIntegration, User};
use App\Services\Notifications\FcmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB, Http};
use Tests\TestCase;

class TenantFcmConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.fcm.project_id' => null, 'services.fcm.service_account' => null]);
    }

    public function test_hotel_super_admin_can_store_encrypted_firebase_credentials(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->post(route('dashboard.settings.fcm'), $this->payload())->assertSessionHasNoErrors();

        $integration = HotelIntegration::where('hotel_id', $admin->hotel_id)->where('type', 'fcm')->firstOrFail();
        $this->assertSame('tenant-project', $integration->configuration['project_id']);
        $this->assertSame(trim($this->privateKey()), $integration->configuration['private_key']);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', (string) DB::table('hotel_integrations')->where('id', $integration->id)->value('configuration'));
        $this->assertDatabaseHas('audit_events', ['hotel_id' => $admin->hotel_id, 'action' => 'fcm_configuration_updated']);
    }

    public function test_non_super_admin_cannot_change_firebase_credentials(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)->post(route('dashboard.settings.fcm'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('hotel_integrations', 0);
    }

    public function test_firebase_configuration_is_isolated_by_hotel_and_safe_for_frontend_props(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $otherHotel = Hotel::create(['name' => 'Other Hotel', 'slug' => 'other-hotel', 'status' => 'active', 'timezone' => 'America/Jamaica', 'currency' => 'JMD']);
        $other = User::factory()->create(['hotel_id' => $otherHotel->id, 'role' => 'super_admin']);
        $this->actingAs($admin)->post(route('dashboard.settings.fcm'), $this->payload());

        $client = app(FcmClient::class);
        $this->assertSame('hotel', $client->details($admin->hotel)['source']);
        $this->assertSame('none', $client->details($other->hotel)['source']);
        $this->assertArrayNotHasKey('private_key', $client->details($admin->hotel));
        $this->assertFalse($client->details($other->hotel)['configured']);
    }

    public function test_global_environment_credentials_are_never_used_for_a_hotel(): void
    {
        config(['services.fcm.project_id' => 'global-project', 'services.fcm.service_account' => '/tmp/global-firebase.json']);
        $hotel = Hotel::create(['name' => 'Unconfigured Hotel', 'slug' => 'unconfigured-hotel', 'status' => 'active', 'timezone' => 'America/Jamaica', 'currency' => 'JMD']);

        $details = app(FcmClient::class)->details($hotel);

        $this->assertFalse($details['configured']);
        $this->assertSame('none', $details['source']);
        $this->assertNull($details['project_id']);
    }

    public function test_property_endpoint_exposes_only_safe_mobile_firebase_options(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('dashboard.settings.fcm'), $this->payload());

        $response = $this->withHeader('X-Hotel-Slug', $admin->hotel->slug)->getJson('/api/v1/property')->assertOk();
        $response->assertJsonPath('data.firebase.project_id', 'tenant-project')
            ->assertJsonPath('data.firebase.android_app_id', '1:123456:android:abc')
            ->assertJsonMissingPath('data.firebase.private_key')
            ->assertJsonMissingPath('data.firebase.client_email');
    }

    public function test_platform_admin_can_configure_a_client_hotel(): void
    {
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $hotel = Hotel::create(['name' => 'Client Hotel', 'slug' => 'client-hotel', 'status' => 'active', 'timezone' => 'America/Jamaica', 'currency' => 'JMD']);

        $this->actingAs($platform)->post(route('platform.hotels.onboarding.fcm', $hotel), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('hotel_integrations', ['hotel_id' => $hotel->id, 'type' => 'fcm', 'active' => true]);
    }

    public function test_connection_test_and_message_delivery_use_the_tenant_project(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('dashboard.settings.fcm'), $this->payload());
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'tenant-token'], 200),
            'https://fcm.googleapis.com/v1/projects/tenant-project/messages:send' => Http::response(['name' => 'messages/1'], 200),
        ]);

        $this->post(route('dashboard.settings.fcm.test'))->assertSessionHasNoErrors();
        app(FcmClient::class)->send('device-token', 'Room ready', 'Your room is available.', ['room' => 101], $admin->hotel);

        $this->assertDatabaseHas('hotel_integrations', ['hotel_id' => $admin->hotel_id, 'connection_status' => 'connected']);
        Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/tenant-project/messages:send'
            && $request->hasHeader('Authorization', 'Bearer tenant-token')
            && $request['message']['data']['room'] === '101');
    }

    private function payload(): array
    {
        return ['project_id' => 'tenant-project', 'client_email' => 'firebase-adminsdk@tenant-project.iam.gserviceaccount.com', 'private_key' => $this->privateKey(), 'token_uri' => 'https://oauth2.googleapis.com/token', 'api_key' => 'public-api-key', 'messaging_sender_id' => '123456', 'android_app_id' => '1:123456:android:abc', 'ios_app_id' => '1:123456:ios:def', 'active' => true];
    }

    private function privateKey(): string
    {
        static $privateKey;
        if ($privateKey) return $privateKey;
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $privateKey);
        return $privateKey;
    }
}

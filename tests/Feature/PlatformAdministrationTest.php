<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelFeatureOverride;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_group_a_property_and_enable_shared_profiles(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $hotel = Hotel::firstOrFail();

        $this->actingAs($platformAdmin)->post(route('platform.organizations.store'), ['name' => 'Harbor Collection', 'slug' => 'harbor-collection'])->assertSessionHasNoErrors();
        $organization = Organization::where('slug', 'harbor-collection')->firstOrFail();
        $this->patch(route('platform.organizations.update', $organization), ['name' => 'Harbor Collection', 'slug' => 'harbor-collection', 'status' => 'active', 'display_name' => 'Harbor Hotels', 'primary_color' => '#112233', 'accent_color' => '#DDAA22', 'support_email' => 'support@harbor.test', 'support_phone' => '876-555-0100'])->assertSessionHasNoErrors();
        $this->patch(route('platform.hotels.organization', $hotel), ['organization_id' => $organization->id, 'inherit_branding' => true, 'inherit_fcm' => true])->assertSessionHasNoErrors();

        $hotel->refresh();
        $this->assertTrue($hotel->inherits('branding'));
        $this->assertTrue($hotel->inherits('fcm'));
        $this->assertSame('Harbor Hotels', \App\Services\PropertySettings::publicData($hotel)['name']);
        $this->get(route('platform.organizations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Platform/Organizations')->has('organizations', 1));
    }

    public function test_only_platform_administrators_can_open_control_panel(): void
    {
        $hotelAdmin = User::factory()->create(['is_platform_admin' => false]);
        $this->actingAs($hotelAdmin)->get(route('platform.hotels.index'))->assertForbidden();

        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($platformAdmin)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard/Dashboard'));
        $this->actingAs($platformAdmin)->get(route('platform.overview'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Platform/Overview')->has('stats')->has('plans', 3));
        $this->actingAs($platformAdmin)->get(route('platform.hotels.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Platform/Hotels')->has('hotels')->has('plans', 3));
        $this->actingAs($platformAdmin)->get(route('platform.plans.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Platform/Plans')->has('plans', 3)->has('features', 11));
        $this->actingAs($platformAdmin)->get(route('platform.activity.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Platform/Activity')->has('events'));
    }

    public function test_platform_admin_can_create_a_hotel_subscription_and_first_admin(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $plan = Plan::where('key', 'operations')->firstOrFail();

        $this->actingAs($platformAdmin)->post(route('platform.hotels.store'), [
            'name' => 'Ocean View', 'slug' => 'ocean-view', 'timezone' => 'America/Jamaica', 'currency' => 'jmd', 'plan_id' => $plan->id,
            'admin_first_name' => 'Olivia', 'admin_last_name' => 'Owner', 'admin_email' => 'owner@ocean.test',
            'admin_password' => 'SecurePass123!', 'admin_password_confirmation' => 'SecurePass123!',
        ])->assertSessionHasNoErrors();

        $hotel = Hotel::where('slug', 'ocean-view')->firstOrFail();
        $this->assertDatabaseHas('hotel_subscriptions', ['hotel_id' => $hotel->id, 'plan_id' => $plan->id, 'status' => 'trialing']);
        $this->assertNotNull($hotel->subscription->trial_started_at);
        $this->assertTrue($hotel->subscription->trial_ends_at->isSameDay(now()->addDays(config('subscriptions.trial_days'))));
        $this->assertDatabaseHas('users', ['hotel_id' => $hotel->id, 'email' => 'owner@ocean.test', 'role' => 'super_admin', 'is_platform_admin' => false]);
        $this->assertDatabaseHas('audit_events', ['hotel_id' => $hotel->id, 'action' => 'hotel_created', 'category' => 'platform']);
    }

    public function test_platform_admin_can_override_a_module_and_impersonate_safely(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $hotel = Hotel::create(['name' => 'Support Hotel', 'slug' => 'support-hotel', 'status' => 'active', 'timezone' => 'America/Jamaica', 'currency' => 'JMD']);
        $hotelAdmin = User::factory()->create(['hotel_id' => $hotel->id, 'role' => 'super_admin', 'is_platform_admin' => false]);

        $this->actingAs($platformAdmin)->put(route('platform.hotels.features.update', [$hotel, 'maintenance']), ['mode' => 'enabled'])->assertSessionHasNoErrors();
        $this->assertTrue(HotelFeatureOverride::where('hotel_id', $hotel->id)->where('feature', 'maintenance')->firstOrFail()->enabled);

        $this->post(route('platform.hotels.impersonate', $hotel))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($hotelAdmin);
        $this->assertTrue(session()->has('platform_actor_id'));

        $this->post(route('platform.impersonation.stop'))->assertRedirect(route('platform.hotels.index'));
        $this->assertAuthenticatedAs($platformAdmin);
        $this->assertDatabaseHas('audit_events', ['hotel_id' => $hotel->id, 'action' => 'platform_impersonation_started', 'actor_id' => $platformAdmin->id]);
    }

    public function test_platform_admin_can_complete_property_profile_and_bulk_create_tenant_rooms(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $hotel = Hotel::create(['name' => 'Launch Hotel', 'slug' => 'launch-hotel', 'status' => 'active', 'timezone' => 'America/Jamaica', 'currency' => 'JMD']);
        $plan = Plan::where('key', 'operations')->firstOrFail();
        \App\Models\HotelSubscription::create(['hotel_id' => $hotel->id, 'plan_id' => $plan->id, 'status' => 'active']);
        User::factory()->create(['hotel_id' => $hotel->id, 'role' => 'super_admin']);
        User::factory()->create(['hotel_id' => $hotel->id, 'role' => 'front_desk']);

        $this->actingAs($platformAdmin)->patch(route('platform.hotels.onboarding.profile', $hotel), [
            'contact_email' => 'hotel@example.test', 'phone' => '876-555-0100', 'address' => '1 Beach Road', 'city' => 'Kingston', 'country' => 'Jamaica', 'website' => 'https://example.test', 'check_in_time' => '15:00', 'check_out_time' => '11:00', 'timezone' => 'America/Jamaica', 'currency' => 'JMD',
        ])->assertSessionHasNoErrors();
        $this->post(route('platform.hotels.onboarding.rooms', $hotel), ['prefix' => '', 'start' => 101, 'count' => 3, 'floor' => 1, 'type' => 'Standard', 'price' => 150])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rooms', ['hotel_id' => $hotel->id, 'number' => '101']);
        $this->post(route('platform.hotels.onboarding.launch', $hotel))->assertSessionHasNoErrors();
        $this->assertTrue((bool) $hotel->fresh()->settings['onboarding_completed']);
        $this->get(route('platform.hotels.onboarding', $hotel))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Platform/Onboarding')->where('progress', 100)->where('ready', true)->where('launched', true));
    }

    public function test_platform_admin_can_recover_a_compromised_hotel_administrator(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $hotel = Hotel::create(['name' => 'Recovery Hotel', 'slug' => 'recovery-hotel', 'status' => 'active', 'timezone' => 'America/Jamaica', 'currency' => 'JMD']);
        $compromised = User::factory()->create(['hotel_id' => $hotel->id, 'role' => 'super_admin']);
        $replacement = User::factory()->create(['hotel_id' => $hotel->id, 'role' => 'manager']);
        $this->actingAs($platform)->post(route('platform.hotels.admin-recovery', $hotel), ['compromised_user_id' => $compromised->id, 'replacement_user_id' => $replacement->id, 'platform_password' => 'password', 'incident_reason' => 'Confirmed unauthorized access from an unknown device.', 'confirmation' => 'RECOVER'])->assertSessionHasNoErrors();
        $this->assertSame('suspended', $compromised->fresh()->status);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_type' => User::class, 'tokenable_id' => $compromised->id]);
        $this->assertSame('super_admin', $replacement->fresh()->role);
        $this->assertNull($replacement->fresh()->staff_role_id);
        $this->assertDatabaseHas('audit_events', ['hotel_id' => $hotel->id, 'action' => 'compromised_admin_recovered', 'severity' => 'critical']);
    }
}

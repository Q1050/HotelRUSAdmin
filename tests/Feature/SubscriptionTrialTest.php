<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\SubscriptionTrialNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionTrialTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_becomes_read_only_after_ending_and_expires_after_grace(): void
    {
        $plan = Plan::where('key', 'connected')->firstOrFail();
        $hotel = Hotel::create(['name' => 'Trial Hotel', 'slug' => 'trial-hotel', 'status' => 'active', 'timezone' => 'America/Jamaica', 'currency' => 'JMD']);
        $subscription = HotelSubscription::create(['hotel_id' => $hotel->id, 'plan_id' => $plan->id, 'status' => 'trialing', 'trial_started_at' => now()->subDays(15), 'trial_ends_at' => now()->subDay()]);

        $this->assertTrue($subscription->hasAccess());
        $this->assertTrue($subscription->isReadOnly());
        $this->assertSame('grace', $subscription->lifecycleState());

        $subscription->update(['trial_ends_at' => now()->subDays(config('subscriptions.grace_days') + 1)]);
        $this->assertFalse($subscription->fresh()->hasAccess());
        $this->assertSame('expired', $subscription->fresh()->lifecycleState());
    }

    public function test_trial_processor_transitions_subscription_and_notifies_admin(): void
    {
        Notification::fake();
        $plan = Plan::where('key', 'connected')->firstOrFail();
        $hotel = Hotel::create(['name' => 'Trial Hotel', 'slug' => 'trial-hotel', 'status' => 'active', 'timezone' => 'America/Jamaica', 'currency' => 'JMD']);
        $admin = User::factory()->create(['hotel_id' => $hotel->id, 'role' => 'super_admin', 'status' => 'active']);
        $subscription = HotelSubscription::create(['hotel_id' => $hotel->id, 'plan_id' => $plan->id, 'status' => 'trialing', 'trial_started_at' => now()->subDays(15), 'trial_ends_at' => now()->subHour()]);

        $this->artisan('subscriptions:process-trials')->assertSuccessful();

        $this->assertSame('grace', $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->grace_ends_at);
        Notification::assertSentTo($admin, SubscriptionTrialNotice::class);
    }
}

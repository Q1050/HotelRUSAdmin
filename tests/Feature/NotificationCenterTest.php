<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\GuestNotificationPreference;
use App\Models\NotificationRule;
use App\Models\User;
use App\Notifications\HousekeepingAlert;
use App\Services\Notifications\NotificationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_center_and_save_cross_channel_rules(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $guest = Guest::create(['first_name' => 'Notify', 'last_name' => 'Guest']);
        GuestNotificationPreference::create(['guest_id' => $guest->id, 'booking_updates' => true, 'access_updates' => false]);
        $this->actingAs($manager)->get(route('dashboard.notification-center.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Notifications/Index')->has('rules', count(NotificationRules::EVENTS))->where('guestPreferences.profiles', 1));
        $rules = collect(app(NotificationRules::class)->all())->map(fn ($rule) => ['event_key' => $rule['eventKey'], 'enabled' => true, 'channels' => $rule['channels'], 'recipient_roles' => $rule['recipientRoles'], 'delivery_mode' => 'immediate', 'digest_time' => null, 'quiet_start' => '22:00', 'quiet_end' => '07:00', 'escalation_minutes' => 30, 'escalation_roles' => ['manager'], 'subject_template' => '{{title}}', 'body_template' => '{{body}}'])->all();
        $this->actingAs($manager)->patch(route('dashboard.notification-center.rules'), ['rules' => $rules])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notification_rules', ['event_key' => 'service.updated', 'quiet_start' => '22:00', 'quiet_end' => '07:00']);
    }

    public function test_staff_alert_channels_are_controlled_by_the_property_rule(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        app()->instance('currentHotel', $manager->hotel);
        NotificationRule::create(['event_key' => 'housekeeping.created', 'label' => 'Housekeeping requests', 'enabled' => true, 'channels' => ['dashboard', 'email'], 'delivery_mode' => 'immediate']);
        $notification = new HousekeepingAlert(new \App\Models\HousekeepingTask, 'Room requires attention.');
        $this->assertSame(['database', 'mail'], $notification->via($manager));
        NotificationRule::where('event_key', 'housekeeping.created')->update(['enabled' => false]);
        $this->assertSame([], $notification->via($manager));
    }

    public function test_digest_and_overnight_quiet_hours_schedule_future_delivery(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $hotel = $manager->hotel;
        app()->instance('currentHotel', $hotel);
        $rule = app(NotificationRules::class)->rule('booking.updated', $hotel);
        $rule['deliveryMode'] = 'digest';
        $rule['digestTime'] = now($hotel->timezone)->subHour()->format('H:i');
        $this->assertTrue(app(NotificationRules::class)->nextDelivery($rule, $hotel)->isFuture());
    }
}

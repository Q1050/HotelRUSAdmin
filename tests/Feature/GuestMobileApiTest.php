<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\GuestServiceRequest;
use App\Models\HousekeepingTask;
use App\Models\LockDevice;
use App\Models\MaintenanceWorkOrder;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\Notifications\MobileNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuestMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private string $device = 'a6de7d2f-a75b-4efd-8306-ecad8b55bf41';

    public function test_guest_can_register_and_use_a_device_bound_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', ['first_name' => 'Ana', 'last_name' => 'Stone', 'email' => 'ana@example.com', 'phone' => '555-0100', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'device_id' => $this->device, 'device_name' => 'Ana Phone', 'platform' => 'ios']);
        $response->assertCreated()->assertJsonPath('data.guest.email', 'ana@example.com')->assertJsonStructure(['data' => ['token', 'expires_at']]);
        $token = $response->json('data.token');
        $this->withToken($token)->withHeader('X-Device-ID', $this->device)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.first_name', 'Ana');
        $this->withToken($token)->withHeader('X-Device-ID', (string) Str::uuid())->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_guest_can_rotate_a_valid_device_session_token(): void
    {
        [$guest,$token] = $this->activeGuest();
        $response = $this->auth($token)->postJson('/api/v1/auth/refresh')->assertOk()->assertJsonStructure(['data' => ['token', 'expires_at']]);
        $replacement = $response->json('data.token');
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => hash('sha256', Str::after($token, '|'))]);
        $this->auth($replacement)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $guest->id);
    }

    public function test_guest_can_request_and_complete_a_password_reset(): void
    {
        Notification::fake();
        [$guest] = $this->activeGuest();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $guest->email])->assertOk()->assertJsonPath('message', 'If that guest account exists, a reset code has been sent.');
        Notification::assertSentOnDemand(\App\Notifications\GuestPasswordReset::class);
        $this->assertDatabaseHas('guest_password_resets', ['hotel_id' => $guest->hotel_id, 'email' => $guest->email]);
        DB::table('guest_password_resets')->where('hotel_id', $guest->hotel_id)->where('email', $guest->email)->update(['token' => Hash::make('123456'), 'created_at' => now()]);
        $this->postJson('/api/v1/auth/reset-password', ['email' => $guest->email, 'token' => '000000', 'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', ['email' => $guest->email, 'token' => '123456', 'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123'])->assertOk()->assertJsonPath('message', 'Password reset successfully.');
        $this->assertTrue(Hash::check('new-secret-123', $guest->fresh()->password));
        $this->assertDatabaseMissing('guest_password_resets', ['hotel_id' => $guest->hotel_id, 'email' => $guest->email]);
    }

    public function test_guest_can_list_and_revoke_another_registered_device(): void
    {
        [$guest,$token] = $this->activeGuest();
        $other = (string) Str::uuid();
        $guest->devices()->create(['device_id' => $other, 'name' => 'Old phone', 'platform' => 'android', 'last_seen_at' => now()->subDay()]);
        $otherToken = $guest->createToken("guest-mobile:{$other}", ['guest:mobile'], now()->addHour());
        $this->auth($token)->getJson('/api/v1/devices')->assertOk()->assertJsonCount(2, 'data')->assertJsonFragment(['device_id' => $other, 'name' => 'Old phone']);
        $this->auth($token)->deleteJson('/api/v1/devices/'.$this->device)->assertUnprocessable()->assertJsonPath('message', 'Use Sign out to revoke access for this device.');
        $this->auth($token)->deleteJson('/api/v1/devices/'.$other)->assertOk()->assertJsonPath('message', 'Device access revoked.');
        $this->assertNotNull($guest->devices()->where('device_id', $other)->first()->revoked_at);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'guest_device_revoked', 'category' => 'security']);
    }

    public function test_guest_can_sign_out_all_other_devices(): void
    {
        [$guest,$token] = $this->activeGuest();
        $other = (string) Str::uuid();
        $guest->devices()->create(['device_id' => $other, 'name' => 'Tablet', 'platform' => 'android', 'push_token' => 'push', 'last_seen_at' => now()]);
        $otherToken = $guest->createToken("guest-mobile:{$other}", ['guest:mobile'], now()->addHour());
        $this->auth($token)->deleteJson('/api/v1/devices/others')->assertOk()->assertJsonPath('data.revoked_count', 1);
        $otherDevice = $guest->devices()->where('device_id', $other)->first();
        $this->assertNotNull($otherDevice->revoked_at);
        $this->assertNull($otherDevice->push_token);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
        $this->auth($token)->getJson('/api/v1/me')->assertOk();
        $this->assertDatabaseHas('audit_events', ['action' => 'guest_other_devices_revoked', 'category' => 'security']);
    }

    public function test_guest_can_change_password_and_keep_the_current_device(): void
    {
        [$guest,$token] = $this->activeGuest();
        $other = (string) Str::uuid();
        $guest->devices()->create(['device_id' => $other, 'name' => 'Old phone', 'platform' => 'ios', 'last_seen_at' => now()]);
        $guest->createToken("guest-mobile:{$other}", ['guest:mobile'], now()->addHour());
        $this->auth($token)->putJson('/api/v1/account/password', ['current_password' => 'wrong', 'password' => 'updated-secret', 'password_confirmation' => 'updated-secret'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->auth($token)->putJson('/api/v1/account/password', ['current_password' => 'secret123', 'password' => 'updated-secret', 'password_confirmation' => 'updated-secret'])->assertOk()->assertJsonPath('data.revoked_count', 1);
        $this->assertTrue(Hash::check('updated-secret', $guest->fresh()->password));
        $this->assertNotNull($guest->devices()->where('device_id', $other)->first()->revoked_at);
        $this->assertNull($guest->devices()->where('device_id', $this->device)->first()->revoked_at);
        $this->auth($token)->getJson('/api/v1/me')->assertOk();
        $this->assertDatabaseHas('audit_events', ['action' => 'guest_password_changed', 'category' => 'security']);
    }

    public function test_guest_can_export_data_and_request_reviewed_account_deletion(): void
    {
        [$guest,$token] = $this->activeGuest();
        $this->auth($token)->postJson('/api/v1/privacy/export')->assertOk()->assertJsonPath('data.profile.email', $guest->email)->assertJsonStructure(['data' => ['profile', 'reservations', 'stays', 'service_requests', 'devices']]);
        $this->assertDatabaseHas('guest_privacy_requests', ['guest_id' => $guest->id, 'type' => 'export', 'status' => 'completed']);
        $this->auth($token)->postJson('/api/v1/privacy/deletion', ['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $response = $this->auth($token)->postJson('/api/v1/privacy/deletion', ['password' => 'secret123', 'reason' => 'I no longer need the account'])->assertCreated()->assertJsonPath('data.status', 'pending');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->actingAs($admin)->patch(route('dashboard.privacy-requests.review', $response->json('data.id')), ['decision' => 'approved', 'notes' => 'Identity and active-stay checks completed.'])->assertSessionHasNoErrors();
        $guest->refresh();
        $this->assertSame('deleted', $guest->account_status);
        $this->assertNull($guest->email);
        $this->assertNull($guest->password);
        $this->assertDatabaseHas('guest_privacy_requests', ['id' => $response->json('data.id'), 'status' => 'completed', 'reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'guest_deletion_approved', 'category' => 'privacy']);
    }

    public function test_guest_only_sees_their_current_stay_without_the_room_marker(): void
    {
        [$guest,$token,$room] = $this->activeGuest();
        Checkin::create(['guest_id' => $guest->id, 'room_id' => $room->id, 'check_in_date' => today(), 'check_out_date' => today()->addDay(), 'payment_status' => 'paid', 'booking_reference' => 'BOOK-1', 'is_active' => true]);
        $response = $this->auth($token)->getJson('/api/v1/stays/current')->assertOk()->assertJsonPath('data.room.number', '101');
        $this->assertArrayNotHasKey('access_marker', $response->json('data.room'));
    }

    public function test_mobile_access_requires_the_assigned_room_marker_and_records_a_credential(): void
    {
        [$guest,$token,$room] = $this->activeGuest();
        LockDevice::create(['room_id' => $room->id, 'provider' => 'simulator', 'external_id' => 'LOCK-101', 'name' => 'Room 101']);
        Checkin::create(['guest_id' => $guest->id, 'room_id' => $room->id, 'check_in_date' => today(), 'check_out_date' => today()->addDay(), 'payment_status' => 'paid', 'booking_reference' => 'BOOK-2', 'is_active' => true]);
        $this->auth($token)->postJson('/api/v1/access/credential', ['marker' => (string) Str::uuid(), 'scan_type' => 'qr'])->assertForbidden();
        $this->auth($token)->postJson('/api/v1/access/credential', ['marker' => $room->access_marker, 'scan_type' => 'nfc'])->assertOk()->assertJsonStructure(['data' => ['credential', 'valid_until']]);
        $this->assertDatabaseHas('lock_credentials', ['guest_id' => $guest->id, 'type' => 'mobile', 'status' => 'active']);
        $this->auth($token)->postJson('/api/v1/access/unlock', ['marker' => $room->access_marker, 'scan_type' => 'nfc'])->assertOk()->assertJsonPath('message', 'Unlock command accepted.');
        $this->assertDatabaseHas('lock_commands', ['lock_device_id' => $room->lockDevice->id, 'command' => 'unlock', 'status' => 'completed']);
        $this->assertDatabaseHas('room_events', ['room_id' => $room->id, 'guest_id' => $guest->id, 'event_type' => 'guest_mobile_unlock']);
    }

    public function test_guest_requests_create_operational_work(): void
    {
        [$guest,$token,$room] = $this->activeGuest();
        Checkin::create(['guest_id' => $guest->id, 'room_id' => $room->id, 'check_in_date' => today(), 'check_out_date' => today()->addDay(), 'payment_status' => 'paid', 'booking_reference' => 'BOOK-3', 'is_active' => true]);
        $this->auth($token)->postJson('/api/v1/requests', ['type' => 'linen', 'details' => 'Two extra towels'])->assertCreated();
        $this->assertDatabaseHas('housekeeping_tasks', ['room_id' => $room->id, 'task_type' => 'service', 'status' => 'pending']);
        $this->auth($token)->postJson('/api/v1/requests', ['type' => 'maintenance', 'details' => 'Air conditioner is noisy', 'priority' => 'high'])->assertCreated();
        $this->assertDatabaseHas('maintenance_work_orders', ['room_id' => $room->id, 'title' => 'Guest-reported maintenance', 'priority' => 'high']);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $this->actingAs($manager)->get(route('dashboard.housekeeping.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tasks.0.guestRequest.guestName', trim($guest->first_name.' '.$guest->last_name))
            ->where('tasks.0.guestRequest.type', 'linen')
            ->where('tasks.0.guestRequest.details', 'Two extra towels')
            ->where('tasks.0.guestRequest.priority', 'normal'));
    }

    public function test_staff_progress_is_synchronized_back_to_guest_requests(): void
    {
        [$guest,$token,$room] = $this->activeGuest();
        $checkin = Checkin::create(['guest_id' => $guest->id, 'room_id' => $room->id, 'check_in_date' => today(), 'check_out_date' => today()->addDay(), 'payment_status' => 'paid', 'booking_reference' => 'BOOK-4', 'is_active' => true]);
        $housekeeper = User::factory()->create(['role' => 'housekeeping', 'status' => 'active']);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $task = HousekeepingTask::create(['room_id' => $room->id, 'checkin_id' => $checkin->id, 'task_type' => 'service', 'status' => 'pending', 'priority' => 'normal']);
        $service = GuestServiceRequest::create(['guest_id' => $guest->id, 'checkin_id' => $checkin->id, 'room_id' => $room->id, 'type' => 'linen', 'details' => 'Fresh towels', 'housekeeping_task_id' => $task->id]);
        $this->actingAs($manager)->patch(route('dashboard.housekeeping.update', $task), ['status' => 'in_progress', 'assigned_to' => $housekeeper->id, 'priority' => 'normal', 'notes' => 'Fresh towels', 'checklist' => []])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $service->fresh()->status);

        $order = MaintenanceWorkOrder::create(['room_id' => $room->id, 'category' => 'general', 'title' => 'Guest-reported maintenance', 'description' => 'Noisy fan', 'priority' => 'normal', 'status' => 'open']);
        $maintenanceRequest = GuestServiceRequest::create(['guest_id' => $guest->id, 'checkin_id' => $checkin->id, 'room_id' => $room->id, 'type' => 'maintenance', 'details' => 'Noisy fan', 'maintenance_work_order_id' => $order->id]);
        $this->actingAs($manager)->patch(route('dashboard.maintenance.update', $order), ['status' => 'cancelled', 'assigned_to' => null, 'priority' => 'normal', 'description' => 'Noisy fan', 'cost' => 0])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $maintenanceRequest->fresh()->status);
    }

    public function test_guest_can_register_fcm_token_manage_preferences_and_read_notifications(): void
    {
        config(['services.fcm.project_id' => null, 'services.fcm.service_account' => null]);
        [$guest,$token] = $this->activeGuest();
        $this->auth($token)->putJson('/api/v1/devices/current/push-token', ['push_token' => 'fcm-device-token'])->assertOk();
        $this->assertSame('fcm-device-token', $guest->devices()->first()->push_token);
        $notification = app(MobileNotifier::class)->send($guest, 'booking', 'Room ready', 'Your room is ready.', ['type' => 'checkin_completed', 'reservation_id' => 77]);
        $this->assertSame('configuration_required', $notification->delivery_status);
        $this->auth($token)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.unread', 1)->assertJsonPath('data.0.title', 'Room ready')->assertJsonPath('data.0.data.type', 'checkin_completed')->assertJsonPath('data.0.data.reservation_id', 77);
        $this->auth($token)->patchJson('/api/v1/notifications/'.$notification->id.'/read')->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
        $preferences = ['booking_updates' => true, 'access_updates' => true, 'service_updates' => false, 'checkout_reminders' => true, 'marketing' => false];
        $this->auth($token)->putJson('/api/v1/notification-preferences', $preferences)->assertOk()->assertJsonPath('data.service_updates', false);
        $disabled = app(MobileNotifier::class)->send($guest, 'service', 'Request updated', 'Completed');
        $this->assertSame('disabled', $disabled->delivery_status);
    }

    public function test_guest_can_view_only_their_own_reservation_folio(): void
    {
        [$guest, $token, $room] = $this->activeGuest();
        $reservation = Reservation::create([
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'reference' => 'MOBILE-FOLIO-1',
            'arrival_date' => today(),
            'departure_date' => today()->addDays(2),
            'room_type' => $room->type,
        ]);
        $folio = Folio::forReservation($reservation);
        FolioItem::create([
            'folio_id' => $folio->id,
            'type' => 'room_charge',
            'description' => 'Room charge',
            'quantity' => 1,
            'unit_amount' => 200,
            'tax_amount' => 0,
            'total_amount' => 200,
            'service_date' => today(),
        ]);
        $folio->update(['charges_total' => 200, 'balance' => 200]);

        $this->auth($token)->getJson("/api/v1/reservations/{$reservation->id}/folio")
            ->assertOk()
            ->assertJsonPath('data.balance', 200)
            ->assertJsonPath('data.items.0.description', 'Room charge');

        $otherGuest = Guest::create(['first_name' => 'Other', 'last_name' => 'Guest', 'email' => 'other-folio@example.com']);
        $otherReservation = Reservation::create([
            'guest_id' => $otherGuest->id,
            'reference' => 'MOBILE-FOLIO-2',
            'arrival_date' => today(),
            'departure_date' => today()->addDay(),
        ]);
        $this->auth($token)->getJson("/api/v1/reservations/{$otherReservation->id}/folio")->assertNotFound();
    }

    private function activeGuest(): array
    {
        $guest = Guest::create(['first_name' => 'Ana', 'last_name' => 'Stone', 'email' => 'ana'.Str::random(5).'@example.com', 'password' => Hash::make('secret123'), 'id_status' => 'verified', 'account_status' => 'active']);
        $guest->devices()->create(['device_id' => $this->device, 'name' => 'Phone', 'platform' => 'ios']);
        $token = $guest->createToken("guest-mobile:{$this->device}", ['guest:mobile'], now()->addHour())->plainTextToken;
        $room = Room::create(['number' => '101', 'type' => 'King', 'floor' => 1, 'status' => 'occupied', 'access_marker' => (string) Str::uuid()]);

        return [$guest, $token, $room];
    }

    private function auth(string $token): static
    {
        return $this->withToken($token)->withHeader('X-Device-ID',$this->device);
    }
}

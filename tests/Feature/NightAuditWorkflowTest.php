<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\NightAudit;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NightAuditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_preview_and_close_business_date_with_idempotent_charges(): void
    {
        [$manager, $reservation, $checkin] = $this->activeStay();
        $date = today()->subDay()->toDateString();

        $this->actingAs($manager)->get(route('dashboard.night-audit.index', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('NightAudit/Index')
                ->where('preview.summary.nightlyChargesPending', 1)
                ->where('preview.summary.nightlyRevenuePreview', 175));

        $this->actingAs($manager)->post(route('dashboard.night-audit.close'), ['business_date' => $date])
            ->assertSessionHasNoErrors();

        $this->assertTrue(NightAudit::whereDate('business_date', $date)->where(['status' => 'closed', 'charges_posted' => 1, 'closed_by' => $manager->id])->exists());
        $this->assertDatabaseHas('folio_items', ['idempotency_key' => "nightly:{$checkin->id}:{$date}", 'total_amount' => 175]);
        $this->assertDatabaseHas('night_audit_events', ['action' => 'closed', 'actor_id' => $manager->id]);
        $this->assertSame(1, FolioItem::where('idempotency_key', "nightly:{$checkin->id}:{$date}")->count());

        $this->actingAs($manager)->post(route('dashboard.night-audit.close'), ['business_date' => $date])->assertStatus(422);
    }

    public function test_blockers_require_reason_and_latest_audit_can_be_reopened(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $guest = Guest::create(['first_name' => 'Late', 'last_name' => 'Arrival']);
        Reservation::create(['guest_id' => $guest->id, 'reference' => 'AUDIT-LATE', 'arrival_date' => today()->subDay(), 'departure_date' => today()->addDay(), 'status' => 'confirmed']);
        $date = today()->subDay()->toDateString();

        $this->actingAs($manager)->post(route('dashboard.night-audit.close'), ['business_date' => $date])
            ->assertStatus(422);
        $this->actingAs($manager)->post(route('dashboard.night-audit.close'), ['business_date' => $date, 'override_reason' => 'Arrival desk has confirmed the guest will arrive after midnight.'])
            ->assertSessionHasNoErrors();

        $audit = NightAudit::firstOrFail();
        $this->assertSame('arrival', $audit->exceptions['blockers'][0]['type']);
        $this->actingAs($manager)->patch(route('dashboard.night-audit.reopen', $audit), ['reason' => 'Correcting an authorized late-arrival classification.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('reopened', $audit->fresh()->status);
        $this->assertDatabaseHas('night_audit_events', ['night_audit_id' => $audit->id, 'action' => 'reopened']);
    }

    public function test_front_desk_cannot_access_night_audit(): void
    {
        $frontDesk = User::factory()->create(['role' => 'front_desk', 'status' => 'active']);
        $this->actingAs($frontDesk)->get(route('dashboard.night-audit.index'))->assertForbidden();
    }

    private function activeStay(): array
    {
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $guest = Guest::create(['first_name' => 'Audit', 'last_name' => 'Guest']);
        $room = Room::create(['number' => '701', 'type' => 'King', 'status' => 'occupied', 'price' => 175]);
        $reservation = Reservation::create(['guest_id' => $guest->id, 'room_id' => $room->id, 'reference' => 'AUDIT-STAY', 'arrival_date' => today()->subDays(2), 'departure_date' => today()->addDay(), 'status' => 'checked_in']);
        $checkin = Checkin::create(['reservation_id' => $reservation->id, 'guest_id' => $guest->id, 'room_id' => $room->id, 'check_in_date' => today()->subDays(2), 'check_out_date' => today()->addDay(), 'booking_reference' => $reservation->reference, 'is_active' => true]);

        return [$manager, $reservation, $checkin];
    }
}

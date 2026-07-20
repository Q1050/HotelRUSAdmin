<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\HousekeepingTask;
use App\Models\LockCredential;
use App\Models\Reservation;
use App\Models\ReservationEvent;
use App\Models\Room;
use App\Models\RoomEvent;
use App\Models\StayDeparture;
use App\Services\Finance\PropertyPricing;
use App\Services\Folios\FolioLedger;
use App\Services\Locks\LockManager;
use App\Services\Notifications\MobileNotifier;
use App\Services\ReservationAvailability;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReservationLifecycleController extends Controller
{
    public function reviews(): Response
    {
        return Inertia::render('Bookings/DepartureReviews', ['departures' => StayDeparture::with(['guest', 'room', 'reservation'])->where('financial_resolution', 'pending')->whereNull('financial_reviewed_at')->latest('departed_at')->get()->map(fn ($d) => ['id' => $d->id, 'reservationId' => $d->reservation_id, 'reference' => $d->reservation?->reference, 'guestName' => trim(($d->guest?->first_name ?? '').' '.($d->guest?->last_name ?? '')), 'roomNumber' => $d->room?->number, 'type' => $d->type, 'reason' => $d->reason, 'departedAt' => $d->departed_at?->toISOString(), 'refundAmount' => (float) $d->refund_amount])]);
    }

    public function show(Reservation $reservation): Response
    {
        $reservation->load(['guest', 'room', 'preArrival', 'events.actor']);
        $checkin = $reservation->guest->checkins()->where('reservation_id', $reservation->id)->with('room')->latest()->first();
        $departure = $checkin ? StayDeparture::where('checkin_id', $checkin->id)->first() : null;

        return Inertia::render('Bookings/Detail', ['reservation' => ['id' => $reservation->id, 'reference' => $reservation->reference, 'guestId' => $reservation->guest_id, 'guestName' => trim($reservation->guest->first_name.' '.$reservation->guest->last_name), 'roomId' => $reservation->room_id, 'roomNumber' => $reservation->room?->number, 'roomType' => $reservation->room_type, 'arrivalDate' => $reservation->arrival_date->toDateString(), 'departureDate' => $reservation->departure_date->toDateString(), 'guestCount' => $reservation->guest_count, 'status' => $reservation->status, 'paymentStatus' => $reservation->payment_status, 'totalAmount' => (float) $reservation->total_amount, 'amountPaid' => (float) $reservation->amount_paid, 'source' => $reservation->source, 'groupCode' => $reservation->group_code, 'specialRequests' => $reservation->special_requests, 'createdAt' => $reservation->created_at?->toISOString(), 'noShowAt' => $reservation->no_show_at?->toISOString(), 'noShowFee' => (float) $reservation->no_show_fee], 'checkin' => $checkin ? ['id' => $checkin->id, 'active' => $checkin->is_active, 'roomId' => $checkin->room_id, 'roomNumber' => $checkin->room?->number, 'accessSuspended' => filled($checkin->access_suspended_at)] : null, 'departure' => $departure ? ['id' => $departure->id, 'type' => $departure->type, 'reason' => $departure->reason, 'financialResolution' => $departure->financial_resolution, 'refundAmount' => (float) $departure->refund_amount, 'reviewedAt' => $departure->financial_reviewed_at?->toISOString(), 'reviewNotes' => $departure->financial_review_notes] : null, 'events' => $reservation->events->map(fn ($e) => ['id' => $e->id, 'type' => $e->type, 'description' => $e->description, 'actor' => $e->actor?->name, 'occurredAt' => $e->occurred_at->toISOString(), 'metadata' => $e->metadata]), 'rooms' => Room::where(fn ($q) => $q->where('status', 'available')->orWhere('id', $reservation->room_id))->orderBy('number')->get()->map(fn ($r) => ['id' => $r->id, 'number' => $r->number, 'type' => $r->type, 'status' => $r->status])]);
    }

    public function update(Request $r, Reservation $reservation, ReservationAvailability $availability): RedirectResponse
    {
        abort_unless(in_array($reservation->status, ['pending', 'confirmed']), 422, 'Only upcoming reservations can be edited here.');
        $v = $r->validate(['room_id' => ['nullable', 'exists:rooms,id'], 'arrival_date' => ['required', 'date'], 'departure_date' => ['required', 'date', 'after:arrival_date'], 'guest_count' => ['required', 'integer', 'min:1', 'max:20'], 'room_type' => ['nullable', 'string', 'max:50'], 'payment_status' => ['required', Rule::in(['pending', 'paid', 'failed'])], 'total_amount' => ['required', 'numeric', 'min:0'], 'amount_paid' => ['required', 'numeric', 'min:0'], 'source' => ['required', 'string', 'max:50'], 'group_code' => ['nullable', 'string', 'max:50'], 'special_requests' => ['nullable', 'string', 'max:2000']]);
        if ($v['room_id'] ?? null) {
            $room = Room::findOrFail($v['room_id']);
            abort_if($availability->roomConflict($room, $v['arrival_date'], $v['departure_date'], $reservation->id), 422, 'That room conflicts with another stay or block.');
            $v['room_type'] = $room->type;
        }abort_if($m = $availability->restriction($v['room_type'] ?? null, $v['arrival_date'], $v['departure_date']), 422, $m);
        $before = $reservation->only(array_keys($v));
        $reservation->update($v);
        $this->event($r, $reservation, 'reservation_updated', 'Reservation details were updated.', ['before' => $before, 'after' => $reservation->only(array_keys($v))]);

        return back()->with('success', 'Reservation updated.');
    }

    public function noShow(Request $r, Reservation $reservation, FolioLedger $ledger, PropertyPricing $pricing): RedirectResponse
    {
        abort_unless(in_array($reservation->status, ['pending', 'confirmed']), 422, 'Only an expected arrival can be marked no-show.');
        $v = $r->validate(['fee' => ['required', 'numeric', 'min:0'], 'reason' => ['required', 'string', 'max:1000']]);
        $policy = $pricing->policy('no_show', (float) $reservation->total_amount, today(), $reservation->room_type);
        if (count($policy['breakdown'])) {
            $v['fee'] = $policy['additions'];
        }
        $reservation->update(['status' => 'no_show', 'no_show_at' => now(), 'no_show_fee' => $v['fee']]);
        if ((float) $v['fee'] > 0) {
            $folio = Folio::forReservation($reservation);
            FolioItem::firstOrCreate(['idempotency_key' => "no-show:{$reservation->id}"], ['folio_id' => $folio->id, 'type' => 'no_show', 'description' => "No-show fee for {$reservation->reference}", 'quantity' => 1, 'unit_amount' => $v['fee'], 'tax_amount' => 0, 'total_amount' => $v['fee'], 'service_date' => today(), 'posted_by' => $r->user()->id, 'metadata' => ['reason' => $v['reason']]]);
            $ledger->recalculate($folio);
        }
        $this->event($r, $reservation, 'guest_no_show', 'Reservation marked as no-show.', ['fee' => (float) $v['fee'], 'reason' => $v['reason']]);
        AuditLogger::record($r, 'reservation_no_show', 'guest', 'warning', 'Reservation '.$reservation->reference.' marked no-show.', $reservation, $v['reason'], ['fee' => (float) $v['fee']]);

        return back()->with('success', 'No-show recorded and inventory released.');
    }

    public function changeStay(Request $r, Reservation $reservation, ReservationAvailability $availability, LockManager $locks): RedirectResponse
    {
        $checkin = $reservation->guest->checkins()->where('reservation_id', $reservation->id)->where('is_active', true)->firstOrFail();
        $v = $r->validate(['departure_date' => ['required', 'date', 'after:today'], 'reason' => ['required', 'string', 'max:1000']]);
        $room = Room::findOrFail($checkin->room_id);
        abort_if($availability->roomConflict($room, today(), $v['departure_date'], $reservation->id), 422, 'The room is not available through the requested departure date.');
        $old = $reservation->departure_date->toDateString();
        $types = LockCredential::where('checkin_id', $checkin->id)->where('status', 'active')->pluck('type')->unique();
        $locks->revokeForCheckin($checkin, $r->user());
        $reservation->update(['departure_date' => $v['departure_date']]);
        $checkin->update(['check_out_date' => $v['departure_date']]);
        foreach ($types as $type) {
            $locks->issue($checkin, $type, $r->user());
        }$this->event($r, $reservation, 'stay_dates_changed', "Active stay departure changed from {$old} to {$v['departure_date']}.", ['reason' => $v['reason']]);

        return back()->with('success', 'Stay dates updated and active credentials refreshed.');
    }

    public function transfer(Request $r, Reservation $reservation, ReservationAvailability $availability, LockManager $locks, MobileNotifier $notifier): RedirectResponse
    {
        $checkin = $reservation->guest->checkins()->where('reservation_id', $reservation->id)->where('is_active', true)->firstOrFail();
        $v = $r->validate(['room_id' => ['required', 'exists:rooms,id'], 'reason' => ['required', 'string', 'max:1000']]);
        $oldRoom = $checkin->room;
        $newRoom = Room::lockForUpdate()->findOrFail($v['room_id']);
        abort_unless($newRoom->status === 'available', 422, 'The destination room is not available.');
        abort_if($availability->roomConflict($newRoom, today(), $checkin->check_out_date, $reservation->id), 422, 'The destination room conflicts with another stay or block.');
        $types = LockCredential::where('checkin_id', $checkin->id)->where('status', 'active')->pluck('type')->unique();
        $locks->revokeForCheckin($checkin, $r->user());
        DB::transaction(function () use ($r, $reservation, $checkin, $oldRoom, $newRoom, $v) {
            $oldRoom->update(['status' => 'cleaning', 'lock_status' => 'locked']);
            HousekeepingTask::firstOrCreate(['room_id' => $oldRoom->id, 'checkin_id' => $checkin->id], ['status' => 'pending', 'priority' => 'high', 'notes' => 'Room transfer turnover']);
            $checkin->update(['room_id' => $newRoom->id]);
            $reservation->update(['room_id' => $newRoom->id, 'room_type' => $newRoom->type]);
            $newRoom->update(['status' => 'occupied', 'lock_status' => 'locked']);
            RoomEvent::create(['room_id' => $oldRoom->id, 'guest_id' => $reservation->guest_id, 'checkin_id' => $checkin->id, 'actor_id' => $r->user()->id, 'event_type' => 'guest_transferred_out', 'description' => "Guest transferred to Room {$newRoom->number}. Reason: {$v['reason']}", 'occurred_at' => now()]);
            RoomEvent::create(['room_id' => $newRoom->id, 'guest_id' => $reservation->guest_id, 'checkin_id' => $checkin->id, 'actor_id' => $r->user()->id, 'event_type' => 'guest_transferred_in', 'description' => "Guest transferred from Room {$oldRoom->number}.", 'occurred_at' => now()]);
        });
        $checkin->refresh()->load('room.lockDevice');
        foreach ($types as $type) {
            if ($checkin->room?->lockDevice) {
                $locks->issue($checkin, $type, $r->user());
            }
        }$this->event($r, $reservation, 'room_transferred', "Guest transferred from Room {$oldRoom->number} to Room {$newRoom->number}.", ['reason' => $v['reason']]);
        $notifier->send($reservation->guest, 'booking', 'Your room changed', "You have been moved to Room {$newRoom->number}. Open the app to refresh room access.", ['type' => 'room_transfer', 'reservation_id' => $reservation->id]);

        return back()->with('success', 'Room transfer completed; previous credentials were revoked.');
    }

    public function reviewDeparture(Request $r, StayDeparture $departure): RedirectResponse
    {
        $v = $r->validate(['financial_resolution' => ['required', Rule::in(['no_refund', 'partial_refund', 'full_refund', 'charge_balance', 'settled'])], 'refund_amount' => ['required', 'numeric', 'min:0'], 'notes' => ['required', 'string', 'max:2000']]);
        $departure->update(['financial_resolution' => $v['financial_resolution'], 'refund_amount' => $v['refund_amount'], 'financial_review_notes' => $v['notes'], 'financial_reviewed_at' => now(), 'financial_reviewed_by' => $r->user()->id]);
        if ($departure->reservation_id) {
            $this->event($r, Reservation::findOrFail($departure->reservation_id), 'departure_financial_reviewed', 'Departure financial decision completed.', $v);
        }

        return back()->with('success', 'Departure financial review completed.');
    }

    public function releaseRestriction(Request $r, Guest $guest): RedirectResponse
    {
        $v = $r->validate(['reason' => ['required', 'string', 'max:2000']]);
        abort_unless($guest->do_not_rent_at, 422, 'This guest is not restricted.');
        $old = $guest->do_not_rent_reason;
        $guest->update(['do_not_rent_at' => null, 'do_not_rent_reason' => null]);
        AuditLogger::record($r, 'do_not_rent_released', 'guest', 'critical', 'Do-not-rent restriction released.', $guest, $v['reason'], ['original_reason' => $old]);

        return back()->with('success', 'Do-not-rent restriction released.');
    }

    private function event(Request $r, Reservation $reservation, string $type, string $description, array $metadata = []): void
    {
        ReservationEvent::create(['reservation_id' => $reservation->id, 'actor_id' => $r->user()->id, 'type' => $type, 'description' => $description, 'metadata' => $metadata ?: null, 'occurred_at' => now()]);
    }
}

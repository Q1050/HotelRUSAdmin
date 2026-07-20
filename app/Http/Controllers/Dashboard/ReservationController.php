<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\PreArrivalSubmission;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomEvent;
use App\Notifications\PreArrivalReviewed;
use App\Services\Finance\PropertyPricing;
use App\Services\Folios\FolioLedger;
use App\Services\Notifications\MobileNotifier;
use App\Services\ReservationAvailability;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Bookings', ['reservations' => Reservation::with(['guest', 'room', 'preArrival.reviewer'])->latest('arrival_date')->get()->map(fn ($r) => ['id' => $r->id, 'reference' => $r->reference, 'guestName' => trim($r->guest->first_name.' '.$r->guest->last_name), 'guestId' => $r->guest_id, 'roomNumber' => $r->room?->number, 'arrivalDate' => $r->arrival_date->toDateString(), 'departureDate' => $r->departure_date->toDateString(), 'guestCount' => $r->guest_count, 'roomType' => $r->room_type, 'status' => $r->status, 'paymentStatus' => $r->payment_status, 'totalAmount' => (float) $r->total_amount, 'amountPaid' => (float) $r->amount_paid, 'preArrival' => $r->preArrival ? ['id' => $r->preArrival->id, 'status' => $r->preArrival->status, 'idType' => $r->preArrival->id_type, 'idNumber' => $r->preArrival->id_number, 'estimatedArrivalTime' => $r->preArrival->estimated_arrival_time, 'guestNotes' => $r->preArrival->guest_notes, 'submittedAt' => $r->preArrival->created_at?->toISOString(), 'reviewedBy' => $r->preArrival->reviewer?->name, 'reviewNotes' => $r->preArrival->review_notes, 'hasBack' => $r->preArrival->id_document_back !== null] : null]), 'guests' => Guest::where('account_status', '!=', 'merged')->orderBy('first_name')->get()->map(fn ($g) => ['id' => $g->id, 'name' => trim($g->first_name.' '.$g->last_name), 'idStatus' => $g->id_status, 'hasMobileAccount' => filled($g->password)]), 'rooms' => Room::where('status', 'available')->orderBy('number')->get()->map(fn ($r) => ['id' => $r->id, 'number' => $r->number, 'type' => $r->type])]);
    }

    public function store(Request $request, ReservationAvailability $availability, PropertyPricing $pricing): RedirectResponse
    {
        $v = $request->validate(['guest_id' => ['required', 'exists:guests,id'], 'room_id' => ['nullable', 'exists:rooms,id'], 'arrival_date' => ['required', 'date'], 'departure_date' => ['required', 'date', 'after:arrival_date'], 'guest_count' => ['required', 'integer', 'min:1', 'max:20'], 'room_type' => ['nullable', 'string', 'max:50'], 'payment_status' => ['required', Rule::in(['pending', 'paid', 'failed'])], 'total_amount' => ['required', 'numeric', 'min:0'], 'amount_paid' => ['required', 'numeric', 'min:0'], 'source' => ['required', 'string', 'max:50'], 'special_requests' => ['nullable', 'string', 'max:2000'], 'tax_exempt' => ['sometimes', 'boolean'], 'tax_exemption_reference' => ['nullable', 'string', 'max:150', 'required_if:tax_exempt,true']]);
        if ($v['tax_exempt'] ?? false) {
            abort_unless($request->user()->hasPermission('stays.force_departure'), 403, 'Management approval is required for a tax exemption.');
        }
        abort_if(Guest::findOrFail($v['guest_id'])->do_not_rent_at, 422, 'This guest is on the do-not-rent list.');
        if (filled($v['room_id'] ?? null)) {
            $room = Room::findOrFail($v['room_id']);
            abort_if($availability->roomConflict($room, $v['arrival_date'], $v['departure_date']), 422, 'The room is already reserved or blocked for these dates.');
            $v['room_type'] = $room->type;
        }abort_if($message = $availability->restriction($v['room_type'] ?? null, $v['arrival_date'], $v['departure_date']), 422, $message);
        $nights = max(1, \Carbon\Carbon::parse($v['arrival_date'])->diffInDays(\Carbon\Carbon::parse($v['departure_date'])));
        $nightlyBase = (float) $v['total_amount'] / $nights;
        $quote = $pricing->quote($nightlyBase, \Carbon\Carbon::parse($v['arrival_date']), $v['room_type'] ?? null, $nights, (bool) ($v['tax_exempt'] ?? false));
        if (count($quote['breakdown'])) {
            $v['total_amount'] = $quote['total'];
        }
        Reservation::create([...$v, 'pricing_snapshot' => count($quote['breakdown']) ? $quote : null, 'reference' => 'RS-'.strtoupper(Str::random(8)), 'status' => 'confirmed', 'created_by' => $request->user()->id]);

        return back()->with('success', 'Reservation created.');
    }

    public function checkIn(Request $request, Reservation $reservation, MobileNotifier $notifier, ReservationAvailability $availability): RedirectResponse
    {
        $v = $request->validate(['room_id' => ['required', 'exists:rooms,id']]);
        abort_unless(in_array($reservation->status, ['confirmed', 'pending']), 422, 'Reservation cannot be checked in.');
        abort_if($reservation->guest->do_not_rent_at, 422, 'This guest is on the do-not-rent list.');
        abort_unless($reservation->guest->id_status === 'verified', 422, 'Guest ID must be verified before check-in.');
        abort_if($reservation->payment_status === 'failed', 422, 'Resolve the failed payment before check-in.');
        $checkin = DB::transaction(function () use ($reservation, $v, $request, $availability) {
            $room = Room::lockForUpdate()->findOrFail($v['room_id']);
            abort_unless($room->status === 'available', 422, 'Room is not available.');
            abort_if($availability->roomConflict($room, today(), $reservation->departure_date, $reservation->id), 422, 'The room is reserved or blocked during this stay.');
            $checkin = Checkin::create(['reservation_id' => $reservation->id, 'guest_id' => $reservation->guest_id, 'room_id' => $room->id, 'check_in_date' => today(), 'check_out_date' => $reservation->departure_date, 'payment_status' => $reservation->payment_status, 'booking_reference' => $reservation->reference, 'is_active' => true]);
            $room->update(['status' => 'occupied', 'lock_status' => 'locked']);
            $reservation->update(['room_id' => $room->id, 'room_type' => $room->type, 'status' => 'checked_in']);
            RoomEvent::create(['room_id' => $room->id, 'guest_id' => $reservation->guest_id, 'checkin_id' => $checkin->id, 'actor_id' => $request->user()->id, 'event_type' => 'guest_assigned', 'description' => "Reservation {$reservation->reference} checked into room {$room->number}.", 'occurred_at' => now()]);

            return $checkin;
        });
        $notifier->send($reservation->guest, 'booking', 'Your room is ready', "You are checked in to Room {$checkin->room->number}.", ['type' => 'checkin_completed', 'reservation_id' => $reservation->id, 'room_number' => $checkin->room->number]);

        return redirect()->route('dashboard.guests.show', $checkin->guest_id)->with('success', 'Check-in completed.');
    }

    public function cancel(Request $request, Reservation $reservation, PropertyPricing $pricing, FolioLedger $ledger): RedirectResponse
    {
        abort_if($reservation->status === 'checked_in', 422, 'Check out the active stay before cancelling.');
        $reservation->update(['status' => 'cancelled']);
        $policy = $pricing->policy('cancellation', (float) $reservation->total_amount, today(), $reservation->room_type);
        if ($policy['additions'] > 0) {
            $folio = Folio::forReservation($reservation);
            FolioItem::firstOrCreate(['idempotency_key' => "cancellation:{$reservation->id}"], ['folio_id' => $folio->id, 'type' => 'cancellation', 'description' => "Cancellation fee for {$reservation->reference}", 'quantity' => 1, 'unit_amount' => $policy['additions'], 'tax_amount' => 0, 'total_amount' => $policy['additions'], 'service_date' => today(), 'posted_by' => $request->user()->id, 'metadata' => ['pricing' => $policy]]);
            $ledger->recalculate($folio);
        }

        return back()->with('success', $policy['additions'] > 0 ? 'Reservation cancelled and the configured cancellation fee was posted.' : 'Reservation cancelled.');
    }

    public function reviewPreArrival(Request $request, PreArrivalSubmission $submission, MobileNotifier $notifier): RedirectResponse
    {
        $v = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'review_notes' => ['nullable', 'string', 'max:2000']]);
        abort_if($v['decision'] === 'rejected' && blank($v['review_notes'] ?? null), 422, 'Explain what the guest needs to correct.');
        $submission->update(['status' => $v['decision'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_notes' => $v['review_notes'] ?? null]);
        if ($v['decision'] === 'approved') {
            $submission->guest->update(['id_type' => $submission->id_type, 'id_number' => $submission->id_number, 'id_status' => 'verified']);
        } else {
            $submission->guest->update(['id_status' => 'rejected']);
        }$submission->guest->notify(new PreArrivalReviewed($submission->reservation->reference, $v['decision'], $v['review_notes'] ?? null));
        $notifier->send($submission->guest, 'booking', 'Pre-arrival check-in '.ucfirst($v['decision']), $v['decision'] === 'approved' ? 'Your identity was verified. You are ready for arrival.' : ($v['review_notes'] ?? 'Please update your pre-arrival details.'), ['type' => 'pre_arrival_reviewed', 'reservation_id' => $submission->reservation_id, 'status' => $v['decision']]);
        AuditLogger::record($request, 'pre_arrival_'.$v['decision'], 'guest', 'sensitive', "Pre-arrival submission {$v['decision']} for {$submission->reservation->reference}.", $submission, $v['review_notes'] ?? null);

        return back()->with('success', "Pre-arrival check-in {$v['decision']}.");
    }

    public function document(Request $request, PreArrivalSubmission $submission, string $side)
    {
        abort_unless(in_array($side, ['front', 'back']), 404);
        $path = $side === 'front' ? $submission->id_document_front : $submission->id_document_back;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        AuditLogger::record($request, 'identity_document_viewed', 'guest', 'sensitive', "{$side} identity document viewed for {$submission->reservation->reference}.", $submission);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}

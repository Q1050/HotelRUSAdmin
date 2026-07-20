<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\RoomEvent;
use App\Models\StayDeparture;
use App\Services\Finance\PropertyPricing;
use App\Services\Folios\FolioLedger;
use App\Services\Locks\LockManager;
use App\Services\Notifications\MobileNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckinController extends Controller
{
    public function generateKey(Request $request, Checkin $checkin, LockManager $locks): RedirectResponse
    {
        abort_unless($checkin->is_active && $checkin->room_id, 422, 'An active room assignment is required.');
        abort_if($checkin->access_suspended_at, 422, 'Room access is suspended. A manager must restore it first.');
        $type = $request->validate(['type' => ['sometimes', 'in:mobile,rfid']])['type'] ?? 'mobile';
        $issued = $locks->issue($checkin, $type, $request->user());
        $key = $issued['secret'];
        $checkin->update([
            'access_key_hash' => hash('sha256', $key),
            'access_key_expires_at' => $checkin->check_out_date?->endOfDay() ?? now()->addDay(),
        ]);
        RoomEvent::create(['room_id' => $checkin->room_id, 'guest_id' => $checkin->guest_id, 'checkin_id' => $checkin->id, 'actor_id' => $request->user()?->id, 'event_type' => 'key_issued', 'description' => 'Mobile room access key issued to guest.', 'metadata' => ['expires_at' => $checkin->access_key_expires_at?->toISOString()], 'occurred_at' => now()]);

        return back()->with('generatedKey', $key)->with('success', strtoupper($type).' room credential generated.');
    }

    public function checkout(Request $request, Checkin $checkin, LockManager $locks, MobileNotifier $notifier, PropertyPricing $pricing, FolioLedger $ledger): RedirectResponse
    {
        abort_unless($checkin->is_active, 422, 'This stay has already ended.');
        $data = $request->validate(['departure_type' => ['sometimes', Rule::in(['normal', 'early', 'forced'])], 'reason' => ['nullable', 'string', 'max:2000'], 'financial_resolution' => ['sometimes', Rule::in(['pending', 'no_refund', 'partial_refund', 'full_refund', 'charge_balance'])], 'refund_amount' => ['sometimes', 'numeric', 'min:0'], 'security_involved' => ['sometimes', 'boolean'], 'do_not_rent' => ['sometimes', 'boolean'], 'notes' => ['nullable', 'string', 'max:3000']]);
        $type = $data['departure_type'] ?? 'normal';
        abort_if($type === 'forced' && ! $request->user()->hasPermission('stays.force_departure'), 403);
        abort_if($type === 'forced' && blank($data['reason'] ?? null), 422, 'A reason is required for a forced checkout.');
        abort_if(($data['do_not_rent'] ?? false) && ! $request->user()->hasPermission('stays.force_departure'), 403);
        if ($type === 'early' && $checkin->reservation_id) {
            $reservation = Reservation::find($checkin->reservation_id);
            $policy = $pricing->policy('early_departure', (float) ($reservation?->total_amount ?? 0), today(), $reservation?->room_type);
            if ($reservation && $policy['additions'] > 0) {
                $folio = Folio::forReservation($reservation);
                FolioItem::firstOrCreate(['idempotency_key' => "early-departure:{$checkin->id}"], ['folio_id' => $folio->id, 'type' => 'adjustment', 'description' => "Early departure fee for {$reservation->reference}", 'quantity' => 1, 'unit_amount' => $policy['additions'], 'tax_amount' => 0, 'total_amount' => $policy['additions'], 'service_date' => today(), 'posted_by' => $request->user()->id, 'metadata' => ['pricing' => $policy]]);
                $ledger->recalculate($folio);
            }
        }
        $title = $type === 'forced' ? 'Stay ended by property' : ($type === 'early' ? 'Early checkout complete' : 'Checkout complete');
        $notifier->send($checkin->guest, 'checkout', $title, 'Your stay has ended and room access has been revoked.', ['type' => 'checkout_completed', 'departure_type' => $type, 'checkin_id' => $checkin->id]);
        DB::transaction(function () use ($checkin, $request, $locks, $data, $type) {
            $locks->revokeForCheckin($checkin, $request->user());
            if ($type === 'forced') {
                $checkin->guest?->tokens()->delete();
                $checkin->guest?->devices()->whereNull('revoked_at')->update(['revoked_at' => now(), 'push_token' => null]);
            }
            $checkin->update(['is_active' => false, 'check_out_date' => now()->toDateString(), 'access_suspended_at' => null, 'access_suspension_reason' => null]);
            $checkin->room?->update(['status' => 'cleaning', 'lock_status' => 'locked']);
            if ($checkin->reservation_id) {
                Reservation::whereKey($checkin->reservation_id)->update(['status' => 'checked_out']);
            }
            HousekeepingTask::firstOrCreate(['checkin_id' => $checkin->id], ['room_id' => $checkin->room_id, 'status' => 'pending', 'priority' => 'normal']);
            StayDeparture::create(['checkin_id' => $checkin->id, 'reservation_id' => $checkin->reservation_id, 'guest_id' => $checkin->guest_id, 'room_id' => $checkin->room_id, 'type' => $type, 'reason' => $data['reason'] ?? null, 'financial_resolution' => $data['financial_resolution'] ?? 'pending', 'refund_amount' => $data['refund_amount'] ?? 0, 'security_involved' => $data['security_involved'] ?? false, 'do_not_rent' => $data['do_not_rent'] ?? false, 'notes' => $data['notes'] ?? null, 'departed_at' => now(), 'performed_by' => $request->user()->id]);
            if ($data['do_not_rent'] ?? false) {
                $checkin->guest?->update(['do_not_rent_at' => now(), 'do_not_rent_reason' => $data['reason']]);
            }
            $label = ['normal' => 'Guest checked out', 'early' => 'Guest departed early', 'forced' => 'Guest was forcibly checked out'][$type];
            RoomEvent::create(['room_id' => $checkin->room_id, 'guest_id' => $checkin->guest_id, 'checkin_id' => $checkin->id, 'actor_id' => $request->user()?->id, 'event_type' => $type === 'forced' ? 'guest_evicted' : ($type === 'early' ? 'guest_early_checkout' : 'guest_checked_out'), 'description' => "{$label}; access revoked and room is pending housekeeping.", 'metadata' => ['reason' => $data['reason'] ?? null, 'financial_resolution' => $data['financial_resolution'] ?? 'pending', 'security_involved' => $data['security_involved'] ?? false, 'do_not_rent' => $data['do_not_rent'] ?? false], 'occurred_at' => now()]);
        });

        return back()->with('success', $type === 'forced' ? 'Forced checkout completed and access revoked.' : ($type === 'early' ? 'Early checkout completed.' : 'Guest checked out.'));
    }

    public function suspendAccess(Request $request, Checkin $checkin, LockManager $locks, MobileNotifier $notifier): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        abort_unless($checkin->is_active, 422, 'This stay is not active.');
        DB::transaction(function () use ($request, $checkin, $locks, $data) {
            $locks->revokeForCheckin($checkin, $request->user());
            $checkin->update(['access_suspended_at' => now(), 'access_suspension_reason' => $data['reason']]);
            $checkin->room?->update(['lock_status' => 'locked']);
            RoomEvent::create(['room_id' => $checkin->room_id, 'guest_id' => $checkin->guest_id, 'checkin_id' => $checkin->id, 'actor_id' => $request->user()->id, 'event_type' => 'guest_access_suspended', 'description' => 'Guest room access was suspended by management.', 'metadata' => ['reason' => $data['reason']], 'occurred_at' => now()]);
        });
        $notifier->send($checkin->guest, 'access', 'Room access suspended', 'Please contact the front desk for assistance.', ['type' => 'access_suspended', 'checkin_id' => $checkin->id]);

        return back()->with('success', 'Room access suspended. The stay remains active.');
    }

    public function restoreAccess(Request $request, Checkin $checkin): RedirectResponse
    {
        abort_unless($checkin->is_active && $checkin->access_suspended_at, 422, 'Room access is not currently suspended.');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];
        $checkin->update(['access_suspended_at' => null, 'access_suspension_reason' => null]);
        RoomEvent::create(['room_id' => $checkin->room_id, 'guest_id' => $checkin->guest_id, 'checkin_id' => $checkin->id, 'actor_id' => $request->user()->id, 'event_type' => 'guest_access_restored', 'description' => 'Management restored eligibility for room access. A new credential may now be issued.', 'metadata' => ['reason' => $reason], 'occurred_at' => now()]);

        return back()->with('success', 'Access restored. Issue a new mobile or RFID credential.');
    }
}

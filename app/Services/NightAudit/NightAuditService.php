<?php

namespace App\Services\NightAudit;

use App\Models\AdvancePaymentEvent;
use App\Models\Checkin;
use App\Models\CorporateInvoicePayment;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\FolioPayment;
use App\Models\Hotel;
use App\Models\NightAudit;
use App\Models\NightAuditEvent;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Finance\PropertyPricing;
use App\Services\Folios\FolioLedger;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class NightAuditService
{
    public function __construct(private FolioLedger $ledger, private PropertyPricing $pricing) {}

    public function suggestedDate(Hotel $hotel): string
    {
        $last = NightAudit::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('status', 'closed')->max('business_date');

        return $last ? Carbon::parse($last, $hotel->timezone)->addDay()->toDateString() : now($hotel->timezone)->subDay()->toDateString();
    }

    public function preview(CarbonInterface $date): array
    {
        $arrivals = Reservation::whereDate('arrival_date', '<=', $date)->where('status', 'confirmed')->with('guest:id,first_name,last_name')->get();
        $departures = Checkin::where('is_active', true)->whereDate('check_out_date', '<=', $date)->with(['guest:id,first_name,last_name', 'room:id,number'])->get();
        $roomMismatches = Room::where(function ($query) {
            $query->where(fn ($q) => $q->where('status', 'occupied')->whereDoesntHave('checkins', fn ($c) => $c->where('is_active', true)))
                ->orWhere(fn ($q) => $q->where('status', '!=', 'occupied')->whereHas('checkins', fn ($c) => $c->where('is_active', true)));
        })->get(['id', 'number', 'status']);
        $folios = Folio::where('balance', '!=', 0)->with(['guest:id,first_name,last_name'])->get();
        $eligible = Checkin::where('is_active', true)->whereDate('check_in_date', '<=', $date)->whereDate('check_out_date', '>', $date)->with('room:id,number,price')->get();
        $unposted = $eligible->filter(fn ($stay) => $stay->reservation_id && ! FolioItem::where('idempotency_key', "nightly:{$stay->id}:{$date->toDateString()}")->exists());

        return [
            'businessDate' => $date->toDateString(),
            'summary' => ['arrivalsPending' => $arrivals->count(), 'departuresPending' => $departures->count(), 'roomMismatches' => $roomMismatches->count(), 'unsettledFolios' => $folios->count(), 'nightlyChargesPending' => $unposted->count(), 'nightlyRevenuePreview' => (float) $unposted->sum(fn ($stay) => (float) ($stay->room?->price ?? 0))],
            'blockers' => collect([
                ...$arrivals->map(fn ($r) => ['type' => 'arrival', 'label' => $r->reference, 'detail' => trim($r->guest->first_name.' '.$r->guest->last_name).' has not checked in or been marked no-show.']),
                ...$departures->map(fn ($c) => ['type' => 'departure', 'label' => $c->booking_reference ?: 'Stay #'.$c->id, 'detail' => 'Room '.($c->room?->number ?? '—').' remains occupied after departure.']),
                ...$roomMismatches->map(fn ($room) => ['type' => 'room_status', 'label' => 'Room '.$room->number, 'detail' => 'Room status '.$room->status.' does not match its active stay.']),
            ])->values()->all(),
            'warnings' => $folios->map(fn ($folio) => ['type' => 'folio', 'label' => $folio->number, 'detail' => trim($folio->guest->first_name.' '.$folio->guest->last_name).' has an outstanding balance of '.$folio->currency.' '.$folio->balance.'.', 'folioId' => $folio->id])->values()->all(),
        ];
    }

    public function close(Hotel $hotel, CarbonInterface $date, int $userId, ?string $overrideReason): NightAudit
    {
        return DB::transaction(function () use ($date, $userId, $overrideReason) {
            abort_if(NightAudit::whereDate('business_date', $date)->where('status', 'closed')->lockForUpdate()->exists(), 422, 'This business date is already closed.');
            $latest = NightAudit::where('status', 'closed')->latest('business_date')->first();
            abort_if($latest && $date->lte($latest->business_date), 422, 'Business dates must be closed in order.');
            $preview = $this->preview($date);
            abort_if(count($preview['blockers']) && blank($overrideReason), 422, 'Resolve the blocking exceptions or provide a manager override reason.');
            $posted = $this->postCharges($date);
            $snapshot = $this->snapshot($date, $preview, $posted);
            $audit = NightAudit::updateOrCreate(['business_date' => $date->toDateString()], [...$snapshot, 'status' => 'closed', 'exceptions' => ['blockers' => $preview['blockers'], 'warnings' => $preview['warnings']], 'override_reason' => $overrideReason, 'closed_by' => $userId, 'closed_at' => now(), 'reopened_by' => null, 'reopened_at' => null, 'reopen_reason' => null]);
            NightAuditEvent::create(['night_audit_id' => $audit->id, 'actor_id' => $userId, 'action' => 'closed', 'reason' => $overrideReason, 'snapshot' => $snapshot + ['exceptions' => $audit->exceptions], 'occurred_at' => now()]);

            return $audit;
        });
    }

    public function reopen(NightAudit $audit, int $userId, string $reason): NightAudit
    {
        abort_unless($audit->status === 'closed', 422, 'Only a closed audit can be reopened.');
        abort_unless(NightAudit::where('status', 'closed')->latest('business_date')->value('id') === $audit->id, 422, 'Only the latest closed business date can be reopened.');
        $audit->update(['status' => 'reopened', 'reopened_by' => $userId, 'reopened_at' => now(), 'reopen_reason' => $reason]);
        NightAuditEvent::create(['night_audit_id' => $audit->id, 'actor_id' => $userId, 'action' => 'reopened', 'reason' => $reason, 'snapshot' => $audit->only(['business_date', 'room_revenue', 'payments', 'refunds', 'outstanding_balance']), 'occurred_at' => now()]);

        return $audit;
    }

    public function postCharges(CarbonInterface $date): int
    {
        $posted = 0;
        Checkin::where('is_active', true)->whereDate('check_in_date', '<=', $date)->whereDate('check_out_date', '>', $date)->with(['room', 'guest'])->each(function ($checkin) use ($date, &$posted) {
            if (! $checkin->reservation_id || ! $checkin->room) {
                return;
            }
            $reservation = Reservation::find($checkin->reservation_id);
            if (! $reservation) {
                return;
            }
            $folio = Folio::forReservation($reservation);
            $folio->update(['checkin_id' => $checkin->id]);
            $quote = $this->pricing->quote((float) $checkin->room->price, $date, $checkin->room->type, 1, (bool) $reservation->tax_exempt, ['tax', 'fee'], 'per_night');
            $item = FolioItem::firstOrCreate(['idempotency_key' => "nightly:{$checkin->id}:{$date->toDateString()}"], ['folio_id' => $folio->id, 'type' => 'room_charge', 'description' => "Room {$checkin->room->number} nightly charge", 'quantity' => 1, 'unit_amount' => $checkin->room->price, 'tax_amount' => $quote['additions'], 'total_amount' => $quote['total'], 'service_date' => $date, 'metadata' => ['pricing' => $quote]]);
            if ($item->wasRecentlyCreated) {
                $this->ledger->recalculate($folio);
                $posted++;
            }
        });

        return $posted;
    }

    private function snapshot(CarbonInterface $date, array $preview, int $posted): array
    {
        $folioCollections = (float) FolioPayment::where('type', 'payment')->where('status', 'completed')->where('method', '!=', 'advance_deposit')->whereDate('processed_at', $date)->sum('amount');
        $corporateCollections = (float) CorporateInvoicePayment::where('type', 'payment')->where('method', '!=', 'advance_deposit')->whereDate('processed_at', $date)->sum('amount');
        $advanceCollections = (float) AdvancePaymentEvent::where('type', 'received')->whereDate('occurred_at', $date)->sum('amount');
        $advanceRefunds = (float) AdvancePaymentEvent::where('type', 'refund')->whereDate('occurred_at', $date)->sum('amount');

        return ['charges_posted' => $posted, 'room_revenue' => (float) FolioItem::where('voided', false)->whereDate('service_date', $date)->where('type', 'room_charge')->sum('total_amount'), 'payments' => $folioCollections + $corporateCollections + $advanceCollections, 'refunds' => (float) FolioPayment::where('type', 'refund')->where('status', 'completed')->whereDate('processed_at', $date)->sum('amount') + $advanceRefunds, 'outstanding_balance' => (float) Folio::sum('balance'), 'occupied_rooms' => Checkin::where('is_active', true)->whereDate('check_in_date', '<=', $date)->whereDate('check_out_date', '>', $date)->distinct('room_id')->count('room_id'), 'arrivals' => Reservation::whereDate('arrival_date', $date)->count(), 'departures' => Reservation::whereDate('departure_date', $date)->count(), 'no_shows' => Reservation::whereDate('no_show_at', $date)->count()];
    }
}

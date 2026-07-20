<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CorporateAccount;
use App\Models\CorporateInvoiceItem;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\GroupBooking;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Finance\PropertyPricing;
use App\Services\ReservationAvailability;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GroupBookingController extends Controller
{
    public function index(Request $r): Response
    {
        $selected = $r->integer('group') ? GroupBooking::with(['corporateAccount', 'reservations.guest', 'reservations.room'])->findOrFail($r->integer('group')) : null;
        $accounts = CorporateAccount::withCount(['groups', 'reservations'])->orderBy('name')->get()->map(function ($a) {
            $outstanding = $this->corporateExposure($a);

            return ['id' => $a->id, 'name' => $a->name, 'code' => $a->code, 'status' => $a->status, 'contactName' => $a->contact_name, 'email' => $a->email, 'phone' => $a->phone, 'billingAddress' => $a->billing_address, 'taxNumber' => $a->tax_number, 'creditLimit' => (float) $a->credit_limit, 'paymentTermsDays' => $a->payment_terms_days, 'outstanding' => $outstanding, 'availableCredit' => (float) $a->credit_limit > 0 ? max(0, (float) $a->credit_limit - $outstanding) : null, 'groupsCount' => $a->groups_count, 'reservationsCount' => $a->reservations_count];
        });
        $groups = GroupBooking::with('corporateAccount')->withCount('reservations')->latest('arrival_date')->get()->map(fn ($g) => $this->groupData($g));

        return Inertia::render('Groups/Index', ['accounts' => $accounts, 'groups' => $groups, 'selected' => $selected ? $this->groupDetail($selected) : null, 'guests' => Guest::where('account_status', '!=', 'merged')->orderBy('first_name')->get()->map(fn ($g) => ['id' => $g->id, 'name' => trim($g->first_name.' '.$g->last_name)]), 'rooms' => Room::orderBy('number')->get()->map(fn ($room) => ['id' => $room->id, 'number' => $room->number, 'type' => $room->type, 'status' => $room->status])]);
    }

    public function storeAccount(Request $r): RedirectResponse
    {
        $v = $r->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('corporate_accounts', 'code')->where('hotel_id', app('currentHotel')->id)], 'contact_name' => ['nullable', 'string', 'max:120'], 'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'string', 'max:40'], 'billing_address' => ['nullable', 'string', 'max:1000'], 'tax_number' => ['nullable', 'string', 'max:80'], 'credit_limit' => ['required', 'numeric', 'min:0'], 'payment_terms_days' => ['required', 'integer', 'between:0,365'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $a = CorporateAccount::create([...$v, 'status' => 'active']);
        AuditLogger::record($r, 'corporate_account_created', 'finance', 'sensitive', "Corporate account {$a->name} created.", $a);

        return back()->with('success', 'Corporate account created.');
    }

    public function updateAccount(Request $r, CorporateAccount $account): RedirectResponse
    {
        $v = $r->validate(['status' => ['required', Rule::in(['active', 'on_hold', 'closed'])], 'credit_limit' => ['required', 'numeric', 'min:0'], 'payment_terms_days' => ['required', 'integer', 'between:0,365'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $account->update($v);
        AuditLogger::record($r, 'corporate_account_updated', 'finance', 'sensitive', "Corporate account {$account->name} updated.", $account);

        return back()->with('success', 'Corporate account updated.');
    }

    public function storeGroup(Request $r): RedirectResponse
    {
        $v = $r->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('group_bookings', 'code')->where('hotel_id', app('currentHotel')->id)], 'corporate_account_id' => ['nullable', 'exists:corporate_accounts,id'], 'contact_name' => ['required', 'string', 'max:120'], 'contact_email' => ['nullable', 'email', 'max:150'], 'contact_phone' => ['nullable', 'string', 'max:40'], 'arrival_date' => ['required', 'date'], 'departure_date' => ['required', 'date', 'after:arrival_date'], 'billing_mode' => ['required', Rule::in(['individual', 'group_master', 'corporate'])], 'negotiated_nightly_rate' => ['nullable', 'numeric', 'min:0'], 'room_commitment' => ['required', 'integer', 'min:1', 'max:10000'], 'release_date' => ['nullable', 'date', 'before_or_equal:arrival_date'], 'billing_instructions' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000']]);
        if ($v['billing_mode'] === 'corporate') {
            abort_if(blank($v['corporate_account_id'] ?? null), 422, 'Corporate billing requires a corporate account.');
        }$g = GroupBooking::create([...$v, 'status' => 'tentative', 'created_by' => $r->user()->id]);
        AuditLogger::record($r, 'group_booking_created', 'reservation', 'sensitive', "Group {$g->code} created.", $g);

        return redirect()->route('dashboard.groups.index', ['group' => $g->id])->with('success', 'Group booking created. Add guests to its rooming list.');
    }

    public function addMember(Request $r, GroupBooking $group, ReservationAvailability $availability, PropertyPricing $pricing): RedirectResponse
    {
        abort_if(in_array($group->status, ['cancelled', 'closed']), 422, 'This group no longer accepts reservations.');
        $v = $r->validate(['guest_id' => ['required', 'exists:guests,id'], 'room_id' => ['nullable', 'exists:rooms,id'], 'room_type' => ['nullable', 'string', 'max:50'], 'guest_count' => ['required', 'integer', 'between:1,20'], 'nightly_rate' => ['nullable', 'numeric', 'min:0'], 'billing_responsibility' => ['required', Rule::in(['guest', 'group_master', 'corporate'])], 'special_requests' => ['nullable', 'string', 'max:2000']]);
        $room = filled($v['room_id'] ?? null) ? Room::findOrFail($v['room_id']) : null;
        if ($room) {
            abort_if($availability->roomConflict($room, $group->arrival_date, $group->departure_date), 422, 'That room is not available for the group dates.');
            $v['room_type'] = $room->type;
        }$rate = (float) ($v['nightly_rate'] ?? $group->negotiated_nightly_rate ?? $room?->price ?? 0);
        $nights = max(1, $group->arrival_date->diffInDays($group->departure_date));
        $quote = $pricing->quote($rate, $group->arrival_date, $v['room_type'] ?? null, $nights);
        $account = $group->corporateAccount;
        if ($v['billing_responsibility'] === 'corporate') {
            abort_if(! $account || $account->status !== 'active', 422, 'An active corporate account is required.');
            $outstanding = $this->corporateExposure($account);
            abort_if((float) $account->credit_limit > 0 && $outstanding + $quote['total'] > (float) $account->credit_limit, 422, 'This reservation would exceed the corporate credit limit.');
        }$reservation = Reservation::create(['guest_id' => $v['guest_id'], 'room_id' => $room?->id, 'group_booking_id' => $group->id, 'corporate_account_id' => $account?->id, 'reference' => 'RS-'.strtoupper(Str::random(8)), 'arrival_date' => $group->arrival_date, 'departure_date' => $group->departure_date, 'guest_count' => $v['guest_count'], 'room_type' => $v['room_type'] ?? null, 'status' => $group->status === 'confirmed' ? 'confirmed' : 'pending', 'payment_status' => 'pending', 'total_amount' => $quote['total'], 'amount_paid' => 0, 'source' => 'group', 'group_code' => $group->code, 'special_requests' => $v['special_requests'] ?? null, 'created_by' => $r->user()->id, 'pricing_snapshot' => $quote, 'negotiated_nightly_rate' => $rate, 'billing_responsibility' => $v['billing_responsibility']]);
        AuditLogger::record($r, 'group_member_added', 'reservation', 'sensitive', "{$reservation->reference} added to group {$group->code}.", $reservation);

        return back()->with('success', 'Guest added to the rooming list.');
    }

    public function status(Request $r, GroupBooking $group): RedirectResponse
    {
        $v = $r->validate(['status' => ['required', Rule::in(['tentative', 'confirmed', 'cancelled', 'closed'])], 'reason' => ['nullable', 'string', 'max:1000', 'required_if:status,cancelled']]);
        $group->update(['status' => $v['status']]);
        if ($v['status'] === 'confirmed') {
            $group->reservations()->where('status', 'pending')->update(['status' => 'confirmed']);
        }if ($v['status'] === 'cancelled') {
            $group->reservations()->whereIn('status', ['pending', 'confirmed'])->update(['status' => 'cancelled']);
        }AuditLogger::record($r, 'group_status_changed', 'reservation', 'sensitive', "Group {$group->code} changed to {$v['status']}.", $group, $v['reason'] ?? null);

        return back()->with('success', 'Group status updated.');
    }

    private function groupData(GroupBooking $g): array
    {
        return ['id' => $g->id, 'code' => $g->code, 'name' => $g->name, 'status' => $g->status, 'company' => $g->corporateAccount?->name, 'arrivalDate' => $g->arrival_date->toDateString(), 'departureDate' => $g->departure_date->toDateString(), 'billingMode' => $g->billing_mode, 'roomCommitment' => $g->room_commitment, 'reservationsCount' => $g->reservations_count ?? $g->reservations()->count(), 'negotiatedRate' => (float) $g->negotiated_nightly_rate];
    }

    private function groupDetail(GroupBooking $g): array
    {
        $base = $this->groupData($g);
        $reservations = $g->reservations->map(function ($r) {
            $folio = Folio::where('reservation_id', $r->id)->first();

            return ['id' => $r->id, 'reference' => $r->reference, 'guestName' => trim($r->guest->first_name.' '.$r->guest->last_name), 'roomNumber' => $r->room?->number, 'roomType' => $r->room_type, 'status' => $r->status, 'billingResponsibility' => $r->billing_responsibility, 'nightlyRate' => (float) $r->negotiated_nightly_rate, 'total' => (float) $r->total_amount, 'folioId' => $folio?->id, 'balance' => (float) ($folio?->balance ?? $r->total_amount)];
        });

        return $base + ['contactName' => $g->contact_name, 'contactEmail' => $g->contact_email, 'contactPhone' => $g->contact_phone, 'billingInstructions' => $g->billing_instructions, 'releaseDate' => $g->release_date?->toDateString(), 'reservations' => $reservations, 'bookedRooms' => $reservations->count(), 'totalValue' => (float) $reservations->sum('total'), 'outstanding' => (float) $reservations->sum('balance')];
    }

    private function corporateExposure(CorporateAccount $account): float
    {
        $openInvoices = (float) $account->invoices()->whereNotIn('status', ['paid', 'void'])->sum('balance');
        $stayExposure = (float) Reservation::where('corporate_account_id', $account->id)->whereNotIn('status', ['cancelled', 'no_show'])->get()->sum(function ($reservation) {
            $folio = Folio::where('reservation_id', $reservation->id)->first();

            return $folio ? max(0, (float) $folio->balance) : max(0, (float) $reservation->total_amount - (float) $reservation->amount_paid);
        });
        $invoicedItemIds = CorporateInvoiceItem::whereNotNull('folio_item_id')->pluck('folio_item_id');
        $masterExposure = (float) FolioItem::whereHas('folio.groupBooking', fn ($query) => $query->where('corporate_account_id', $account->id))
            ->where('voided', false)
            ->whereNotIn('id', $invoicedItemIds)
            ->sum('total_amount');

        return $openInvoices + $stayExposure + $masterExposure;
    }
}

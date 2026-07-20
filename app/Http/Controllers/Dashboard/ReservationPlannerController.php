<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\{InventoryBlock, Reservation, Room, RoomRateRule};
use App\Services\ReservationAvailability;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\{Inertia, Response};

class ReservationPlannerController extends Controller
{
    public function index(Request $request): Response
    {
        $view = $request->string('view')->value() === 'month' ? 'month' : 'week';
        $start = Carbon::parse($request->input('start', today()->toDateString()))->startOfDay();
        $days = $view === 'month' ? 28 : 7;
        $end = $start->copy()->addDays($days);
        $dates = collect(CarbonPeriod::create($start, $end->copy()->subDay()))->map(fn ($date) => $date->toDateString())->values();
        $rooms = Room::orderByRaw("CAST(number AS UNSIGNED), number")->get();
        $reservations = Reservation::with(['guest:id,first_name,last_name', 'room:id,number,type'])
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->whereDate('arrival_date', '<', $end)->whereDate('departure_date', '>', $start)->get();
        $blocks = InventoryBlock::with('room:id,number')->where('status', 'active')
            ->whereDate('start_date', '<', $end)->whereDate('end_date', '>', $start)->get();
        $rules = RoomRateRule::whereDate('start_date', '<', $end)->whereDate('end_date', '>=', $start)->orderBy('start_date')->get();
        $types = $rooms->pluck('type')->filter()->unique()->sort()->values();

        $availability = $types->mapWithKeys(function ($type) use ($dates, $rooms, $reservations, $blocks) {
            $capacity = $rooms->where('type', $type)->count();
            return [$type => $dates->mapWithKeys(function ($date) use ($capacity, $type, $reservations, $blocks, $rooms) {
                $next = Carbon::parse($date)->addDay();
                $used = $reservations->filter(fn ($r) => $r->room_type === $type && $r->arrival_date->lt($next) && $r->departure_date->gt($date))->count();
                $typeRoomIds = $rooms->where('type', $type)->pluck('id');
                $held = $blocks->filter(fn ($b) => (($b->room_id && $typeRoomIds->contains($b->room_id)) || (!$b->room_id && $b->room_type === $type)) && $b->start_date->lt($next) && $b->end_date->gt($date))->sum(fn ($b) => $b->room_id ? 1 : $b->quantity);
                return [$date => max(0, $capacity - $used - $held)];
            })];
        });

        $serializeReservation = fn ($r) => ['id' => $r->id, 'reference' => $r->reference, 'guestName' => trim($r->guest->first_name.' '.$r->guest->last_name), 'roomId' => $r->room_id, 'roomNumber' => $r->room?->number, 'roomType' => $r->room_type, 'arrivalDate' => $r->arrival_date->toDateString(), 'departureDate' => $r->departure_date->toDateString(), 'status' => $r->status, 'groupCode' => $r->group_code];

        return Inertia::render('Bookings/Planner', [
            'view' => $view, 'start' => $start->toDateString(), 'end' => $end->copy()->subDay()->toDateString(), 'dates' => $dates,
            'rooms' => $rooms->map(fn ($r) => ['id' => $r->id, 'number' => $r->number, 'type' => $r->type, 'status' => $r->status, 'price' => (float) $r->price]),
            'reservations' => $reservations->map($serializeReservation)->values(),
            'unassigned' => $reservations->whereNull('room_id')->map($serializeReservation)->values(),
            'blocks' => $blocks->map(fn ($b) => ['id' => $b->id, 'roomId' => $b->room_id, 'roomNumber' => $b->room?->number, 'roomType' => $b->room_type, 'name' => $b->name, 'groupCode' => $b->group_code, 'startDate' => $b->start_date->toDateString(), 'endDate' => $b->end_date->toDateString(), 'quantity' => $b->quantity]),
            'rules' => $rules->map(fn ($r) => ['id' => $r->id, 'roomType' => $r->room_type, 'startDate' => $r->start_date->toDateString(), 'endDate' => $r->end_date->toDateString(), 'nightlyRate' => $r->nightly_rate ? (float) $r->nightly_rate : null, 'minimumStay' => $r->minimum_stay, 'closedToArrival' => $r->closed_to_arrival]),
            'roomTypes' => $types, 'availability' => $availability,
            'stats' => ['arrivals' => $reservations->filter(fn ($r) => $r->arrival_date->betweenIncluded($start, $end->copy()->subDay()))->count(), 'departures' => $reservations->filter(fn ($r) => $r->departure_date->betweenIncluded($start, $end->copy()->subDay()))->count(), 'stayOvers' => $reservations->filter(fn ($r) => $r->arrival_date->lt($start) && $r->departure_date->gt($start))->count(), 'unassigned' => $reservations->whereNull('room_id')->count()],
        ]);
    }

    public function move(Request $request, Reservation $reservation, ReservationAvailability $availability): RedirectResponse
    {
        $data = $request->validate(['room_id' => ['required', 'exists:rooms,id'], 'arrival_date' => ['required', 'date'], 'group_code' => ['nullable', 'string', 'max:50']]);
        abort_if(in_array($reservation->status, ['checked_in', 'checked_out', 'cancelled']), 422, 'This reservation can no longer be moved.');
        $duration = $reservation->arrival_date->diffInDays($reservation->departure_date);
        $arrival = Carbon::parse($data['arrival_date']); $departure = $arrival->copy()->addDays($duration);
        DB::transaction(function () use ($reservation, $data, $arrival, $departure, $availability) {
            $room = Room::lockForUpdate()->findOrFail($data['room_id']);
            abort_if(in_array($room->status, ['maintenance', 'out_of_service']), 422, 'This room is out of service.');
            abort_if($availability->roomConflict($room, $arrival, $departure, $reservation->id), 422, 'The room is already reserved or blocked for these dates.');
            abort_if($message = $availability->restriction($room->type, $arrival, $departure), 422, $message);
            $reservation->update(['room_id' => $room->id, 'room_type' => $room->type, 'arrival_date' => $arrival, 'departure_date' => $departure, 'group_code' => $data['group_code'] ?? $reservation->group_code]);
        });
        return back()->with('success', 'Reservation placement updated.');
    }

    public function storeBlock(Request $request, ReservationAvailability $availability): RedirectResponse
    {
        $data = $request->validate(['room_id' => ['nullable', 'exists:rooms,id', 'required_without:room_type'], 'room_type' => ['nullable', 'string', 'max:50', 'required_without:room_id'], 'name' => ['required', 'string', 'max:100'], 'group_code' => ['nullable', 'string', 'max:50'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after:start_date'], 'quantity' => ['required', 'integer', 'min:1', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000']]);
        if (!empty($data['room_id'])) { $room = Room::findOrFail($data['room_id']); abort_if($availability->roomConflict($room, $data['start_date'], $data['end_date']), 422, 'That room already has a reservation or block in this period.'); }
        InventoryBlock::create([...$data, 'created_by' => $request->user()->id]);
        return back()->with('success', 'Inventory block created.');
    }

    public function releaseBlock(InventoryBlock $block): RedirectResponse { $block->update(['status' => 'released']); return back()->with('success', 'Inventory block released.'); }

    public function storeRule(Request $request): RedirectResponse
    {
        $data = $request->validate(['room_type' => ['required', 'string', 'max:50'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'nightly_rate' => ['nullable', 'numeric', 'min:0'], 'minimum_stay' => ['required', 'integer', 'min:1', 'max:365'], 'closed_to_arrival' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:1000']]);
        RoomRateRule::create([...$data, 'created_by' => $request->user()->id]);
        return back()->with('success', 'Rate rule created.');
    }

    public function destroyRule(RoomRateRule $rule): RedirectResponse { $rule->delete(); return back()->with('success', 'Rate rule removed.'); }
}

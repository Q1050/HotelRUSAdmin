<?php

namespace App\Services;

use App\Models\{InventoryBlock, Reservation, Room, RoomRateRule};
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ReservationAvailability
{
    public function roomConflict(Room $room, CarbonInterface|string $arrival, CarbonInterface|string $departure, ?int $exceptReservation = null): bool
    {
        $reservations = Reservation::where('room_id', $room->id)
            ->whereNotIn('status', ['cancelled', 'checked_out', 'no_show'])
            ->whereDate('arrival_date', '<', $departure)
            ->whereDate('departure_date', '>', $arrival);
        if ($exceptReservation) $reservations->whereKeyNot($exceptReservation);

        return $reservations->exists() || InventoryBlock::where('room_id', $room->id)
            ->where('status', 'active')->whereDate('start_date', '<', $departure)->whereDate('end_date', '>', $arrival)->exists();
    }

    public function restriction(?string $roomType, CarbonInterface|string $arrival, CarbonInterface|string $departure): ?string
    {
        if (!$roomType) return null;
        $rules = RoomRateRule::where('room_type', $roomType)->whereDate('start_date', '<', $departure)->whereDate('end_date', '>=', $arrival)->get();
        $arrivalDate = Carbon::parse($arrival); $departureDate = Carbon::parse($departure);
        $nights = $arrivalDate->diffInDays($departureDate);
        if ($rules->contains(fn ($rule) => $rule->closed_to_arrival && $rule->start_date->lte($arrivalDate) && $rule->end_date->gte($arrivalDate))) return 'Arrivals are closed for this room type on the selected date.';
        $minimum = (int) $rules->max('minimum_stay');
        return $minimum > $nights ? "This room type requires a minimum stay of {$minimum} nights." : null;
    }
}

<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use App\Models\Checkin;
use App\Models\Guest;
use App\Models\GuestDevice;
use App\Models\Room;
use App\Models\RoomEvent;
use App\Models\Reservation;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Services\Locks\LockManager;

class GuestController extends Controller
{
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $guests = Guest::query()->when($term !== '', function ($query) use ($term) {
            $query->where(function ($query) use ($term) {
                $query->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%");
            });
        })->latest('id')->limit(20)->get()->map(fn (Guest $guest) => [
            'id' => $guest->id, 'name' => trim(($guest->first_name ?? '').' '.($guest->last_name ?? '')),
            'email' => $guest->email, 'phone' => $guest->phone, 'idStatus' => $guest->id_status,
        ]);
        return response()->json(['guests' => $guests]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'id_type' => ['nullable', 'string', 'max:50'],
            'id_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $guest = Guest::create($validated);
        return redirect()->route('dashboard.guests.show', $guest->id)->with('success', 'Guest created.');
    }

    public function show(Request $request, $id)
    {
        $guest = Guest::query()
            ->with(['checkins' => function ($query) {
                $query->latest('id')->with('room.lockDevice');
            }])
            ->findOrFail($id);

        $latestCheckin = $guest->checkins->first();
        $guestDevices = $guest->devices()->latest('last_seen_at')->get();
        $linkedDevices = GuestDevice::query()
            ->with('guest:id,first_name,last_name,email')
            ->whereIn('device_id', $guestDevices->pluck('device_id'))
            ->where('guest_id', '!=', $guest->id)
            ->get()
            ->groupBy('device_id');

        $availableRooms = Room::query()
            ->where('status', 'available')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(function (Room $room) {
                return [
                    'id' => $room->id,
                    'label' => $room->number ? "Room {$room->number}" : "Room #{$room->id}",
                ];
            })
            ->values();

        return inertia::render('Dashboard/Guests/GuestDetails', [
            'guest' => [
                'id' => $guest->id,
                'name' => trim(($guest->first_name ?? '') . ' ' . ($guest->last_name ?? '')) ?: "Guest #{$guest->id}",
                'email' => $guest->email,
                'phone' => $guest->phone,
                'address' => $guest->address,
                'idStatus' => $guest->id_status,
                'idType' => $guest->id_type,
                'idNumber' => $guest->id_number,
                'roomNumber' => $latestCheckin?->room?->number,
                'notes' => $guest->notes,
                'checkInDate' => optional($latestCheckin?->check_in_date)->toDateString(),
                'checkOutDate' => optional($latestCheckin?->check_out_date)->toDateString(),
                'bookingReference' => $latestCheckin?->booking_reference,
                'checkinId' => $latestCheckin?->id,
                'isActive' => (bool) ($latestCheckin?->is_active ?? false),
                'hasLockDevice' => (bool) $latestCheckin?->room?->lockDevice,
                'accessSuspendedAt' => $latestCheckin?->access_suspended_at?->toISOString(),
                'accessSuspensionReason' => $latestCheckin?->access_suspension_reason,
                'doNotRentAt' => $guest->do_not_rent_at?->toISOString(),
                'doNotRentReason' => $guest->do_not_rent_reason,
                'paymentStatus' => $latestCheckin?->payment_status ? ucfirst($latestCheckin->payment_status) : null,
                'createdAt' => optional($guest->created_at)?->toISOString(),
            ],
            'devices' => $guestDevices->map(function (GuestDevice $device) use ($linkedDevices) {
                return [
                    'id' => $device->id,
                    'deviceId' => $device->device_id,
                    'name' => $device->name,
                    'platform' => $device->platform,
                    'ipAddress' => $device->ip_address,
                    'firstSeenAt' => $device->created_at?->toISOString(),
                    'lastSeenAt' => $device->last_seen_at?->toISOString(),
                    'revokedAt' => $device->revoked_at?->toISOString(),
                    'hasPush' => filled($device->push_token),
                    'linkedGuests' => $linkedDevices->get($device->device_id, collect())->map(fn (GuestDevice $linked) => [
                        'id' => $linked->guest?->id,
                        'name' => trim(($linked->guest?->first_name ?? '').' '.($linked->guest?->last_name ?? '')) ?: 'Guest account',
                        'email' => $linked->guest?->email,
                        'lastSeenAt' => $linked->last_seen_at?->toISOString(),
                        'revokedAt' => $linked->revoked_at?->toISOString(),
                    ])->values(),
                ];
            })->values(),
            'privacyRequests' => $guest->privacyRequests()->latest()->get()->map(fn ($privacy) => [
                'id'=>$privacy->id,'type'=>$privacy->type,'status'=>$privacy->status,'guestReason'=>$privacy->guest_reason,'reviewNotes'=>$privacy->review_notes,'createdAt'=>$privacy->created_at?->toISOString(),'reviewedAt'=>$privacy->reviewed_at?->toISOString(),
            ]),
            'availableRooms' => $availableRooms,
        ]);
    }

    public function verifyId(Request $request, $id): RedirectResponse
    {
        $guest = Guest::query()->findOrFail($id);
        $guest->update([
            'id_status' => 'verified',
        ]);

        return redirect()
            ->route('dashboard.guests.show', ['id' => $guest->id])
            ->with('success', 'Guest ID verified successfully.');
    }

    public function rejectId(Request $request, $id): RedirectResponse
    {
        Guest::query()->findOrFail($id)->update(['id_status' => 'rejected']);
        return back()->with('success', 'Guest ID rejected.');
    }

    public function assignRoom(Request $request, $id, LockManager $locks): RedirectResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
        ]);

        $guest = Guest::query()->findOrFail($id);
        abort_unless($guest->id_status === 'verified', 422, 'Guest ID must be verified before check-in.');
        abort_if($guest->do_not_rent_at, 422, 'This guest is on the do-not-rent list. A manager must review the restriction.');
        DB::transaction(function () use ($guest, $validated, $request, $locks) {
            $room = Room::query()->lockForUpdate()->findOrFail($validated['room_id']);
            if ($room->status !== 'available') {
                throw \Illuminate\Validation\ValidationException::withMessages(['room_id' => 'Selected room is not available.']);
            }
            $active = $guest->checkins()->where('is_active', true)->with('room')->get();
            foreach ($active as $previous) {
                $locks->revokeForCheckin($previous, $request->user());
                $previous->update(['is_active' => false, 'check_out_date' => now()->toDateString()]);
                $previous->room?->update(['status' => 'available', 'lock_status' => 'locked']);
                if ($previous->room) RoomEvent::create(['room_id' => $previous->room_id, 'guest_id' => $guest->id, 'checkin_id' => $previous->id, 'actor_id' => $request->user()?->id, 'event_type' => 'room_released', 'description' => "Room released from {$guest->first_name} {$guest->last_name} during reassignment.", 'occurred_at' => now()]);
            }
            $reservation = Reservation::create(['guest_id'=>$guest->id,'room_id'=>$room->id,'reference'=>'RS-'.strtoupper(Str::random(8)),'arrival_date'=>today(),'departure_date'=>today()->addDay(),'guest_count'=>1,'room_type'=>$room->type,'status'=>'checked_in','payment_status'=>'pending','source'=>'walk_in','created_by'=>$request->user()?->id]);
            $checkin = Checkin::query()->create([
            'reservation_id' => $reservation->id,
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'check_in_date' => now()->toDateString(),
            'payment_status' => 'pending',
            'booking_reference' => $reservation->reference,
            'is_active' => true,
            ]);
            $room->update([
            'status' => 'occupied',
            'lock_status' => 'locked',
            ]);
            RoomEvent::create(['room_id' => $room->id, 'guest_id' => $guest->id, 'checkin_id' => $checkin->id, 'actor_id' => $request->user()?->id, 'event_type' => 'guest_assigned', 'description' => "{$guest->first_name} {$guest->last_name} assigned to room {$room->number}.", 'occurred_at' => now()]);
        });

        return redirect()
            ->route('dashboard.guests.show', ['id' => $guest->id])
            ->with('success', 'Room assigned successfully.');
    }
}

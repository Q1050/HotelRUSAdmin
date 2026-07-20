<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function history(Request $request, Room $room): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $events = $room->events()->with(['guest', 'actor'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhereHas('guest', fn ($guest) => $guest->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', "%{$search}%"));
            }))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('event_type', $type))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('occurred_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('occurred_at', '<=', $to))
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($event) => [
                'id' => $event->id,
                'type' => $event->event_type,
                'description' => $event->description,
                'guestName' => $event->guest ? trim(($event->guest->first_name ?? '').' '.($event->guest->last_name ?? '')) : null,
                'actorName' => $event->actor?->name,
                'occurredAt' => $event->occurred_at?->toISOString(),
            ]);

        return Inertia::render('Dashboard/Rooms/History', [
            'room' => ['id' => $room->id, 'number' => $room->number, 'type' => $room->type, 'floor' => $room->floor],
            'events' => $events,
            'eventTypes' => $room->events()->distinct()->orderBy('event_type')->pluck('event_type'),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $room = Room::create($this->validated($request));
        $this->record($request, $room, 'room_created', "Room {$room->number} created.");
        return back()->with('success', 'Room created.');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $before = $room->only(['number', 'type', 'floor', 'status', 'price', 'last_cleaned_at']);
        $room->update($this->validated($request, $room));
        $changes = collect($room->getChanges())->except(['updated_at'])->all();
        if ($changes) $this->record($request, $room, isset($changes['last_cleaned_at']) ? 'cleaning_recorded' : 'room_updated', isset($changes['last_cleaned_at']) ? 'Room cleaning recorded.' : 'Room details or status updated.', ['before' => $before, 'changes' => $changes]);
        return back()->with('success', 'Room updated.');
    }

    public function toggleLock(Request $request, Room $room): RedirectResponse
    {
        $room->update(['lock_status' => $room->lock_status === 'locked' ? 'unlocked' : 'locked']);
        $this->record($request, $room, 'lock_changed', "Room lock changed to {$room->lock_status}.", ['lock_status' => $room->lock_status]);
        return back()->with('success', 'Room lock updated.');
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:20', Rule::unique('rooms', 'number')->ignore($room)],
            'type' => ['required', 'string', 'max:50'],
            'floor' => ['required', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(['available', 'occupied', 'cleaning'])],
            'price' => ['required', 'numeric', 'min:0'],
            'last_cleaned_at' => ['nullable', 'date'],
        ]);
    }

    private function record(Request $request, Room $room, string $type, string $description, array $metadata = []): void
    {
        RoomEvent::create(['room_id' => $room->id, 'actor_id' => $request->user()?->id, 'event_type' => $type, 'description' => $description, 'metadata' => $metadata ?: null, 'occurred_at' => now()]);
    }
}

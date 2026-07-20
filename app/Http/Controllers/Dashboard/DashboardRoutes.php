<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Resources\DashboardResource;
use App\Models\Guest;
use App\Models\User;
use App\Models\Checkin;
use App\Models\Room;
use App\Models\Reservation;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceWorkOrder;

class DashboardRoutes extends Controller
{
    public function mainScreen(Request $request)
    {
        $user = User::find($request->user()->id);
        return inertia::render('Dashboard/Dashboard', [
          'DashboardModel' => array_merge((new DashboardResource($user))->resolve(), [
              'stats' => [
                  'checkinsToday' => Checkin::whereDate('check_in_date', today())->count(),
                  'pendingVerifications' => Guest::where('id_status', 'pending')->count(),
                  'availableRooms' => Room::where('status', 'available')->count(),
                  'arrivalsToday' => Reservation::whereDate('arrival_date', today())->whereIn('status',['confirmed','pending'])->count(),
                  'departuresToday' => Checkin::whereDate('check_out_date',today())->where('is_active',true)->count(),
                  'roomsCleaning' => Room::where('status','cleaning')->count(),
                  'offlineLocks' => \App\Models\LockDevice::where('status','!=','online')->count(),
                  'housekeepingPending' => HousekeepingTask::whereIn('status',['pending','in_progress','completed'])->count(),
                  'maintenanceOpen' => MaintenanceWorkOrder::whereIn('status',['open','in_progress','repaired'])->count(),
              ],
              'recentCheckins' => Checkin::with(['guest', 'room'])->latest('id')->limit(10)->get()->map(fn (Checkin $checkin) => [
                  'id' => $checkin->guest_id,
                  'name' => trim(($checkin->guest?->first_name ?? '').' '.($checkin->guest?->last_name ?? '')),
                  'email' => $checkin->guest?->email,
                  'checkInTime' => optional($checkin->created_at)?->toISOString(),
                  'idStatus' => $checkin->guest?->id_status ?? 'pending',
                  'roomNumber' => $checkin->room?->number,
              ])->values(),
          ]),
        ]);
    }
    public function GuestScreen(Request $request)
    {
        $guests = Guest::query()
            ->with(['checkins' => function ($query) {
                $query->latest('id')->with('room');
            }])
            ->latest('id')
            ->get()
            ->map(function (Guest $guest) {
                $latestCheckin = $guest->checkins->first();

                return [
                    'id' => $guest->id,
                    'name' => trim(($guest->first_name ?? '') . ' ' . ($guest->last_name ?? '')) ?: "Guest #{$guest->id}",
                    'email' => $guest->email,
                    'phone' => $guest->phone,
                    'idStatus' => $guest->id_status,
                    'checkInDate' => optional($latestCheckin?->check_in_date)->toDateString(),
                    'checkOutDate' => optional($latestCheckin?->check_out_date)->toDateString(),
                    'roomNumber' => $latestCheckin?->room?->number,
                    'createdAt' => optional($guest->created_at)?->toISOString(),
                ];
            })
            ->values();

        return inertia::render('Dashboard/Guests/Guests', [
            'guests' => $guests,
        ]);
    }
    public function RoomsScreen(Request $request)
    {
        $rooms = Room::with([
            'checkins' => fn ($query) => $query->where('is_active', true)->latest('id')->with('guest'),
            'events' => fn ($query) => $query->limit(50)->with(['guest', 'actor']),
            'lockDevice',
            'latestHousekeepingTask.assignee',
            'latestMaintenanceWorkOrder',
        ])
            ->orderBy('number')->get()->map(function (Room $room) {
                $guest = $room->checkins->first()?->guest;
                return [
                    'id' => $room->id, 'roomNumber' => $room->number, 'roomType' => $room->type,
                    'floor' => $room->floor, 'status' => $room->status, 'lockStatus' => $room->lock_status,
                    'housekeepingStatus' => $room->latestHousekeepingTask?->status,
                    'housekeepingAssignee' => $room->latestHousekeepingTask?->assignee?->name,
                    'maintenanceStatus' => $room->latestMaintenanceWorkOrder?->status,
                    'price' => (float) $room->price, 'lastCleaned' => optional($room->last_cleaned_at)?->toDateString(),
                    'guestName' => $guest ? trim(($guest->first_name ?? '').' '.($guest->last_name ?? '')) : null,
                    'history' => $room->events->map(fn ($event) => [
                        'id' => $event->id, 'type' => $event->event_type, 'description' => $event->description,
                        'guestName' => $event->guest ? trim(($event->guest->first_name ?? '').' '.($event->guest->last_name ?? '')) : null,
                        'actorName' => $event->actor?->name, 'occurredAt' => $event->occurred_at?->toISOString(),
                    ])->values(),
                    'device' => $room->lockDevice ? ['id'=>$room->lockDevice->id,'name'=>$room->lockDevice->name,'provider'=>$room->lockDevice->provider,'status'=>$room->lockDevice->status,'batteryLevel'=>$room->lockDevice->battery_level,'lastSeenAt'=>$room->lockDevice->last_seen_at?->toISOString()] : null,
                ];
            })->values();
        return inertia::render('Dashboard/Rooms/Rooms', ['rooms' => $rooms]);
    }
    public function BookingsScreen(Request $request)
    {
        return inertia::render('Bookings');
    }
    public function SettingScreen(Request $request)
    {
        $hotel = $request->user()->hotel;
        $plan = $hotel?->subscription?->plan;

        return inertia::render('Settings', [
          'property'=>\App\Services\PropertySettings::publicData($hotel)+['branding'=>$hotel?->settings['branding']??[]],
          'saas'=>[
            'hotel'=>['name'=>$hotel?->name,'slug'=>$hotel?->slug],
            'plan'=>$plan?->name ?? 'No active plan',
            'subscriptionStatus'=>$hotel?->subscription?->status ?? 'inactive',
            'features'=>collect(\App\Support\Features::ALL)->map(fn(string $feature)=>[
                'key'=>$feature,
                'name'=>str($feature)->replace('_',' ')->title()->toString(),
                'enabled'=>$hotel?->hasFeature($feature) ?? false,
            ])->values(),
            'limits'=>[
                ['name'=>'Rooms','used'=>\App\Models\Room::count(),'limit'=>$hotel?->limit('rooms')],
                ['name'=>'Staff users','used'=>\App\Models\User::count(),'limit'=>$hotel?->limit('staff')],
            ],
          ],
          'notifications'=>[
            ...app(\App\Services\Notifications\FcmClient::class)->details($hotel),
            'fcmConfigured'=>app(\App\Services\Notifications\MobileNotifier::class)->configured($hotel),
            'projectId'=>app(\App\Services\Notifications\FcmClient::class)->details($hotel)['project_id'],
            'registeredDevices'=>\App\Models\GuestDevice::whereNotNull('push_token')->whereNull('revoked_at')->count(),
            'sent'=>\App\Models\MobileNotification::where('delivery_status','sent')->count(),
            'pendingConfiguration'=>\App\Models\MobileNotification::where('delivery_status','configuration_required')->count(),
            'failed'=>\App\Models\MobileNotification::whereIn('delivery_status',['failed','partial'])->count(),
          ],
        ]);
    }
    public function LogoutScreen(Request $request)
    {
        return inertia::render('Logout');
    }
}

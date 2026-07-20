<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\GuestServiceRequest;
use App\Models\HousekeepingTask;
use App\Models\LockCredential;
use App\Models\MaintenanceWorkOrder;
use App\Models\RoomEvent;
use App\Notifications\HousekeepingAlert;
use App\Notifications\MaintenanceAlert;
use App\Services\Locks\LockManager;
use App\Services\Security\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class GuestMobileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $guest = $request->user();

        return response()->json(['data' => ['id' => $guest->id, 'first_name' => $guest->first_name, 'last_name' => $guest->last_name, 'email' => $guest->email, 'phone' => $guest->phone, 'id_status' => $guest->id_status, 'account_status' => $guest->account_status]]);
    }

    public function currentStay(Request $request): JsonResponse
    {
        $stay = $this->activeStay($request, false);

        return response()->json(['data' => $stay ? $this->stayData($stay) : null]);
    }

    public function reservations(Request $request): JsonResponse
    {
        $items = $request->user()->reservations()->with('room:id,number,type,floor')->latest('arrival_date')->paginate(20);

        return response()->json(['data' => $items->items(), 'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()]]);
    }

    public function credential(Request $request, LockManager $locks): JsonResponse
    {
        [$stay,$device,$scan] = $this->accessContext($request);
        $credential = LockCredential::where('checkin_id', $stay->id)->where('type', 'mobile')->where('status', 'active')->where('valid_until', '>', now())->latest()->first();
        if (! $credential || ! $credential->secret_encrypted) {
            $locks->issue($stay, 'mobile', null);
            $credential = LockCredential::where('checkin_id', $stay->id)->where('type', 'mobile')->latest()->first();
        }
        AuditLogger::actor(null, 'guest_credential_retrieved', 'access', 'sensitive', 'Guest retrieved a mobile room credential.', $request->user(), null, ['room_id' => $stay->room_id, 'checkin_id' => $stay->id, 'device_id' => $device->device_id, 'scan_type' => $scan]);

        return response()->json(['data' => ['room_number' => $stay->room->number, 'credential' => $credential->secret_encrypted, 'external_id' => $credential->external_id, 'valid_until' => $credential->valid_until?->toISOString()]]);
    }

    public function unlock(Request $request, LockManager $locks): JsonResponse
    {
        [$stay,$guestDevice,$scan] = $this->accessContext($request);
        $locks->unlock($stay->room->lockDevice, null, "Guest mobile {$scan} scan");
        RoomEvent::create(['room_id' => $stay->room_id, 'guest_id' => $stay->guest_id, 'checkin_id' => $stay->id, 'event_type' => 'guest_mobile_unlock', 'description' => 'Guest unlocked the assigned room from a verified mobile device.', 'metadata' => ['device_id' => $guestDevice->device_id, 'scan_type' => $scan], 'occurred_at' => now()]);
        AuditLogger::actor(null, 'guest_mobile_unlock', 'access', 'sensitive', 'Guest unlocked the assigned room.', $request->user(), null, ['room_id' => $stay->room_id, 'checkin_id' => $stay->id, 'device_id' => $guestDevice->device_id, 'scan_type' => $scan]);

        return response()->json(['message' => 'Unlock command accepted.']);
    }

    public function requests(Request $request): JsonResponse
    {
        $items = $request->user()->serviceRequests()->with(['room:id,number', 'housekeepingTask:id,status', 'maintenanceWorkOrder:id,status'])->latest()->paginate(20);

        return response()->json(['data' => $items->items(), 'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()]]);
    }

    public function createRequest(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => 'required|in:housekeeping,linen,amenity,maintenance', 'details' => 'required|string|max:2000', 'priority' => 'sometimes|in:low,normal,high']);
        $stay = $this->activeStay($request);
        $priority = $data['priority'] ?? 'normal';
        $service = DB::transaction(function () use ($request, $data, $stay, $priority) {
            $service = GuestServiceRequest::create(['guest_id' => $request->user()->id, 'checkin_id' => $stay->id, 'room_id' => $stay->room_id, 'type' => $data['type'], 'priority' => $priority, 'details' => $data['details']]);
            $service->events()->create(['guest_id' => $request->user()->id, 'event_type' => 'created', 'label' => 'Guest created the request.', 'occurred_at' => now()]);
            if ($data['type'] === 'maintenance') {
                $work = MaintenanceWorkOrder::create(['room_id' => $stay->room_id, 'category' => 'general', 'title' => 'Guest-reported maintenance', 'description' => $data['details'], 'priority' => $priority, 'status' => 'open']);
                $service->update(['maintenance_work_order_id' => $work->id]);
                RoomEvent::create(['room_id' => $stay->room_id, 'guest_id' => $stay->guest_id, 'checkin_id' => $stay->id, 'event_type' => 'maintenance_requested', 'description' => 'Guest submitted a maintenance request.', 'occurred_at' => now()]);

                return $service->setRelation('work', $work);
            }
            $task = HousekeepingTask::create(['room_id' => $stay->room_id, 'checkin_id' => $stay->id, 'task_type' => 'service', 'status' => 'pending', 'priority' => $priority, 'notes' => ucfirst($data['type']).': '.$data['details']]);
            $service->update(['housekeeping_task_id' => $task->id]);
            RoomEvent::create(['room_id' => $stay->room_id, 'guest_id' => $stay->guest_id, 'checkin_id' => $stay->id, 'event_type' => 'guest_service_requested', 'description' => 'Guest submitted a '.str_replace('_', ' ', $data['type']).' request.', 'occurred_at' => now()]);

            return $service->setRelation('task', $task);
        });
        if ($service->maintenance_work_order_id) {
            Notification::send(app(\App\Services\Notifications\NotificationRules::class)->staffRecipients('maintenance.created', $stay->hotel), new MaintenanceAlert($service->work, "Guest reported maintenance for Room {$stay->room->number}."));
        } else {
            Notification::send(app(\App\Services\Notifications\NotificationRules::class)->staffRecipients('housekeeping.created', $stay->hotel), new HousekeepingAlert($service->task, "Guest requested service for Room {$stay->room->number}."));
        }
        AuditLogger::actor(null, 'guest_service_requested', 'operations', 'standard', 'Guest submitted a service request.', $request->user(), null, ['room_id' => $stay->room_id, 'checkin_id' => $stay->id, 'request_id' => $service->id, 'type' => $data['type']]);

        return response()->json(['data' => $service->fresh()], 201);
    }

    public function devices(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->devices()->latest('last_seen_at')->get(['device_id', 'name', 'platform', 'last_seen_at', 'revoked_at'])]);
    }

    public function revokeDevice(Request $request, string $deviceId): JsonResponse
    {
        if ($deviceId === $request->header('X-Device-ID')) {
            return response()->json(['message' => 'Use Sign out to revoke access for this device.'], 422);
        }$device = $request->user()->devices()->where('device_id', $deviceId)->firstOrFail();
        $device->update(['revoked_at' => now(), 'push_token' => null]);
        $request->user()->tokens()->where('name', "guest-mobile:{$deviceId}")->delete();
        AuditLogger::actor(null, 'guest_device_revoked', 'security', 'sensitive', 'Guest revoked access for a registered device.', $request->user(), null, ['device_id' => $deviceId]);

        return response()->json(['message' => 'Device access revoked.']);
    }

    public function revokeOtherDevices(Request $request): JsonResponse
    {
        $guest = $request->user();
        $current = $request->header('X-Device-ID');
        $devices = $guest->devices()->where('device_id', '!=', $current)->whereNull('revoked_at');
        $count = $devices->count();
        $devices->update(['revoked_at' => now(), 'push_token' => null]);
        $guest->tokens()->where('name', '!=', "guest-mobile:{$current}")->delete();
        AuditLogger::actor(null, 'guest_other_devices_revoked', 'security', 'sensitive', 'Guest revoked every other registered device.', $guest, null, ['device_count' => $count, 'current_device_id' => $current]);

        return response()->json(['message' => $count ? "Signed out {$count} other device(s)." : 'No other active devices were found.', 'data' => ['revoked_count' => $count]]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate(['current_password' => 'required|string', 'password' => 'required|string|min:8|confirmed']);
        $guest = $request->user();
        if (! $guest->password || ! Hash::check($data['current_password'], $guest->password)) {
            return response()->json(['message' => 'The current password is incorrect.', 'errors' => ['current_password' => ['The current password is incorrect.']]], 422);
        }$guest->update(['password' => Hash::make($data['password'])]);
        $current = $request->header('X-Device-ID');
        $devices = $guest->devices()->where('device_id', '!=', $current)->whereNull('revoked_at');
        $count = $devices->count();
        $devices->update(['revoked_at' => now(), 'push_token' => null]);
        $guest->tokens()->where('name', '!=', "guest-mobile:{$current}")->delete();
        AuditLogger::actor(null, 'guest_password_changed', 'security', 'sensitive', 'Guest changed their account password and revoked other devices.', $guest, null, ['revoked_device_count' => $count, 'current_device_id' => $current]);

        return response()->json(['message' => 'Password updated. Other devices were signed out.', 'data' => ['revoked_count' => $count]]);
    }

    private function activeStay(Request $request, bool $required = true): ?Checkin
    {
        $stay = Checkin::where('guest_id', $request->user()->id)->where('is_active', true)->with(['room.lockDevice'])->latest('check_in_date')->first();
        if ($required && ! $stay) {
            abort(422, 'An active room assignment is required.');
        }

return $stay;
    }

    private function stayData(Checkin $stay): array
    {
        return ['id' => $stay->id, 'booking_reference' => $stay->booking_reference, 'check_in_date' => $stay->check_in_date?->toDateString(), 'check_out_date' => $stay->check_out_date?->toDateString(), 'payment_status' => $stay->payment_status, 'room' => ['number' => $stay->room?->number, 'type' => $stay->room?->type, 'floor' => $stay->room?->floor, 'mobile_access_available' => (bool) $stay->room?->lockDevice]];
    }

    private function accessContext(Request $request): array
    {
        $data = $request->validate(['marker' => 'required|uuid', 'scan_type' => 'required|in:qr,nfc']);
        $stay = $this->activeStay($request);
        $guest = $request->user();
        abort_unless($guest->id_status === 'verified',403,'Your identity must be verified before mobile room access is enabled.');
        abort_if($stay->payment_status === 'failed',403,'Room access is disabled because payment requires attention.');
        abort_unless($stay->room?->lockDevice,422,'No smart lock is paired with this room.');
        abort_unless(hash_equals((string) $stay->room->access_marker,$data['marker']),403,'This room marker does not match your assigned room.');

        return [$stay, $request->attributes->get('guest_device'), $data['scan_type']];
    }
}

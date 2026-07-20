<?php

namespace App\Jobs;

use App\Models\LockDevice;
use App\Notifications\LockHealthAlert;
use App\Services\Locks\LockManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SyncLockDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 45;

    public function __construct(public int $deviceId)
    {
        $this->onQueue('locks');
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(LockManager $manager): void
    {
        $device = LockDevice::withoutGlobalScopes()->findOrFail($this->deviceId);
        app()->instance('currentHotel', $device->hotel);
        $manager->sync($device);
        $device->refresh();

        if ($device->status !== 'online' || $device->battery_level < 25) {
            $recipients = app(\App\Services\Notifications\NotificationRules::class)->staffRecipients('lock.alert', $device->hotel);
            Notification::send($recipients, new LockHealthAlert(
                $device,
                "{$device->name} needs attention: {$device->status}, battery {$device->battery_level}%."
            ));
        }
    }
}

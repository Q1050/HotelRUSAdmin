<?php

namespace App\Jobs;

use App\Models\MobileNotification;
use App\Services\Notifications\MobileNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeliverMobileNotification implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public int $notificationId)
    {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function uniqueId(): string
    {
        return (string) $this->notificationId;
    }

    public function handle(MobileNotifier $notifier): void
    {
        $notifier->deliver($this->notificationId);
    }

    public function failed(Throwable $exception): void
    {
        MobileNotification::withoutGlobalScopes()->find($this->notificationId)?->update([
            'delivery_status' => 'failed',
            'delivery_error' => substr($exception->getMessage(), 0, 2000),
        ]);
    }
}

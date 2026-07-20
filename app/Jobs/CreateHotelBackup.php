<?php

namespace App\Jobs;

use App\Services\Backups\HotelBackupManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateHotelBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public int $hotelId)
    {
        $this->onQueue('backups');
    }

    public function backoff(): array
    {
        return [300];
    }

    public function handle(HotelBackupManager $manager): void
    {
        $manager->create($this->hotelId);
    }
}

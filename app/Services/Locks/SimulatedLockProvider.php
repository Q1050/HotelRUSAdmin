<?php
namespace App\Services\Locks;
use App\Models\LockDevice;
use Illuminate\Support\Str;
class SimulatedLockProvider implements LockProvider {
    public function provision(LockDevice $device, string $type, \DateTimeInterface $from, \DateTimeInterface $until): array { return ['external_id'=>'sim-'.Str::uuid(), 'secret'=>strtoupper(Str::random(8))]; }
    public function revoke(LockDevice $device, string $externalCredentialId): void {}
    public function unlock(LockDevice $device): array { return ['external_id'=>'cmd-'.Str::uuid(), 'status'=>'completed']; }
    public function status(LockDevice $device): array { return ['status'=>'online','battery_level'=>$device->battery_level,'last_seen_at'=>now()]; }
}

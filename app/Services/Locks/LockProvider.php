<?php
namespace App\Services\Locks;
use App\Models\LockDevice;
interface LockProvider {
    public function provision(LockDevice $device, string $type, \DateTimeInterface $from, \DateTimeInterface $until): array;
    public function revoke(LockDevice $device, string $externalCredentialId): void;
    public function unlock(LockDevice $device): array;
    public function status(LockDevice $device): array;
}

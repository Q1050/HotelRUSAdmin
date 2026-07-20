<?php
namespace App\Services\Locks;
use App\Models\{Checkin, LockCommand, LockCredential, LockDevice, LockSyncAttempt, RoomEvent, User};
use App\Services\Security\AuditLogger;
use App\Services\Operations\IntegrationCircuit;
use Illuminate\Support\Facades\Cache;
class LockManager {
    public function __construct(private LockProvider $provider, private IntegrationCircuit $circuit) {}
    public function issue(Checkin $checkin, string $type, ?User $actor): array {
        $device = $checkin->room?->lockDevice; abort_unless($checkin->is_active && $device, 422, 'An active check-in and paired lock are required.');
        $from = now(); $until = $checkin->check_out_date?->endOfDay() ?? now()->addDay(); $issued = $this->provider->provision($device, $type, $from, $until);
        LockCredential::create(['lock_device_id'=>$device->id,'guest_id'=>$checkin->guest_id,'checkin_id'=>$checkin->id,'type'=>$type,'external_id'=>$issued['external_id'],'token_hash'=>hash('sha256',$issued['secret']),'secret_encrypted'=>$issued['secret'],'valid_from'=>$from,'valid_until'=>$until]);
        RoomEvent::create(['room_id'=>$device->room_id,'guest_id'=>$checkin->guest_id,'checkin_id'=>$checkin->id,'actor_id'=>$actor?->id,'event_type'=>'credential_issued','description'=>strtoupper($type).' room credential issued.','occurred_at'=>now()]);
        AuditLogger::actor($actor,'credential_issued','access','sensitive',strtoupper($type).' credential issued for Room '.$device->room?->number.'.',$checkin,null,['room_id'=>$device->room_id,'guest_id'=>$checkin->guest_id,'credential_type'=>$type]);
        return $issued;
    }
    public function unlock(LockDevice $device, ?User $actor, string $reason = 'System operation'): void {
        $guard = 'lock-command:unlock:'.$device->hotel_id.':'.$device->id;
        abort_unless(Cache::add($guard, true, now()->addSeconds(5)), 429, 'An unlock command was just sent to this door. Wait a few seconds before retrying.');
        $command = LockCommand::create(['lock_device_id'=>$device->id,'actor_id'=>$actor?->id,'command'=>'unlock']);
        try { $result=$this->circuit->execute('locks',$device->provider,ucfirst($device->provider).' locks',fn()=>$this->provider->unlock($device)); }
        catch (\Throwable $exception) { Cache::forget($guard); $command->update(['status'=>'failed','response'=>['error'=>$exception->getMessage()],'completed_at'=>now()]); throw $exception; }
        $command->update(['status'=>$result['status'],'external_id'=>$result['external_id'],'response'=>$result,'completed_at'=>now()]);
        RoomEvent::create(['room_id'=>$device->room_id,'actor_id'=>$actor?->id,'event_type'=>'remote_unlock','description'=>'Remote unlock command completed. Reason: '.$reason,'occurred_at'=>now()]);
    }
    public function revokeForCheckin(Checkin $checkin, ?User $actor): void {
        $credentials=LockCredential::where('checkin_id',$checkin->id)->where('status','active')->get();
        foreach($credentials as $credential){ $device=LockDevice::find($credential->lock_device_id); if($device && $credential->external_id) $this->provider->revoke($device,$credential->external_id); $credential->update(['status'=>'revoked','revoked_at'=>now()]); }
        if($credentials->isNotEmpty()){RoomEvent::create(['room_id'=>$checkin->room_id,'guest_id'=>$checkin->guest_id,'checkin_id'=>$checkin->id,'actor_id'=>$actor?->id,'event_type'=>'credentials_revoked','description'=>'All room credentials revoked.','occurred_at'=>now()]);AuditLogger::actor($actor,'credentials_revoked','access','sensitive','All room credentials revoked.',$checkin,null,['room_id'=>$checkin->room_id,'count'=>$credentials->count()]);}
    }
    public function sync(LockDevice $device): void { $attempt=LockSyncAttempt::create(['lock_device_id'=>$device->id,'operation'=>'status_sync','status'=>'running','attempts'=>1]);try{$device->update($this->provider->status($device));$attempt->update(['status'=>'completed','completed_at'=>now()]);}catch(\Throwable$e){$device->update(['sync_status'=>'failed','sync_error'=>$e->getMessage()]);$attempt->update(['status'=>'failed','error'=>$e->getMessage(),'next_retry_at'=>now()->addMinutes(5)]);throw $e;} }
}

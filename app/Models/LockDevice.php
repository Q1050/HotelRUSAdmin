<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LockDevice extends Model { use Concerns\BelongsToHotel;
    protected $fillable = ['hotel_id','room_id','provider','external_id','name','status','battery_level','last_seen_at','metadata','hardware_model','firmware_version','capabilities','sync_status','sync_error','last_synced_at','last_event_at'];
    protected function casts(): array { return ['last_seen_at'=>'datetime','metadata'=>'array','capabilities'=>'array','last_synced_at'=>'datetime','last_event_at'=>'datetime']; }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function credentials(): HasMany { return $this->hasMany(LockCredential::class); }
    public function commands(): HasMany { return $this->hasMany(LockCommand::class); }
    public function syncAttempts(): HasMany { return $this->hasMany(LockSyncAttempt::class); }
}

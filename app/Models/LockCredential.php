<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LockCredential extends Model {
    protected $fillable = ['lock_device_id','guest_id','checkin_id','type','external_id','token_hash','secret_encrypted','status','valid_from','valid_until','revoked_at'];
    protected $hidden=['secret_encrypted','token_hash'];
    protected function casts(): array { return ['secret_encrypted'=>'encrypted','valid_from'=>'datetime','valid_until'=>'datetime','revoked_at'=>'datetime']; }
}

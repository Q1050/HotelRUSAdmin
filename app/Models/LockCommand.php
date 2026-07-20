<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LockCommand extends Model {
    protected $fillable = ['lock_device_id','actor_id','command','status','external_id','response','completed_at'];
    protected function casts(): array { return ['response'=>'array','completed_at'=>'datetime']; }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NightAuditEvent extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'night_audit_id', 'actor_id', 'action', 'reason', 'snapshot', 'occurred_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'occurred_at' => 'datetime'];
    }

    public function audit()
    {
        return $this->belongsTo(NightAudit::class, 'night_audit_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

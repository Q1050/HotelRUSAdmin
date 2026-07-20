<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRule extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'event_key', 'label', 'enabled', 'channels', 'recipient_roles', 'delivery_mode', 'digest_time', 'quiet_start', 'quiet_end', 'escalation_minutes', 'escalation_roles', 'subject_template', 'body_template'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'channels' => 'array', 'recipient_roles' => 'array', 'escalation_roles' => 'array'];
    }
}

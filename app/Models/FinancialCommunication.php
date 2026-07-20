<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialCommunication extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'document_type', 'document_id', 'recipient', 'subject', 'body', 'status', 'attempts', 'dedupe_key', 'metadata', 'scheduled_for', 'sent_at', 'opened_at', 'last_attempt_at', 'last_error', 'created_by'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'scheduled_for' => 'datetime', 'sent_at' => 'datetime', 'opened_at' => 'datetime', 'last_attempt_at' => 'datetime'];
    }
}

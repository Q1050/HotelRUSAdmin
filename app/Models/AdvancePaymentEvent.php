<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvancePaymentEvent extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'advance_payment_id', 'type', 'amount', 'reason', 'occurred_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    public function advancePayment()
    {
        return $this->belongsTo(AdvancePayment::class);
    }
}

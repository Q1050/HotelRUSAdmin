<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdvancePayment extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'guest_id', 'corporate_account_id', 'reservation_id', 'group_booking_id', 'receipt_number', 'method', 'provider', 'external_reference', 'amount', 'allocated_total', 'refunded_total', 'forfeited_total', 'available_balance', 'status', 'notes', 'received_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'allocated_total' => 'decimal:2', 'refunded_total' => 'decimal:2', 'forfeited_total' => 'decimal:2', 'available_balance' => 'decimal:2', 'received_at' => 'datetime'];
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function corporateAccount()
    {
        return $this->belongsTo(CorporateAccount::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function groupBooking()
    {
        return $this->belongsTo(GroupBooking::class);
    }

    public function allocations()
    {
        return $this->hasMany(AdvancePaymentAllocation::class);
    }

    public function events()
    {
        return $this->hasMany(AdvancePaymentEvent::class);
    }

    public static function nextReceiptNumber(): string
    {
        return 'DEP-'.now()->format('Ym').'-'.strtoupper(Str::random(6));
    }

    public function refreshTotals(): void
    {
        $available = max(0, round((float) $this->amount - (float) $this->allocated_total - (float) $this->refunded_total - (float) $this->forfeited_total, 2));
        $this->update(['available_balance' => $available, 'status' => $available > 0 ? ((float) $this->allocated_total > 0 || (float) $this->refunded_total > 0 || (float) $this->forfeited_total > 0 ? 'partially_used' : 'available') : 'closed']);
    }
}

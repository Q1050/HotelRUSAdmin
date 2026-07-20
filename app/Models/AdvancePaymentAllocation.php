<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvancePaymentAllocation extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'advance_payment_id', 'folio_id', 'corporate_invoice_id', 'folio_payment_id', 'corporate_invoice_payment_id', 'amount', 'notes', 'allocated_at', 'allocated_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'allocated_at' => 'datetime'];
    }

    public function advancePayment()
    {
        return $this->belongsTo(AdvancePayment::class);
    }

    public function folio()
    {
        return $this->belongsTo(Folio::class);
    }

    public function corporateInvoice()
    {
        return $this->belongsTo(CorporateInvoice::class);
    }
}

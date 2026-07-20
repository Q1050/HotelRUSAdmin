<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateInvoicePayment extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'corporate_invoice_id', 'type', 'method', 'amount', 'reference', 'notes', 'processed_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'processed_at' => 'datetime'];
    }

    public function invoice()
    {
        return $this->belongsTo(CorporateInvoice::class, 'corporate_invoice_id');
    }
}

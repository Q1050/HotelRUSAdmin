<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateInvoiceItem extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'corporate_invoice_id', 'folio_item_id', 'reservation_id', 'description', 'amount', 'tax_amount', 'total_amount', 'metadata'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'metadata' => 'array'];
    }

    public function invoice()
    {
        return $this->belongsTo(CorporateInvoice::class, 'corporate_invoice_id');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}

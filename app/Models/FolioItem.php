<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FolioItem extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'folio_id', 'type', 'description', 'quantity', 'unit_amount', 'tax_amount', 'total_amount', 'service_date', 'idempotency_key', 'voided', 'void_reason', 'voided_at', 'voided_by', 'posted_by', 'metadata', 'transferred_to_folio_id', 'transferred_at', 'transferred_by'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'service_date' => 'date', 'voided' => 'boolean', 'voided_at' => 'datetime', 'transferred_at' => 'datetime', 'metadata' => 'array'];
    }

    public function folio()
    {
        return $this->belongsTo(Folio::class);
    }
}

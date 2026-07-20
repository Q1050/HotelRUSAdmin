<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialRule extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'name', 'type', 'calculation', 'amount', 'application', 'room_type', 'price_inclusive', 'tax_exemptible', 'active', 'effective_from', 'effective_until', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'price_inclusive' => 'boolean', 'tax_exemptible' => 'boolean', 'active' => 'boolean', 'effective_from' => 'date', 'effective_until' => 'date'];
    }
}

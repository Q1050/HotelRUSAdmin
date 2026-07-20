<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateAccount extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'name', 'code', 'status', 'contact_name', 'email', 'phone', 'billing_address', 'tax_number', 'credit_limit', 'payment_terms_days', 'notes'];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2'];
    }

    public function groups()
    {
        return $this->hasMany(GroupBooking::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function invoices()
    {
        return $this->hasMany(CorporateInvoice::class);
    }
}

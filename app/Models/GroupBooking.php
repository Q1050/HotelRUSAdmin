<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupBooking extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'corporate_account_id', 'code', 'name', 'status', 'contact_name', 'contact_email', 'contact_phone', 'arrival_date', 'departure_date', 'billing_mode', 'negotiated_nightly_rate', 'room_commitment', 'release_date', 'billing_instructions', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['arrival_date' => 'date', 'departure_date' => 'date', 'release_date' => 'date', 'negotiated_nightly_rate' => 'decimal:2'];
    }

    public function corporateAccount()
    {
        return $this->belongsTo(CorporateAccount::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

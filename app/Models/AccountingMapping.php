<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingMapping extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'accounting_profile_id', 'key', 'account_code', 'account_name'];

    public function profile()
    {
        return $this->belongsTo(AccountingProfile::class, 'accounting_profile_id');
    }
}

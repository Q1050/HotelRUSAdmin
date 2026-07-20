<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingExportBatch extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'accounting_profile_id', 'night_audit_id', 'reversal_of_id', 'batch_number', 'business_date', 'status', 'debit_total', 'credit_total', 'checksum', 'error', 'generated_by', 'generated_at', 'posted_by', 'posted_at'];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'debit_total' => 'decimal:2', 'credit_total' => 'decimal:2', 'generated_at' => 'datetime', 'posted_at' => 'datetime'];
    }

    public function profile()
    {
        return $this->belongsTo(AccountingProfile::class, 'accounting_profile_id');
    }

    public function entries()
    {
        return $this->hasMany(AccountingExportEntry::class);
    }

    public function nightAudit()
    {
        return $this->belongsTo(NightAudit::class);
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}

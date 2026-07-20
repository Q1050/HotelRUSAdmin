<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingSyncRun extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'accounting_profile_id', 'accounting_export_batch_id', 'requested_by', 'provider', 'direction', 'idempotency_key', 'status', 'external_reference', 'message', 'records_sent', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function batch()
    {
        return $this->belongsTo(AccountingExportBatch::class, 'accounting_export_batch_id');
    }
}

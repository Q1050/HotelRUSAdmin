<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingExportEntry extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'accounting_export_batch_id', 'line_number', 'account_key', 'account_code', 'account_name', 'description', 'reference', 'debit', 'credit', 'metadata'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'metadata' => 'array'];
    }

    public function batch()
    {
        return $this->belongsTo(AccountingExportBatch::class, 'accounting_export_batch_id');
    }
}

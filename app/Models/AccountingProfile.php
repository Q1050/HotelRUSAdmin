<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingProfile extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'name', 'driver', 'active', 'configuration'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'configuration' => 'encrypted:array'];
    }

    public function mappings()
    {
        return $this->hasMany(AccountingMapping::class);
    }

    public function batches()
    {
        return $this->hasMany(AccountingExportBatch::class);
    }

    public function syncRuns()
    {
        return $this->hasMany(AccountingSyncRun::class);
    }
}

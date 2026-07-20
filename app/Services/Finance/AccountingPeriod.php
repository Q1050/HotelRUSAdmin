<?php

namespace App\Services\Finance;

use App\Models\AccountingExportBatch;
use Carbon\CarbonInterface;

class AccountingPeriod
{
    public function ensureOpen(CarbonInterface|string $date): void
    {
        abort_if(AccountingExportBatch::whereDate('business_date', $date)->whereNull('reversal_of_id')->where('status', 'posted')->exists(), 422, 'This accounting period is locked. Reverse the posted journal before changing financial activity.');
    }
}

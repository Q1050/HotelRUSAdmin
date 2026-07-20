<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folio_payments', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->after('external_reference');
            $table->unique(['hotel_id', 'idempotency_key'], 'folio_payment_idempotency_unique');
        });
        Schema::table('accounting_sync_runs', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->after('direction');
            $table->unique(['hotel_id', 'idempotency_key'], 'accounting_sync_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_sync_runs', function (Blueprint $table) {
            $table->dropUnique('accounting_sync_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
        Schema::table('folio_payments', function (Blueprint $table) {
            $table->dropUnique('folio_payment_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_export_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider');
            $table->string('direction')->default('outbound');
            $table->string('status')->default('queued');
            $table->string('external_reference')->nullable();
            $table->text('message')->nullable();
            $table->unsignedInteger('records_sent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['hotel_id', 'status', 'created_at'], 'accounting_sync_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_sync_runs');
    }
};

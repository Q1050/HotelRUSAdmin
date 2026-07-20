<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('night_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('status')->default('closed');
            $table->unsignedInteger('charges_posted')->default(0);
            $table->decimal('room_revenue', 12, 2)->default(0);
            $table->decimal('payments', 12, 2)->default(0);
            $table->decimal('refunds', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->unsignedInteger('occupied_rooms')->default(0);
            $table->unsignedInteger('arrivals')->default(0);
            $table->unsignedInteger('departures')->default(0);
            $table->unsignedInteger('no_shows')->default(0);
            $table->json('exceptions')->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();
            $table->unique(['hotel_id', 'business_date']);
        });

        Schema::create('night_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('night_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('night_audit_events');
        Schema::dropIfExists('night_audits');
    }
};

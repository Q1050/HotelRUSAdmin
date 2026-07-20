<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('corporate_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_number');
            $table->string('method');
            $table->string('provider')->nullable();
            $table->string('external_reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('allocated_total', 12, 2)->default(0);
            $table->decimal('refunded_total', 12, 2)->default(0);
            $table->decimal('forfeited_total', 12, 2)->default(0);
            $table->decimal('available_balance', 12, 2);
            $table->string('status')->default('available');
            $table->text('notes')->nullable();
            $table->timestamp('received_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['hotel_id', 'receipt_number']);
            $table->index(['hotel_id', 'status', 'received_at']);
        });

        Schema::create('advance_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advance_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('corporate_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('folio_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('corporate_invoice_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamp('allocated_at');
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_payment_allocations');
        Schema::dropIfExists('advance_payments');
    }
};

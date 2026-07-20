<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folios', function (Blueprint $t) {
            $t->foreignId('reservation_id')->nullable()->change();
            $t->foreignId('guest_id')->nullable()->change();
            $t->foreignId('group_booking_id')->nullable()->after('reservation_id')->constrained()->cascadeOnDelete();
            $t->unique(['hotel_id', 'group_booking_id']);
        });
        Schema::table('folio_items', function (Blueprint $t) {
            $t->foreignId('transferred_to_folio_id')->nullable()->constrained('folios')->nullOnDelete();
            $t->timestamp('transferred_at')->nullable();
            $t->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('corporate_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $t->foreignId('corporate_account_id')->constrained()->restrictOnDelete();
            $t->foreignId('group_booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number');
            $t->string('status')->default('draft');
            $t->string('currency', 3);
            $t->date('issue_date');
            $t->date('due_date');
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('tax_total', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->decimal('paid_total', 12, 2)->default(0);
            $t->decimal('balance', 12, 2)->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('issued_at')->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('voided_at')->nullable();
            $t->text('void_reason')->nullable();
            $t->timestamps();
            $t->unique(['hotel_id', 'number']);
            $t->index(['hotel_id', 'status', 'due_date']);
        });
        Schema::create('corporate_invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $t->foreignId('corporate_invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('folio_item_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description');
            $t->decimal('amount', 12, 2);
            $t->decimal('tax_amount', 12, 2)->default(0);
            $t->decimal('total_amount', 12, 2);
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('corporate_invoice_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $t->foreignId('corporate_invoice_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('payment');
            $t->string('method');
            $t->decimal('amount', 12, 2);
            $t->string('reference')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('processed_at');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_invoice_payments');
        Schema::dropIfExists('corporate_invoice_items');
        Schema::dropIfExists('corporate_invoices');
        Schema::table('folio_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('transferred_to_folio_id');
            $t->dropConstrainedForeignId('transferred_by');
            $t->dropColumn('transferred_at');
        });
        Schema::table('folios', function (Blueprint $t) {
            $t->dropUnique(['hotel_id', 'group_booking_id']);
            $t->dropConstrainedForeignId('group_booking_id');
        });
    }
};

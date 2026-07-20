<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('accounting_export_entries');
        Schema::dropIfExists('accounting_export_batches');
        Schema::dropIfExists('accounting_mappings');
        Schema::dropIfExists('accounting_profiles');
        Schema::create('accounting_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name')->default('Default accounting profile');
            $table->string('driver')->default('file');
            $table->boolean('active')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
        });
        Schema::create('accounting_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_profile_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('account_code');
            $table->string('account_name');
            $table->timestamps();
            $table->unique(['accounting_profile_id', 'key']);
        });
        Schema::create('accounting_export_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('night_audit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('accounting_export_batches')->nullOnDelete();
            $table->string('batch_number');
            $table->date('business_date');
            $table->string('status')->default('draft');
            $table->decimal('debit_total', 14, 2)->default(0);
            $table->decimal('credit_total', 14, 2)->default(0);
            $table->string('checksum', 64)->nullable();
            $table->text('error')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['hotel_id', 'batch_number']);
            $table->index(['hotel_id', 'business_date', 'status']);
        });
        Schema::create('accounting_export_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_export_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('account_key');
            $table->string('account_code');
            $table->string('account_name');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['accounting_export_batch_id', 'line_number'], 'accounting_batch_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_export_entries');
        Schema::dropIfExists('accounting_export_batches');
        Schema::dropIfExists('accounting_mappings');
        Schema::dropIfExists('accounting_profiles');
    }
};

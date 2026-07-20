<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('calculation')->default('percentage');
            $table->decimal('amount', 12, 4);
            $table->string('application')->default('per_stay');
            $table->string('room_type')->nullable();
            $table->boolean('price_inclusive')->default(false);
            $table->boolean('tax_exemptible')->default(false);
            $table->boolean('active')->default(true);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hotel_id', 'type', 'active', 'effective_from']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->json('pricing_snapshot')->nullable();
            $table->boolean('tax_exempt')->default(false);
            $table->string('tax_exemption_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['pricing_snapshot', 'tax_exempt', 'tax_exemption_reference']));
        Schema::dropIfExists('financial_rules');
    }
};

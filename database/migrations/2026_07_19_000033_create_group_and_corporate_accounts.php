<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('code');
            $t->string('status')->default('active');
            $t->string('contact_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('billing_address')->nullable();
            $t->string('tax_number')->nullable();
            $t->decimal('credit_limit', 12, 2)->default(0);
            $t->unsignedSmallInteger('payment_terms_days')->default(30);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['hotel_id', 'code']);
        });
        Schema::create('group_bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $t->foreignId('corporate_account_id')->nullable()->constrained()->nullOnDelete();
            $t->string('code');
            $t->string('name');
            $t->string('status')->default('tentative');
            $t->string('contact_name');
            $t->string('contact_email')->nullable();
            $t->string('contact_phone')->nullable();
            $t->date('arrival_date');
            $t->date('departure_date');
            $t->string('billing_mode')->default('individual');
            $t->decimal('negotiated_nightly_rate', 12, 2)->nullable();
            $t->unsignedInteger('room_commitment')->default(1);
            $t->date('release_date')->nullable();
            $t->text('billing_instructions')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['hotel_id', 'code']);
        });
        Schema::table('reservations', function (Blueprint $t) {
            $t->foreignId('group_booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('corporate_account_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('negotiated_nightly_rate', 12, 2)->nullable();
            $t->string('billing_responsibility')->default('guest');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('group_booking_id');
            $t->dropConstrainedForeignId('corporate_account_id');
            $t->dropColumn(['negotiated_nightly_rate', 'billing_responsibility']);
        });
        Schema::dropIfExists('group_bookings');
        Schema::dropIfExists('corporate_accounts');
    }
};

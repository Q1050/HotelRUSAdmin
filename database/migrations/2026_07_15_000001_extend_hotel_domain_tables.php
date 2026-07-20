<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('email')->nullable()->after('last_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('address')->nullable()->after('phone');
            $table->string('id_type')->nullable()->after('address');
            $table->string('id_number')->nullable()->after('id_type');
            $table->enum('id_status', ['pending', 'verified', 'rejected'])->default('pending')->after('id_number');
            $table->text('notes')->nullable()->after('id_status');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('number')->nullable()->after('id');
            $table->string('type')->nullable()->after('number');
            $table->unsignedSmallInteger('floor')->nullable()->after('type');
            $table->enum('status', ['available', 'occupied', 'cleaning'])->default('available')->after('floor');
            $table->enum('lock_status', ['locked', 'unlocked'])->default('locked')->after('status');
            $table->decimal('price', 8, 2)->nullable()->after('lock_status');
            $table->timestamp('last_cleaned_at')->nullable()->after('price');
        });

        Schema::table('checkins', function (Blueprint $table) {
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete()->after('id');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete()->after('guest_id');
            $table->date('check_in_date')->nullable()->after('room_id');
            $table->date('check_out_date')->nullable()->after('check_in_date');
            $table->enum('payment_status', ['paid', 'pending', 'failed'])->default('pending')->after('check_out_date');
            $table->string('booking_reference')->nullable()->after('payment_status');
            $table->boolean('is_active')->default(true)->after('booking_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guest_id');
            $table->dropConstrainedForeignId('room_id');
            $table->dropColumn([
                'check_in_date',
                'check_out_date',
                'payment_status',
                'booking_reference',
                'is_active',
            ]);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'number',
                'type',
                'floor',
                'status',
                'lock_status',
                'price',
                'last_cleaned_at',
            ]);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'email',
                'phone',
                'address',
                'id_type',
                'id_number',
                'id_status',
                'notes',
            ]);
        });
    }
};

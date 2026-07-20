<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            $table->timestamp('access_suspended_at')->nullable();
            $table->text('access_suspension_reason')->nullable();
        });
        Schema::table('guests', function (Blueprint $table) {
            $table->timestamp('do_not_rent_at')->nullable();
            $table->text('do_not_rent_reason')->nullable();
        });
        Schema::create('stay_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checkin_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('reason')->nullable();
            $table->string('financial_resolution')->default('pending');
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->boolean('security_involved')->default(false);
            $table->boolean('do_not_rent')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('departed_at');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hotel_id', 'type', 'departed_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('stay_departures');
        Schema::table('guests', fn (Blueprint $table) => $table->dropColumn(['do_not_rent_at', 'do_not_rent_reason']));
        Schema::table('checkins', fn (Blueprint $table) => $table->dropColumn(['access_suspended_at', 'access_suspension_reason']));
    }
};

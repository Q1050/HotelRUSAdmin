<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id(); $table->foreignId('guest_id')->constrained()->restrictOnDelete(); $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique(); $table->date('arrival_date'); $table->date('departure_date'); $table->unsignedSmallInteger('guest_count')->default(1);
            $table->string('room_type')->nullable(); $table->string('status')->default('confirmed'); $table->string('payment_status')->default('pending');
            $table->decimal('total_amount',10,2)->default(0); $table->decimal('amount_paid',10,2)->default(0); $table->string('source')->default('direct');
            $table->text('special_requests')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['arrival_date','status']);
        });
        Schema::table('checkins', fn (Blueprint $table) => $table->foreignId('reservation_id')->nullable()->after('id')->constrained()->nullOnDelete());
        Schema::create('housekeeping_tasks', function (Blueprint $table) {
            $table->id(); $table->foreignId('room_id')->constrained()->cascadeOnDelete(); $table->foreignId('checkin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); $table->string('status')->default('pending'); $table->string('priority')->default('normal');
            $table->text('notes')->nullable(); $table->dateTime('started_at')->nullable(); $table->dateTime('completed_at')->nullable(); $table->dateTime('inspected_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('housekeeping_tasks'); Schema::table('checkins',fn(Blueprint $table)=>$table->dropConstrainedForeignId('reservation_id')); Schema::dropIfExists('reservations'); }
};

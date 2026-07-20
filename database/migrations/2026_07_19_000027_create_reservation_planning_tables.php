<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('group_code')->nullable()->after('source')->index();
        });

        Schema::create('inventory_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('room_type')->nullable();
            $table->string('name');
            $table->string('group_code')->nullable()->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hotel_id', 'start_date', 'end_date']);
        });

        Schema::create('room_rate_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('room_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('nightly_rate', 10, 2)->nullable();
            $table->unsignedSmallInteger('minimum_stay')->default(1);
            $table->boolean('closed_to_arrival')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hotel_id', 'room_type', 'start_date', 'end_date'], 'rate_rules_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_rate_rules');
        Schema::dropIfExists('inventory_blocks');
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn('group_code'));
    }
};

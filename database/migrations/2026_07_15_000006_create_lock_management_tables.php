<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lock_devices')) Schema::create('lock_devices', function (Blueprint $table) {
            $table->id(); $table->foreignId('room_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('provider')->default('simulator'); $table->string('external_id')->unique();
            $table->string('name'); $table->string('status')->default('online'); $table->unsignedTinyInteger('battery_level')->default(100);
            $table->dateTime('last_seen_at')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        if (! Schema::hasTable('lock_credentials')) Schema::create('lock_credentials', function (Blueprint $table) {
            $table->id(); $table->foreignId('lock_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('checkin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); $table->string('external_id')->nullable(); $table->string('token_hash', 64)->nullable();
            $table->string('status')->default('active'); $table->dateTime('valid_from'); $table->dateTime('valid_until');
            $table->dateTime('revoked_at')->nullable(); $table->timestamps();
        });
        if (! Schema::hasTable('lock_commands')) Schema::create('lock_commands', function (Blueprint $table) {
            $table->id(); $table->foreignId('lock_device_id')->constrained()->cascadeOnDelete(); $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('command'); $table->string('status')->default('pending'); $table->string('external_id')->nullable();
            $table->json('response')->nullable(); $table->dateTime('completed_at')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('lock_commands'); Schema::dropIfExists('lock_credentials'); Schema::dropIfExists('lock_devices'); }
};

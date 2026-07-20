<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('service');
            $table->string('provider_key');
            $table->string('label');
            $table->boolean('active')->default(true);
            $table->string('status')->default('untested');
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('failure_threshold')->default(3);
            $table->unsignedInteger('cooldown_minutes')->default(5);
            $table->unsignedInteger('last_response_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('circuit_open_until')->nullable();
            $table->timestamps();
            $table->unique(['hotel_id', 'service', 'provider_key'], 'integration_health_provider_unique');
            $table->index(['hotel_id', 'status'], 'integration_health_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_health_checks');
    }
};

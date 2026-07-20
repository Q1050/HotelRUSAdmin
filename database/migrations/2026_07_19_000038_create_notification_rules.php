<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('event_key');
            $table->string('label');
            $table->boolean('enabled')->default(true);
            $table->json('channels');
            $table->json('recipient_roles')->nullable();
            $table->string('delivery_mode')->default('immediate');
            $table->time('digest_time')->nullable();
            $table->time('quiet_start')->nullable();
            $table->time('quiet_end')->nullable();
            $table->unsignedInteger('escalation_minutes')->nullable();
            $table->json('escalation_roles')->nullable();
            $table->string('subject_template')->nullable();
            $table->text('body_template')->nullable();
            $table->timestamps();
            $table->unique(['hotel_id', 'event_key']);
        });
        Schema::table('mobile_notifications', function (Blueprint $table) {
            $table->string('event_key')->nullable()->after('category');
            $table->json('channels')->nullable()->after('data');
            $table->timestamp('scheduled_for')->nullable()->after('channels');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_notifications', function (Blueprint $table) {
            $table->dropColumn(['event_key', 'channels', 'scheduled_for']);
        });
        Schema::dropIfExists('notification_rules');
    }
};

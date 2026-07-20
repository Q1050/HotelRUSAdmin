<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_started_at')->nullable()->after('provider_subscription_id');
            $table->json('trial_reminders_sent')->nullable()->after('grace_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['trial_started_at', 'trial_reminders_sent']);
        });
    }
};

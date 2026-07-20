<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            $table->string('access_key_hash', 64)->nullable()->after('is_active');
            $table->timestamp('access_key_expires_at')->nullable()->after('access_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('checkins', fn (Blueprint $table) => $table->dropColumn(['access_key_hash', 'access_key_expires_at']));
    }
};

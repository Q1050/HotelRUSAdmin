<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'name')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('name')->nullable()->after('id'));
        }
        Schema::table('rooms', fn (Blueprint $table) => $table->unique('number'));
        Schema::table('checkins', fn (Blueprint $table) => $table->unique('booking_reference'));
    }

    public function down(): void
    {
        Schema::table('checkins', fn (Blueprint $table) => $table->dropUnique(['booking_reference']));
        Schema::table('rooms', fn (Blueprint $table) => $table->dropUnique(['number']));
    }
};

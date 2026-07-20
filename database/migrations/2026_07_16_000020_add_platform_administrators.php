<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_platform_admin')->default(false)->index()->after('status'));

        $owner = DB::table('users')->where('role', 'super_admin')->oldest('id')->value('id');
        if ($owner) DB::table('users')->where('id', $owner)->update(['is_platform_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_admin'));
    }
};

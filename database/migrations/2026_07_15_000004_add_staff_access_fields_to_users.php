<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('front_desk')->after('formality'));
        if (! Schema::hasColumn('users', 'status')) Schema::table('users', fn (Blueprint $table) => $table->string('status')->default('active')->after('role'));
        if (! Schema::hasColumn('users', 'last_login_at')) Schema::table('users', fn (Blueprint $table) => $table->timestamp('last_login_at')->nullable()->after('status'));
        DB::table('users')->update(['role' => 'super_admin', 'status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'status', 'last_login_at']));
    }
};

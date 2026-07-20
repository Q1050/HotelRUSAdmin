<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('base_role')->default('front_desk');
            $table->json('permissions');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['hotel_id', 'name']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->foreignId('staff_role_id')->nullable()->after('role')->constrained('staff_roles')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('staff_role_id'));
        Schema::dropIfExists('staff_roles');
    }
};

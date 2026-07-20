<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->json('settings')->nullable();
            $table->text('credentials')->nullable();
            $table->timestamps();
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->index()->constrained()->nullOnDelete();
            $table->json('organization_inheritance')->nullable()->after('settings');
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('administrator');
            $table->timestamps();
            $table->primary(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('organization_inheritance');
        });
        Schema::dropIfExists('organizations');
    }
};

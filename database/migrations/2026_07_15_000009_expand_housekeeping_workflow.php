<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('housekeeping_tasks', function (Blueprint $table) {
            $table->string('task_type')->default('turnover')->after('checkin_id');
            $table->dateTime('due_at')->nullable()->after('priority');
            $table->json('checklist')->nullable()->after('notes');
            $table->text('reassignment_reason')->nullable()->after('assigned_to');
            $table->foreignId('assigned_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->dateTime('assigned_at')->nullable()->after('assigned_by');
            $table->text('maintenance_notes')->nullable()->after('inspected_by');
            $table->index(['status', 'assigned_to']);
            $table->index('due_at');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('housekeeping_tasks', function (Blueprint $table) {
            $table->dropIndex(['status', 'assigned_to']);
            $table->dropIndex(['due_at']);
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn(['task_type', 'due_at', 'checklist', 'reassignment_reason', 'assigned_at', 'maintenance_notes']);
        });
    }
};

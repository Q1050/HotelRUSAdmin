<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('housekeeping_task_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('category')->default('general');
            $table->string('title');
            $table->text('description');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assigned_at')->nullable();
            $table->text('reassignment_reason')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('repaired_at')->nullable();
            $table->text('repair_notes')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->dateTime('inspected_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('inspection_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'assigned_to']);
            $table->index(['room_id', 'status']);
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_work_orders');
    }
};

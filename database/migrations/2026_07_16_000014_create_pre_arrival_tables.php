<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('guests', fn(Blueprint $table) => $table->foreignId('merged_into_guest_id')->nullable()->constrained('guests')->nullOnDelete());
        Schema::create('reservation_claim_tokens', function(Blueprint $table){$table->id();$table->foreignId('reservation_id')->constrained()->cascadeOnDelete();$table->foreignId('guest_id')->constrained()->cascadeOnDelete();$table->string('code_hash');$table->unsignedTinyInteger('attempts')->default(0);$table->timestamp('expires_at');$table->timestamp('used_at')->nullable();$table->timestamps();$table->index(['reservation_id','guest_id']);});
        Schema::create('pre_arrival_submissions', function(Blueprint $table){$table->id();$table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();$table->foreignId('guest_id')->constrained()->cascadeOnDelete();$table->string('status')->default('pending');$table->string('id_type');$table->string('id_number');$table->string('id_document_front');$table->string('id_document_back')->nullable();$table->time('estimated_arrival_time')->nullable();$table->text('guest_notes')->nullable();$table->boolean('policy_accepted');$table->timestamp('consented_at');$table->string('consent_ip',45)->nullable();$table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('reviewed_at')->nullable();$table->text('review_notes')->nullable();$table->timestamps();});
    }
    public function down(): void {Schema::dropIfExists('pre_arrival_submissions');Schema::dropIfExists('reservation_claim_tokens');Schema::table('guests',fn(Blueprint $table)=>$table->dropConstrainedForeignId('merged_into_guest_id'));}
};

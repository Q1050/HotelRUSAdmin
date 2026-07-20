<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up():void{
  Schema::create('audit_events',function(Blueprint $table){$table->id();$table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();$table->string('action');$table->string('category');$table->string('severity')->default('normal');$table->string('subject_type')->nullable();$table->string('subject_id')->nullable();$table->text('description');$table->text('reason')->nullable();$table->json('metadata')->nullable();$table->string('ip_address',45)->nullable();$table->text('user_agent')->nullable();$table->timestamp('occurred_at');$table->index(['category','severity']);$table->index(['actor_id','occurred_at']);$table->index(['action','occurred_at']);});
  Schema::create('security_settings',function(Blueprint $table){$table->string('key')->primary();$table->text('value')->nullable();$table->timestamps();});
  Schema::table('users',function(Blueprint $table){$table->boolean('two_factor_enabled')->default(false);$table->string('two_factor_code_hash')->nullable();$table->timestamp('two_factor_code_expires_at')->nullable();});
 }
 public function down():void{Schema::table('users',fn(Blueprint $table)=>$table->dropColumn(['two_factor_enabled','two_factor_code_hash','two_factor_code_expires_at']));Schema::dropIfExists('security_settings');Schema::dropIfExists('audit_events');}
};

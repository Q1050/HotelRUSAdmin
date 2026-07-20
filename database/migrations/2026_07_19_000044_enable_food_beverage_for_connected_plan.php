<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Support\Facades\DB;
return new class extends Migration{public function up():void{$plan=DB::table('plans')->where('key','connected')->value('id');if($plan)DB::table('plan_features')->insertOrIgnore(['plan_id'=>$plan,'feature'=>'food_beverage','enabled'=>true]);}public function down():void{DB::table('plan_features')->where('feature','food_beverage')->whereIn('plan_id',DB::table('plans')->where('key','connected')->select('id'))->delete();}};

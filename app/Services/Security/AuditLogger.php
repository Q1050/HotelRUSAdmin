<?php
namespace App\Services\Security;
use App\Models\AuditEvent;use App\Models\User;use Illuminate\Database\Eloquent\Model;use Illuminate\Http\Request;
class AuditLogger{
 public static function record(Request $request,string $action,string $category,string $severity,string $description,?Model $subject=null,?string $reason=null,array $metadata=[]):AuditEvent{return self::write($request->user(),$action,$category,$severity,$description,$subject,$reason,$metadata,$request->ip(),$request->userAgent());}
 public static function actor(?User $actor,string $action,string $category,string $severity,string $description,?Model $subject=null,?string $reason=null,array $metadata=[]):AuditEvent{$request=request();return self::write($actor,$action,$category,$severity,$description,$subject,$reason,$metadata,$request?->ip(),$request?->userAgent());}
 private static function write(?User $actor,string $action,string $category,string $severity,string $description,?Model $subject,?string $reason,array $metadata,?string $ip,?string $agent):AuditEvent{return AuditEvent::create(['actor_id'=>$actor?->id,'action'=>$action,'category'=>$category,'severity'=>$severity,'subject_type'=>$subject?->getMorphClass(),'subject_id'=>$subject?->getKey(),'description'=>$description,'reason'=>$reason,'metadata'=>$metadata?:null,'ip_address'=>$ip,'user_agent'=>substr((string)$agent,0,1000),'occurred_at'=>now()]);}
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GuestPrivacyRequest;
use App\Services\Security\AuditLogger;
use Illuminate\Http\{JsonResponse,Request};
use Illuminate\Support\Facades\Hash;

class GuestPrivacyController extends Controller
{
    public function export(Request $request): JsonResponse
    {
        $guest=$request->user();
        $record=GuestPrivacyRequest::create(['guest_id'=>$guest->id,'type'=>'export','status'=>'completed','completed_at'=>now()]);
        $data=['generated_at'=>now()->toISOString(),'property'=>$guest->hotel?->name,'profile'=>$guest->only(['first_name','last_name','email','phone','address','id_type','id_status','account_status','created_at']),'reservations'=>$guest->reservations()->with('room:id,number,type')->get(),'stays'=>$guest->checkins()->with('room:id,number,type')->get(),'service_requests'=>$guest->serviceRequests()->with(['messages'=>fn($query)=>$query->where('internal',false)->select(['id','guest_service_request_id','guest_id','message','created_at'])])->get(),'devices'=>$guest->devices()->get(['name','platform','ip_address','last_seen_at','revoked_at','created_at'])];
        AuditLogger::actor(null,'guest_data_exported','privacy','sensitive','Guest exported a copy of their personal data.',$guest,null,['privacy_request_id'=>$record->id]);
        return response()->json(['data'=>$data]);
    }

    public function deletion(Request $request): JsonResponse
    {
        $data=$request->validate(['password'=>'required|string','reason'=>'nullable|string|max:2000']);$guest=$request->user();
        if(!$guest->password||!Hash::check($data['password'],$guest->password))return response()->json(['message'=>'The account password is incorrect.','errors'=>['password'=>['The account password is incorrect.']]],422);
        abort_if($guest->checkins()->where('is_active',true)->exists(),422,'Account deletion cannot be requested during an active stay. Contact the front desk for assistance.');
        $privacy=GuestPrivacyRequest::firstOrCreate(['guest_id'=>$guest->id,'type'=>'deletion','status'=>'pending'],['guest_reason'=>$data['reason']??null]);
        AuditLogger::actor(null,'guest_deletion_requested','privacy','critical','Guest requested account deletion.',$guest,null,['privacy_request_id'=>$privacy->id]);
        return response()->json(['message'=>'Your deletion request was sent to the hotel for review.','data'=>['id'=>$privacy->id,'status'=>$privacy->status]],201);
    }
}

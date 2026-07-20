<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\GuestPrivacyRequest;
use App\Services\Security\AuditLogger;
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\Rule;

class GuestPrivacyController extends Controller
{
    public function review(Request $request, GuestPrivacyRequest $privacyRequest): RedirectResponse
    {
        abort_unless($request->user()->role==='super_admin',403);$data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected'])],'notes'=>'required|string|max:2000']);abort_unless($privacyRequest->type==='deletion'&&$privacyRequest->status==='pending',422,'This privacy request is no longer pending.');$guest=$privacyRequest->guest;abort_if($data['decision']==='approved'&&$guest->checkins()->where('is_active',true)->exists(),422,'An account with an active stay cannot be deleted.');
        DB::transaction(function()use($privacyRequest,$guest,$data,$request){if($data['decision']==='approved'){$guest->tokens()->delete();$guest->devices()->update(['revoked_at'=>now(),'push_token'=>null]);foreach($guest->preArrivalSubmissions as$submission){foreach([$submission->id_document_front,$submission->id_document_back]as$path)if($path)Storage::disk('local')->delete($path);$submission->update(['id_document_front'=>null,'id_document_back'=>null,'id_number'=>null]);}$guest->update(['first_name'=>'Deleted','last_name'=>'Guest','email'=>null,'phone'=>null,'address'=>null,'id_type'=>null,'id_number'=>null,'notes'=>null,'password'=>null,'account_status'=>'deleted']);}$privacyRequest->update(['status'=>$data['decision']==='approved'?'completed':'rejected','reviewed_by'=>$request->user()->id,'review_notes'=>$data['notes'],'reviewed_at'=>now(),'completed_at'=>$data['decision']==='approved'?now():null]);});
        AuditLogger::record($request,'guest_deletion_'.$data['decision'],'privacy','critical','Guest deletion request '.$data['decision'].'.',$privacyRequest,$data['notes']);return back()->with('success','Privacy request '.$data['decision'].'.');
    }
}

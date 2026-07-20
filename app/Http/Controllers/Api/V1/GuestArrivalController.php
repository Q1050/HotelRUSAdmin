<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{PreArrivalSubmission, Reservation, ReservationClaimToken};
use App\Notifications\ReservationClaimCode;
use App\Services\Security\AuditLogger;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{DB, Hash, Storage};

class GuestArrivalController extends Controller
{
    public function requestClaim(Request $request): JsonResponse
    {
        $reference=strtoupper(trim($request->validate(['reference'=>'required|string|max:50'])['reference']));$guest=$request->user();abort_unless(filled($guest->email),422,'Add an email address to your account before claiming a reservation.');
        $reservation=Reservation::with('guest')->where('reference',$reference)->whereIn('status',['pending','confirmed'])->first();
        abort_unless($reservation && $this->identityMatches($guest,$reservation->guest),422,'The reservation details do not match this guest account.');
        if($reservation->guest_id===$guest->id)return response()->json(['message'=>'This reservation is already linked to your account.','already_claimed'=>true]);
        $code=(string)random_int(100000,999999);ReservationClaimToken::where('reservation_id',$reservation->id)->whereNull('used_at')->delete();ReservationClaimToken::create(['reservation_id'=>$reservation->id,'guest_id'=>$guest->id,'code_hash'=>Hash::make($code),'expires_at'=>now()->addMinutes(15)]);$guest->notify(new ReservationClaimCode($code,$reference));
        AuditLogger::actor(null,'reservation_claim_requested','guest','standard','Guest requested reservation ownership verification.',$guest,null,['reservation_id'=>$reservation->id]);return response()->json(['message'=>'A verification code was sent to your account email.']);
    }

    public function verifyClaim(Request $request): JsonResponse
    {
        $data=$request->validate(['reference'=>'required|string|max:50','code'=>'required|digits:6']);$guest=$request->user();$reservation=Reservation::where('reference',strtoupper(trim($data['reference'])))->firstOrFail();$claim=ReservationClaimToken::where('reservation_id',$reservation->id)->where('guest_id',$guest->id)->whereNull('used_at')->latest()->first();
        abort_unless($claim && $claim->expires_at->isFuture() && $claim->attempts<5,422,'The verification code is invalid or expired.');$claim->increment('attempts');abort_unless(Hash::check($data['code'],$claim->code_hash),422,'The verification code is invalid or expired.');
        DB::transaction(function()use($reservation,$guest,$claim){$oldGuest=$reservation->guest;$reservation->update(['guest_id'=>$guest->id]);$claim->update(['used_at'=>now()]);if($oldGuest->id!==$guest->id&&!$oldGuest->password&&!$oldGuest->checkins()->exists()&&!$oldGuest->reservations()->where('id','!=',$reservation->id)->exists())$oldGuest->update(['account_status'=>'merged','merged_into_guest_id'=>$guest->id]);});
        AuditLogger::actor(null,'reservation_claimed','guest','sensitive','Guest securely linked an existing reservation.',$guest,null,['reservation_id'=>$reservation->id]);return response()->json(['message'=>'Reservation linked successfully.','data'=>['reservation_id'=>$reservation->id,'reference'=>$reservation->reference]]);
    }

    public function show(Request $request,Reservation $reservation): JsonResponse
    {
        abort_unless($reservation->guest_id===$request->user()->id,404);$submission=$reservation->preArrival;return response()->json(['data'=>$submission?['id'=>$submission->id,'status'=>$submission->status,'id_type'=>$submission->id_type,'id_number'=>$submission->id_number,'estimated_arrival_time'=>$submission->estimated_arrival_time,'guest_notes'=>$submission->guest_notes,'policy_accepted'=>$submission->policy_accepted,'submitted_at'=>$submission->created_at?->toISOString(),'review_notes'=>$submission->review_notes]:null]);
    }

    public function submit(Request $request,Reservation $reservation): JsonResponse
    {
        abort_unless($reservation->guest_id===$request->user()->id,404);abort_unless(in_array($reservation->status,['pending','confirmed']),422,'Pre-arrival check-in is not available for this reservation.');
        $data=$request->validate(['id_type'=>'required|in:passport,drivers_license,national_id','id_number'=>'required|string|max:100','id_document_front'=>'required|image|mimes:jpg,jpeg,png,webp|max:5120','id_document_back'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120','estimated_arrival_time'=>'nullable|date_format:H:i','guest_notes'=>'nullable|string|max:2000','policy_accepted'=>'accepted']);$existing=$reservation->preArrival;
        if($existing&&$existing->status==='approved')abort(422,'The approved pre-arrival submission can no longer be replaced.');
        $front=$request->file('id_document_front')->store("guest-documents/{$request->user()->id}",'local');$back=$request->file('id_document_back')?->store("guest-documents/{$request->user()->id}",'local');
        if($existing){Storage::disk('local')->delete(array_filter([$existing->id_document_front,$existing->id_document_back]));$existing->update([...$data,'id_document_front'=>$front,'id_document_back'=>$back,'status'=>'pending','policy_accepted'=>true,'consented_at'=>now(),'consent_ip'=>$request->ip(),'reviewed_by'=>null,'reviewed_at'=>null,'review_notes'=>null]);$submission=$existing;}else{$submission=PreArrivalSubmission::create([...$data,'reservation_id'=>$reservation->id,'guest_id'=>$request->user()->id,'id_document_front'=>$front,'id_document_back'=>$back,'status'=>'pending','policy_accepted'=>true,'consented_at'=>now(),'consent_ip'=>$request->ip()]);}
        AuditLogger::actor(null,'pre_arrival_submitted','guest','sensitive','Guest submitted identification and consent for pre-arrival review.',$request->user(),null,['reservation_id'=>$reservation->id,'submission_id'=>$submission->id]);return response()->json(['message'=>'Pre-arrival check-in submitted for staff review.','data'=>['id'=>$submission->id,'status'=>$submission->status]],$existing?200:201);
    }

    private function identityMatches($appGuest,$bookingGuest): bool
    {
        $email=filled($appGuest->email)&&filled($bookingGuest->email)&&mb_strtolower(trim($appGuest->email))===mb_strtolower(trim($bookingGuest->email));$phone=filled($appGuest->phone)&&filled($bookingGuest->phone)&&preg_replace('/\D/','',$appGuest->phone)===preg_replace('/\D/','',$bookingGuest->phone);return$email||$phone;
    }
}

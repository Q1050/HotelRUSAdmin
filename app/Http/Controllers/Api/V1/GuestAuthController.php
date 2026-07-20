<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Notifications\GuestPasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash, Notification, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GuestAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['first_name'=>'required|string|max:100','last_name'=>'required|string|max:100','email'=>['required','email','max:255',Rule::unique('guests','email')->where('hotel_id',app('currentHotel')->id)],'phone'=>'nullable|string|max:30','password'=>'required|string|min:8|confirmed','device_id'=>'required|uuid','device_name'=>'nullable|string|max:100','platform'=>'required|in:ios,android,web','push_token'=>'nullable|string|max:500']);
        $guest = Guest::create($data + ['id_status'=>'pending','account_status'=>'active']);
        return response()->json(['data'=>$this->session($guest,$request,$data)], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data=$request->validate(['email'=>'required|email','password'=>'required|string','device_id'=>'required|uuid','device_name'=>'nullable|string|max:100','platform'=>'required|in:ios,android,web','push_token'=>'nullable|string|max:500']);
        $guest=Guest::where('hotel_id',app('currentHotel')->id)->where('email',$data['email'])->first();
        if(!$guest || !$guest->password || !Hash::check($data['password'],$guest->password) || $guest->account_status!=='active') return response()->json(['message'=>'The provided credentials are invalid.'],422);
        return response()->json(['data'=>$this->session($guest,$request,$data)]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $guest=$request->user(); $deviceId=$request->header('X-Device-ID');
        $guest->currentAccessToken()->delete();
        return response()->json(['data'=>$this->token($guest,$deviceId)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->devices()->where('device_id',$request->header('X-Device-ID'))->update(['revoked_at'=>now()]);
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message'=>'Signed out.']);
    }

    public function forgot(Request $request): JsonResponse
    {
        $email=$request->validate(['email'=>'required|email'])['email']; $guest=Guest::where('hotel_id',app('currentHotel')->id)->where('email',$email)->first();
        if($guest){$plain=(string)random_int(100000,999999);DB::table('guest_password_resets')->updateOrInsert(['hotel_id'=>app('currentHotel')->id,'email'=>$email],['token'=>Hash::make($plain),'created_at'=>now()]);Notification::route('mail',$email)->notify(new GuestPasswordReset($plain));}
        return response()->json(['message'=>'If that guest account exists, a reset code has been sent.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $data=$request->validate(['email'=>'required|email','token'=>'required|string','password'=>'required|string|min:8|confirmed']);$reset=DB::table('guest_password_resets')->where('hotel_id',app('currentHotel')->id)->where('email',$data['email'])->first();
        if(!$reset || !Hash::check($data['token'],$reset->token) || now()->diffInMinutes($reset->created_at)>30) return response()->json(['message'=>'The reset code is invalid or expired.'],422);
        Guest::where('hotel_id',app('currentHotel')->id)->where('email',$data['email'])->update(['password'=>Hash::make($data['password'])]);DB::table('guest_password_resets')->where('hotel_id',app('currentHotel')->id)->where('email',$data['email'])->delete();
        return response()->json(['message'=>'Password reset successfully.']);
    }

    private function session(Guest $guest,Request $request,array $data): array
    {
        $guest->devices()->updateOrCreate(['device_id'=>$data['device_id']],['name'=>$data['device_name']??null,'platform'=>$data['platform'],'push_token'=>$data['push_token']??null,'ip_address'=>$request->ip(),'last_seen_at'=>now(),'revoked_at'=>null]);
        $guest->tokens()->where('name',"guest-mobile:{$data['device_id']}")->delete();
        return ['guest'=>$this->guest($guest)]+$this->token($guest,$data['device_id']);
    }
    private function token(Guest $guest,string $deviceId): array {$expires=now()->addHour();return ['token'=>$guest->createToken("guest-mobile:{$deviceId}",['guest:mobile'],$expires)->plainTextToken,'token_type'=>'Bearer','expires_at'=>$expires->toISOString()];}
    private function guest(Guest $guest): array{return ['id'=>$guest->id,'first_name'=>$guest->first_name,'last_name'=>$guest->last_name,'email'=>$guest->email,'phone'=>$guest->phone,'id_status'=>$guest->id_status];}
}

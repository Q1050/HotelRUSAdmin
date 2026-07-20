<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;use App\Models\MobileNotification;use Illuminate\Http\{JsonResponse,Request};
class GuestNotificationController extends Controller{
 public function index(Request$request):JsonResponse{$items=$request->user()->mobileNotifications()->latest()->paginate(30);return response()->json(['data'=>$items->items(),'meta'=>['current_page'=>$items->currentPage(),'last_page'=>$items->lastPage(),'total'=>$items->total(),'unread'=>$request->user()->mobileNotifications()->whereNull('read_at')->count()]]);}
 public function read(Request$request,MobileNotification$notification):JsonResponse{abort_unless($notification->guest_id===$request->user()->id,404);$notification->update(['read_at'=>now()]);return response()->json(['message'=>'Notification marked as read.']);}
 public function readAll(Request$request):JsonResponse{$request->user()->mobileNotifications()->whereNull('read_at')->update(['read_at'=>now()]);return response()->json(['message'=>'All notifications marked as read.']);}
 public function preferences(Request$request):JsonResponse{return response()->json(['data'=>$request->user()->notificationPreference()->firstOrCreate([])->only(['booking_updates','access_updates','service_updates','checkout_reminders','marketing'])]);}
 public function updatePreferences(Request$request):JsonResponse{$data=$request->validate(['booking_updates'=>'required|boolean','access_updates'=>'required|boolean','service_updates'=>'required|boolean','checkout_reminders'=>'required|boolean','marketing'=>'required|boolean']);$preferences=$request->user()->notificationPreference()->updateOrCreate([],$data);return response()->json(['data'=>$preferences->only(array_keys($data))]);}
 public function pushToken(Request$request):JsonResponse{$data=$request->validate(['push_token'=>'nullable|string|max:500']);$device=$request->attributes->get('guest_device');$device->update(['push_token'=>$data['push_token']??null]);return response()->json(['message'=>$device->push_token?'Push notifications enabled for this device.':'Push notifications disabled for this device.']);}
}

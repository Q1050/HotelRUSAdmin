<?php
namespace App\Http\Middleware;use App\Models\Hotel;use Closure;use Illuminate\Http\Request;use Symfony\Component\HttpFoundation\Response;
class ResolvePublicHotel{public function handle(Request$request,Closure$next):Response{$slug=$request->header('X-Hotel-Slug','default-hotel');$hotel=Hotel::where('slug',$slug)->where('status','active')->first();if(!$hotel)return response()->json(['message'=>'Hotel account not found.'],404);app()->instance('currentHotel',$hotel);return$next($request);}}

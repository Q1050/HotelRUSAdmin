<?php
namespace App\Http\Controllers\Api\V1;use App\Http\Controllers\Controller;use App\Services\PropertySettings;use Illuminate\Http\JsonResponse;
class PropertyController extends Controller{public function show():JsonResponse{return response()->json(['data'=>PropertySettings::publicData(app('currentHotel'))]);}}

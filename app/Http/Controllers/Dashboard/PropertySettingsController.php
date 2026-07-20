<?php
namespace App\Http\Controllers\Dashboard;
use App\Http\Controllers\Controller;use App\Services\PropertySettings;use App\Services\Security\AuditLogger;use Illuminate\Http\{RedirectResponse,Request};
class PropertySettingsController extends Controller{public function update(Request$request,PropertySettings$settings):RedirectResponse{abort_unless($request->user()->role==='super_admin',403);$settings->update($request,$request->user()->hotel);AuditLogger::record($request,'property_branding_updated','settings','sensitive','Property branding and guest policies updated.',$request->user()->hotel);return back()->with('success','Property branding saved.');}}

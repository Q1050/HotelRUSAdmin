<?php

use App\Http\Controllers\Platform\HotelController;
use App\Http\Controllers\Platform\BillingController;
use App\Http\Controllers\Platform\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::post('/platform/impersonation/stop',[HotelController::class,'stopImpersonating'])->middleware('auth')->name('platform.impersonation.stop');
Route::post('/webhooks/stripe',[BillingController::class,'webhook'])->name('webhooks.stripe');
Route::prefix('platform')->name('platform.')->middleware(['auth','verified','platform.admin'])->group(function(){
    Route::get('/',[HotelController::class,'overview'])->name('overview');
    Route::get('/plans',[HotelController::class,'plans'])->name('plans.index');
    Route::post('/plans',[HotelController::class,'storePlan'])->name('plans.store');
    Route::patch('/plans/{plan}',[HotelController::class,'updatePlan'])->name('plans.update');
    Route::post('/plans/{plan}/duplicate',[HotelController::class,'duplicatePlan'])->name('plans.duplicate');
    Route::get('/activity',[HotelController::class,'activity'])->name('activity.index');
    Route::get('/billing',[BillingController::class,'index'])->name('billing.index');
    Route::post('/billing/{hotel}/checkout',[BillingController::class,'checkout'])->name('billing.checkout');
    Route::post('/billing/{hotel}/portal',[BillingController::class,'portal'])->name('billing.portal');
    Route::get('/hotels',[HotelController::class,'index'])->name('hotels.index');
    Route::get('/organizations',[OrganizationController::class,'index'])->name('organizations.index');
    Route::post('/organizations',[OrganizationController::class,'store'])->name('organizations.store');
    Route::patch('/organizations/{organization}',[OrganizationController::class,'update'])->name('organizations.update');
    Route::post('/organizations/{organization}/fcm',[OrganizationController::class,'fcm'])->name('organizations.fcm');
    Route::patch('/hotels/{hotel}/organization',[OrganizationController::class,'assign'])->name('hotels.organization');
    Route::get('/hotels/{hotel}/onboarding',[HotelController::class,'onboarding'])->name('hotels.onboarding');
    Route::patch('/hotels/{hotel}/onboarding/profile',[HotelController::class,'updateOnboardingProfile'])->name('hotels.onboarding.profile');
    Route::post('/hotels/{hotel}/onboarding/branding',[HotelController::class,'updateBranding'])->name('hotels.onboarding.branding');
    Route::post('/hotels/{hotel}/onboarding/fcm',[HotelController::class,'updateFcm'])->name('hotels.onboarding.fcm');
    Route::post('/hotels/{hotel}/onboarding/fcm/test',[HotelController::class,'testFcm'])->name('hotels.onboarding.fcm.test');
    Route::post('/hotels/{hotel}/onboarding/rooms',[HotelController::class,'bulkRooms'])->name('hotels.onboarding.rooms');
    Route::post('/hotels/{hotel}/onboarding/launch',[HotelController::class,'launch'])->name('hotels.onboarding.launch');
    Route::post('/hotels',[HotelController::class,'store'])->name('hotels.store');
    Route::patch('/hotels/{hotel}',[HotelController::class,'update'])->name('hotels.update');
    Route::put('/hotels/{hotel}/features/{feature}',[HotelController::class,'feature'])->name('hotels.features.update');
    Route::post('/hotels/{hotel}/impersonate',[HotelController::class,'impersonate'])->name('hotels.impersonate');
    Route::post('/hotels/{hotel}/admin-recovery',[HotelController::class,'recoverAdministrator'])->name('hotels.admin-recovery');
});

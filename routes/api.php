<?php

use App\Http\Controllers\Api\V1\GuestArrivalController;
use App\Http\Controllers\Api\V1\GuestAuthController;
use App\Http\Controllers\Api\V1\GuestFolioController;
use App\Http\Controllers\Api\V1\GuestMobileController;
use App\Http\Controllers\Api\V1\GuestNotificationController;
use App\Http\Controllers\Api\V1\GuestPrivacyController;
use App\Http\Controllers\Api\V1\GuestRequestController;
use App\Http\Controllers\Api\V1\PropertyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('property', [PropertyController::class, 'show'])->middleware(['hotel.public', 'throttle:60,1']);
    Route::prefix('auth')->middleware(['hotel.public', 'hotel.feature:guest_mobile', 'throttle:5,1'])->group(function () {
        Route::post('register', [GuestAuthController::class, 'register']);
        Route::post('login', [GuestAuthController::class, 'login']);
        Route::post('forgot-password', [GuestAuthController::class, 'forgot']);
        Route::post('reset-password', [GuestAuthController::class, 'reset']);
    });
    Route::middleware(['auth:sanctum', 'hotel', 'hotel.feature:guest_mobile', 'guest.device', 'throttle:60,1'])->group(function () {
        Route::post('auth/refresh', [GuestAuthController::class, 'refresh']);
        Route::post('auth/logout', [GuestAuthController::class, 'logout']);
        Route::get('me', [GuestMobileController::class, 'me']);
        Route::get('stays/current', [GuestMobileController::class, 'currentStay']);
        Route::get('reservations', [GuestMobileController::class, 'reservations']);
        Route::get('reservations/{reservation}/folio', [GuestFolioController::class, 'show']);
        Route::middleware('hotel.feature:pre_arrival')->group(function () {
            Route::post('reservations/claim/request', [GuestArrivalController::class, 'requestClaim'])->middleware('throttle:3,10');
            Route::post('reservations/claim/verify', [GuestArrivalController::class, 'verifyClaim'])->middleware('throttle:5,10');
            Route::get('reservations/{reservation}/pre-arrival', [GuestArrivalController::class, 'show']);
            Route::post('reservations/{reservation}/pre-arrival', [GuestArrivalController::class, 'submit'])->middleware('throttle:5,10');
        });
        Route::get('devices', [GuestMobileController::class, 'devices']);
        Route::delete('devices/others', [GuestMobileController::class, 'revokeOtherDevices']);
        Route::delete('devices/{deviceId}', [GuestMobileController::class, 'revokeDevice']);
        Route::put('account/password', [GuestMobileController::class, 'changePassword'])->middleware('throttle:5,10');
        Route::post('privacy/export', [GuestPrivacyController::class, 'export'])->middleware('throttle:privacy-export');
        Route::post('privacy/deletion', [GuestPrivacyController::class, 'deletion'])->middleware('throttle:privacy-deletion');
        Route::middleware('hotel.feature:notifications')->group(function () {
            Route::put('devices/current/push-token', [GuestNotificationController::class, 'pushToken']);
            Route::get('notifications', [GuestNotificationController::class, 'index']);
            Route::patch('notifications/read-all', [GuestNotificationController::class, 'readAll']);
            Route::patch('notifications/{notification}/read', [GuestNotificationController::class, 'read']);
            Route::get('notification-preferences', [GuestNotificationController::class, 'preferences']);
            Route::put('notification-preferences', [GuestNotificationController::class, 'updatePreferences']);
        });
        Route::get('requests', [GuestMobileController::class, 'requests']);
        Route::post('requests', [GuestMobileController::class, 'createRequest']);
        Route::middleware('hotel.feature:food_beverage')->group(function () {
            Route::get('food/menu', [\App\Http\Controllers\Api\V1\GuestFoodOrderController::class, 'menu']);
            Route::get('food/orders', [\App\Http\Controllers\Api\V1\GuestFoodOrderController::class, 'index']);
            Route::post('food/orders', [\App\Http\Controllers\Api\V1\GuestFoodOrderController::class, 'store']);
            Route::patch('food/orders/{order}/cancel', [\App\Http\Controllers\Api\V1\GuestFoodOrderController::class, 'cancel']);
        });
        Route::middleware('hotel.feature:conversations')->group(function () {
            Route::get('requests/{serviceRequest}', [GuestRequestController::class, 'show']);
            Route::post('requests/{serviceRequest}/messages', [GuestRequestController::class, 'message']);
            Route::get('requests/{serviceRequest}/messages/{message}/attachment', [GuestRequestController::class, 'attachment']);
            Route::patch('requests/{serviceRequest}/cancel', [GuestRequestController::class, 'cancel']);
            Route::patch('requests/{serviceRequest}/resolution', [GuestRequestController::class, 'resolution']);
            Route::get('requests/{serviceRequest}/completion-photo', [GuestRequestController::class, 'photo']);
        });
        Route::middleware(['hotel.feature:smart_locks', 'throttle:10,1'])->group(function () {
            Route::post('access/credential', [GuestMobileController::class, 'credential']);
            Route::post('access/unlock', [GuestMobileController::class, 'unlock']);
        });
    });
});

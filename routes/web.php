<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/webhooks/locks/{providerKey}', [\App\Http\Controllers\LockWebhookController::class, 'handle'])->name('webhooks.locks');
Route::get('/health', \App\Http\Controllers\HealthController::class)->name('health');
Route::get('/communications/{delivery}/open.gif', \App\Http\Controllers\CommunicationTrackingController::class)->name('communications.open');

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Login');
});
Route::prefix('authenticate')->name('auth.')->middleware('guest')->group(function () {

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});
Route::get('/dashboard', [\App\Http\Controllers\Dashboard\DashboardRoutes::class, 'mainScreen'])
    ->middleware(['auth', 'verified', 'hotel', 'staff.permission:dashboard'])
    ->name('dashboard');
Route::prefix('dashboard')->name('dashboard.')->middleware(['auth', 'verified', 'hotel', 'staff.permission:dashboard'])->group(function () {
    Route::patch('/notifications/read', [\App\Http\Controllers\Dashboard\NotificationController::class, 'read'])->name('notifications.read');
    Route::middleware('staff.permission:guests')->group(function () {
        Route::get('/guests', [\App\Http\Controllers\Dashboard\DashboardRoutes::class, 'GuestScreen'])->name('guests.index');
        Route::get('/guests-search', [\App\Http\Controllers\Dashboard\GuestController::class, 'search'])->name('guests.search');
        Route::get('/guests/{id}', [\App\Http\Controllers\Dashboard\GuestController::class, 'show'])->name('guests.show');
        Route::patch('/guests/{id}/verify-id', [\App\Http\Controllers\Dashboard\GuestController::class, 'verifyId'])->name('guests.verify-id');
        Route::patch('/guests/{id}/reject-id', [\App\Http\Controllers\Dashboard\GuestController::class, 'rejectId'])->name('guests.reject-id');
        Route::post('/guests', [\App\Http\Controllers\Dashboard\GuestController::class, 'store'])->name('guests.store');
        Route::patch('/guests/{id}/assign-room', [\App\Http\Controllers\Dashboard\GuestController::class, 'assignRoom'])->name('guests.assign-room');
        Route::patch('/checkins/{checkin}/checkout', [\App\Http\Controllers\Dashboard\CheckinController::class, 'checkout'])->name('checkins.checkout');
        Route::patch('/checkins/{checkin}/suspend-access', [\App\Http\Controllers\Dashboard\CheckinController::class, 'suspendAccess'])->middleware('staff.permission:stays.force_departure')->name('checkins.suspend-access');
        Route::patch('/checkins/{checkin}/restore-access', [\App\Http\Controllers\Dashboard\CheckinController::class, 'restoreAccess'])->middleware('staff.permission:stays.force_departure')->name('checkins.restore-access');
        Route::post('/checkins/{checkin}/key', [\App\Http\Controllers\Dashboard\CheckinController::class, 'generateKey'])->name('checkins.key');
        Route::get('/bookings', [\App\Http\Controllers\Dashboard\ReservationController::class, 'index'])->name('bookings');
        Route::get('/groups', [\App\Http\Controllers\Dashboard\GroupBookingController::class, 'index'])->name('groups.index');
        Route::post('/groups', [\App\Http\Controllers\Dashboard\GroupBookingController::class, 'storeGroup'])->name('groups.store');
        Route::post('/groups/{group}/members', [\App\Http\Controllers\Dashboard\GroupBookingController::class, 'addMember'])->name('groups.members.store');
        Route::patch('/groups/{group}/status', [\App\Http\Controllers\Dashboard\GroupBookingController::class, 'status'])->name('groups.status');
        Route::post('/corporate-accounts', [\App\Http\Controllers\Dashboard\GroupBookingController::class, 'storeAccount'])->name('corporate-accounts.store');
        Route::patch('/corporate-accounts/{account}', [\App\Http\Controllers\Dashboard\GroupBookingController::class, 'updateAccount'])->name('corporate-accounts.update');
        Route::middleware('staff.permission:stays.force_departure')->group(function () {
            Route::get('/receivables', [\App\Http\Controllers\Dashboard\CorporateBillingController::class, 'index'])->name('receivables.index');
            Route::get('/corporate-invoices/{invoice}', [\App\Http\Controllers\Dashboard\CorporateBillingController::class, 'show'])->name('corporate-invoices.show');
            Route::get('/corporate-accounts/{account}/statement.csv', [\App\Http\Controllers\Dashboard\CorporateBillingController::class, 'statement'])->name('corporate-accounts.statement');
            Route::get('/groups/{group}/master-folio', [\App\Http\Controllers\Dashboard\CorporateBillingController::class, 'master'])->name('groups.master-folio');
            Route::post('/groups/{group}/master-folio/transfer', [\App\Http\Controllers\Dashboard\CorporateBillingController::class, 'transfer'])->name('groups.master-folio.transfer');
            Route::post('/groups/{group}/invoices', [\App\Http\Controllers\Dashboard\CorporateBillingController::class, 'issue'])->name('groups.invoices.store');
            Route::post('/corporate-invoices/{invoice}/payments', [\App\Http\Controllers\Dashboard\CorporateBillingController::class, 'payment'])->name('corporate-invoices.payments.store');
            Route::post('/corporate-invoices/{invoice}/credits', [\App\Http\Controllers\Dashboard\CorporateBillingController::class, 'credit'])->name('corporate-invoices.credits.store');
            Route::patch('/corporate-invoices/{invoice}/void', [\App\Http\Controllers\Dashboard\CorporateBillingController::class, 'void'])->name('corporate-invoices.void');
            Route::get('/deposits', [\App\Http\Controllers\Dashboard\AdvancePaymentController::class, 'index'])->name('deposits.index');
            Route::post('/deposits', [\App\Http\Controllers\Dashboard\AdvancePaymentController::class, 'store'])->name('deposits.store');
            Route::get('/deposits/{payment}/receipt', [\App\Http\Controllers\Dashboard\AdvancePaymentController::class, 'receipt'])->name('deposits.receipt');
            Route::post('/deposits/{payment}/allocations', [\App\Http\Controllers\Dashboard\AdvancePaymentController::class, 'allocate'])->name('deposits.allocations.store');
            Route::post('/deposits/{payment}/refund', [\App\Http\Controllers\Dashboard\AdvancePaymentController::class, 'refund'])->name('deposits.refund');
            Route::post('/deposits/{payment}/forfeit', [\App\Http\Controllers\Dashboard\AdvancePaymentController::class, 'forfeit'])->name('deposits.forfeit');
            Route::get('/communications', [\App\Http\Controllers\Dashboard\FinancialCommunicationController::class, 'index'])->name('communications.index');
            Route::post('/communications/send', [\App\Http\Controllers\Dashboard\FinancialCommunicationController::class, 'send'])->name('communications.send');
            Route::patch('/communications/{delivery}/retry', [\App\Http\Controllers\Dashboard\FinancialCommunicationController::class, 'retry'])->name('communications.retry');
            Route::patch('/communications/settings', [\App\Http\Controllers\Dashboard\FinancialCommunicationController::class, 'settings'])->name('communications.settings');
            Route::get('/notification-center', [\App\Http\Controllers\Dashboard\NotificationCenterController::class, 'index'])->name('notification-center.index');
            Route::patch('/notification-center/rules', [\App\Http\Controllers\Dashboard\NotificationCenterController::class, 'update'])->name('notification-center.rules');
            Route::get('/accounting', [\App\Http\Controllers\Dashboard\AccountingController::class, 'index'])->name('accounting.index');
            Route::patch('/accounting/settings', [\App\Http\Controllers\Dashboard\AccountingController::class, 'settings'])->name('accounting.settings');
            Route::patch('/accounting/integration', [\App\Http\Controllers\Dashboard\AccountingController::class, 'integration'])->name('accounting.integration');
            Route::post('/accounting/batches/{batch}/sync', [\App\Http\Controllers\Dashboard\AccountingController::class, 'sync'])->name('accounting.sync');
            Route::post('/accounting/audits/{audit}/generate', [\App\Http\Controllers\Dashboard\AccountingController::class, 'generate'])->name('accounting.generate');
            Route::patch('/accounting/batches/{batch}/post', [\App\Http\Controllers\Dashboard\AccountingController::class, 'post'])->name('accounting.post');
            Route::post('/accounting/batches/{batch}/reverse', [\App\Http\Controllers\Dashboard\AccountingController::class, 'reverse'])->name('accounting.reverse');
            Route::get('/accounting/batches/{batch}.{format}', [\App\Http\Controllers\Dashboard\AccountingController::class, 'download'])->name('accounting.download');
        });
        Route::get('/departure-reviews', [\App\Http\Controllers\Dashboard\ReservationLifecycleController::class, 'reviews'])->middleware('staff.permission:stays.force_departure')->name('departures.index');
        Route::get('/folios', [\App\Http\Controllers\Dashboard\FolioController::class, 'index'])->name('folios.index');
        Route::get('/folios/reconciliation.csv', [\App\Http\Controllers\Dashboard\FolioController::class, 'reconciliation'])->middleware('staff.permission:stays.force_departure')->name('folios.reconciliation');
        Route::middleware('staff.permission:stays.force_departure')->group(function () {
            Route::get('/night-audit', [\App\Http\Controllers\Dashboard\NightAuditController::class, 'index'])->name('night-audit.index');
            Route::post('/night-audit/close', [\App\Http\Controllers\Dashboard\NightAuditController::class, 'close'])->name('night-audit.close');
            Route::patch('/night-audit/{audit}/reopen', [\App\Http\Controllers\Dashboard\NightAuditController::class, 'reopen'])->name('night-audit.reopen');
            Route::get('/night-audit/{audit}/export.csv', [\App\Http\Controllers\Dashboard\NightAuditController::class, 'export'])->name('night-audit.export');
        });
        Route::get('/bookings/{reservation}/folio', [\App\Http\Controllers\Dashboard\FolioController::class, 'reservation'])->name('bookings.folio');
        Route::get('/folios/{folio}', [\App\Http\Controllers\Dashboard\FolioController::class, 'show'])->name('folios.show');
        Route::post('/folios/{folio}/items', [\App\Http\Controllers\Dashboard\FolioController::class, 'item'])->name('folios.items.store');
        Route::post('/folios/{folio}/payments', [\App\Http\Controllers\Dashboard\FolioController::class, 'payment'])->name('folios.payments.store');
        Route::post('/folios/{folio}/refunds', [\App\Http\Controllers\Dashboard\FolioController::class, 'refund'])->middleware('staff.permission:stays.force_departure')->name('folios.refunds.store');
        Route::patch('/folio-items/{item}/void', [\App\Http\Controllers\Dashboard\FolioController::class, 'void'])->middleware('staff.permission:stays.force_departure')->name('folio-items.void');
        Route::get('/bookings/{reservation}', [\App\Http\Controllers\Dashboard\ReservationLifecycleController::class, 'show'])->whereNumber('reservation')->name('bookings.show');
        Route::patch('/bookings/{reservation}', [\App\Http\Controllers\Dashboard\ReservationLifecycleController::class, 'update'])->name('bookings.update');
        Route::patch('/bookings/{reservation}/no-show', [\App\Http\Controllers\Dashboard\ReservationLifecycleController::class, 'noShow'])->name('bookings.no-show');
        Route::patch('/bookings/{reservation}/stay-dates', [\App\Http\Controllers\Dashboard\ReservationLifecycleController::class, 'changeStay'])->name('bookings.stay-dates');
        Route::patch('/bookings/{reservation}/transfer', [\App\Http\Controllers\Dashboard\ReservationLifecycleController::class, 'transfer'])->name('bookings.transfer');
        Route::patch('/departure-reviews/{departure}', [\App\Http\Controllers\Dashboard\ReservationLifecycleController::class, 'reviewDeparture'])->middleware('staff.permission:stays.force_departure')->name('departures.review');
        Route::patch('/guests/{guest}/release-restriction', [\App\Http\Controllers\Dashboard\ReservationLifecycleController::class, 'releaseRestriction'])->middleware('staff.permission:stays.force_departure')->name('guests.release-restriction');
        Route::get('/bookings/planner', [\App\Http\Controllers\Dashboard\ReservationPlannerController::class, 'index'])->name('bookings.planner');
        Route::patch('/bookings/{reservation}/move', [\App\Http\Controllers\Dashboard\ReservationPlannerController::class, 'move'])->name('bookings.move');
        Route::post('/inventory-blocks', [\App\Http\Controllers\Dashboard\ReservationPlannerController::class, 'storeBlock'])->name('inventory-blocks.store');
        Route::patch('/inventory-blocks/{block}/release', [\App\Http\Controllers\Dashboard\ReservationPlannerController::class, 'releaseBlock'])->name('inventory-blocks.release');
        Route::post('/room-rate-rules', [\App\Http\Controllers\Dashboard\ReservationPlannerController::class, 'storeRule'])->name('room-rate-rules.store');
        Route::delete('/room-rate-rules/{rule}', [\App\Http\Controllers\Dashboard\ReservationPlannerController::class, 'destroyRule'])->name('room-rate-rules.destroy');
        Route::post('/bookings', [\App\Http\Controllers\Dashboard\ReservationController::class, 'store'])->name('bookings.store');
        Route::post('/bookings/{reservation}/check-in', [\App\Http\Controllers\Dashboard\ReservationController::class, 'checkIn'])->name('bookings.checkin');
        Route::patch('/bookings/{reservation}/cancel', [\App\Http\Controllers\Dashboard\ReservationController::class, 'cancel'])->name('bookings.cancel');
        Route::patch('/pre-arrivals/{submission}/review', [\App\Http\Controllers\Dashboard\ReservationController::class, 'reviewPreArrival'])->name('pre-arrivals.review');
        Route::get('/pre-arrivals/{submission}/documents/{side}', [\App\Http\Controllers\Dashboard\ReservationController::class, 'document'])->name('pre-arrivals.document');
    });
    Route::get('/rooms', [\App\Http\Controllers\Dashboard\DashboardRoutes::class, 'RoomsScreen'])->middleware('staff.permission:rooms.view')->name('rooms');
    Route::get('/rooms/{room}/history', [\App\Http\Controllers\Dashboard\RoomController::class, 'history'])->middleware('staff.permission:rooms.view')->name('rooms.history');
    Route::middleware('staff.permission:rooms.manage')->group(function () {
        Route::get('/locks', [\App\Http\Controllers\Dashboard\LockController::class, 'index'])->middleware('hotel.feature:smart_locks')->name('locks.index');
        Route::post('/locks', [\App\Http\Controllers\Dashboard\LockController::class, 'store'])->middleware('hotel.feature:smart_locks')->name('locks.store');
        Route::middleware('hotel.feature:smart_locks')->group(function () {
            Route::post('/lock-providers', [\App\Http\Controllers\Dashboard\LockController::class, 'saveProvider'])->name('locks.providers.store');
            Route::post('/lock-providers/{provider}/test', [\App\Http\Controllers\Dashboard\LockController::class, 'testProvider'])->name('locks.providers.test');
            Route::post('/lock-providers/{provider}/discover', [\App\Http\Controllers\Dashboard\LockController::class, 'discover'])->name('locks.providers.discover');
            Route::post('/locks/import', [\App\Http\Controllers\Dashboard\LockController::class, 'import'])->name('locks.import');
            Route::patch('/locks/{device}/assign', [\App\Http\Controllers\Dashboard\LockController::class, 'assign'])->name('locks.assign');
            Route::patch('/locks/{device}/unassign', [\App\Http\Controllers\Dashboard\LockController::class, 'unassign'])->name('locks.unassign');
            Route::post('/rooms/{room}/device', [\App\Http\Controllers\Dashboard\LockController::class, 'pair'])->name('locks.pair');
            Route::patch('/rooms/{room}/access-marker', [\App\Http\Controllers\Dashboard\LockController::class, 'rotateMarker'])->name('locks.marker.rotate');
            Route::post('/lock-devices/{device}/unlock', [\App\Http\Controllers\Dashboard\LockController::class, 'unlock'])->middleware('staff.permission:locks.unlock')->name('locks.unlock');
            Route::post('/lock-devices/{device}/sync', [\App\Http\Controllers\Dashboard\LockController::class, 'sync'])->name('locks.sync');
            Route::post('/lock-devices/{device}/emergency', [\App\Http\Controllers\Dashboard\LockController::class, 'emergency'])->name('locks.emergency');
            Route::post('/lock-sync-attempts/{attempt}/retry', [\App\Http\Controllers\Dashboard\LockController::class, 'retry'])->name('locks.retry');
        });
        Route::post('/rooms', [\App\Http\Controllers\Dashboard\RoomController::class, 'store'])->middleware('hotel.limit:rooms')->name('rooms.store');
        Route::patch('/rooms/{room}', [\App\Http\Controllers\Dashboard\RoomController::class, 'update'])->name('rooms.update');
        Route::patch('/rooms/{room}/lock', [\App\Http\Controllers\Dashboard\RoomController::class, 'toggleLock'])->name('rooms.lock');
    });
    Route::get('/settings', [\App\Http\Controllers\Dashboard\DashboardRoutes::class, 'SettingScreen'])->middleware('staff.permission:settings')->name('settings');
    Route::post('/settings/property', [\App\Http\Controllers\Dashboard\PropertySettingsController::class, 'update'])->middleware('staff.permission:settings')->name('settings.property');
    Route::middleware('staff.permission:settings')->group(function () {
        Route::get('/settings/financial', [\App\Http\Controllers\Dashboard\FinancialSettingsController::class, 'index'])->name('financial-rules.index');
        Route::post('/settings/financial', [\App\Http\Controllers\Dashboard\FinancialSettingsController::class, 'store'])->name('financial-rules.store');
        Route::patch('/settings/financial/{rule}', [\App\Http\Controllers\Dashboard\FinancialSettingsController::class, 'update'])->name('financial-rules.update');
    });
    Route::post('/settings/fcm', [\App\Http\Controllers\Dashboard\FcmSettingsController::class, 'update'])->middleware('staff.permission:settings')->name('settings.fcm');
    Route::post('/settings/fcm/test', [\App\Http\Controllers\Dashboard\FcmSettingsController::class, 'test'])->middleware('staff.permission:settings')->name('settings.fcm.test');
    Route::patch('/privacy-requests/{privacyRequest}', [\App\Http\Controllers\Dashboard\GuestPrivacyController::class, 'review'])->middleware('staff.permission:settings')->name('privacy-requests.review');
    Route::middleware(['hotel.feature:housekeeping', 'staff.permission:housekeeping'])->group(function () {
        Route::get('/housekeeping', [\App\Http\Controllers\Dashboard\HousekeepingController::class, 'index'])->name('housekeeping.index');
        Route::post('/housekeeping', [\App\Http\Controllers\Dashboard\HousekeepingController::class, 'store'])->name('housekeeping.store');
        Route::patch('/housekeeping/{task}', [\App\Http\Controllers\Dashboard\HousekeepingController::class, 'update'])->name('housekeeping.update');
    });
    Route::middleware(['hotel.feature:maintenance', 'staff.permission:maintenance'])->group(function () {
        Route::get('/maintenance', [\App\Http\Controllers\Dashboard\MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::post('/maintenance', [\App\Http\Controllers\Dashboard\MaintenanceController::class, 'store'])->name('maintenance.store');
        Route::patch('/maintenance/{order}', [\App\Http\Controllers\Dashboard\MaintenanceController::class, 'update'])->name('maintenance.update');
    });
    Route::middleware(['hotel.feature:food_beverage', 'staff.permission:food_beverage'])->group(function () {
        Route::get('/food-beverage', [\App\Http\Controllers\Dashboard\FoodBeverageController::class, 'index'])->name('food-beverage.index');
        Route::post('/food-beverage/categories', [\App\Http\Controllers\Dashboard\FoodBeverageController::class, 'category'])->name('food-beverage.categories.store');
        Route::post('/food-beverage/items', [\App\Http\Controllers\Dashboard\FoodBeverageController::class, 'item'])->name('food-beverage.items.store');
        Route::patch('/food-beverage/items/{item}', [\App\Http\Controllers\Dashboard\FoodBeverageController::class, 'updateItem'])->name('food-beverage.items.update');
        Route::patch('/food-beverage/orders/{order}/status', [\App\Http\Controllers\Dashboard\FoodBeverageController::class, 'status'])->name('food-beverage.orders.status');
    });
    Route::middleware('hotel.feature:conversations')->group(function () {
        Route::post('/guest-requests/{serviceRequest}/messages', [\App\Http\Controllers\Dashboard\GuestRequestController::class, 'message'])->name('guest-requests.messages');
        Route::post('/guest-requests/{serviceRequest}/complete', [\App\Http\Controllers\Dashboard\GuestRequestController::class, 'complete'])->name('guest-requests.complete');
        Route::patch('/guest-requests/{serviceRequest}/read', [\App\Http\Controllers\Dashboard\GuestRequestController::class, 'read'])->name('guest-requests.read');
        Route::get('/guest-requests/{serviceRequest}/messages/{message}/attachment', [\App\Http\Controllers\Dashboard\GuestRequestController::class, 'attachment'])->name('guest-requests.attachment');
    });
    Route::middleware('staff.permission:users')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Dashboard\UserController::class, 'index'])->name('users.index');
        Route::get('/roles', [\App\Http\Controllers\Dashboard\UserController::class, 'roles'])->name('roles.index');
        Route::post('/users', [\App\Http\Controllers\Dashboard\UserController::class, 'store'])->middleware('hotel.limit:staff')->name('users.store');
        Route::patch('/users/{user}', [\App\Http\Controllers\Dashboard\UserController::class, 'update'])->name('users.update');
        Route::post('/roles', [\App\Http\Controllers\Dashboard\UserController::class, 'storeRole'])->name('roles.store');
        Route::patch('/roles/{role}', [\App\Http\Controllers\Dashboard\UserController::class, 'updateRole'])->name('roles.update');
        Route::delete('/roles/{role}', [\App\Http\Controllers\Dashboard\UserController::class, 'destroyRole'])->name('roles.destroy');
        Route::patch('/users/{user}/role', [\App\Http\Controllers\Dashboard\UserController::class, 'assignRole'])->name('users.role');
    });
    Route::middleware(['hotel.feature:security', 'staff.permission:security'])->group(function () {
        Route::get('/security', [\App\Http\Controllers\Dashboard\SecurityController::class, 'index'])->name('security.index');
        Route::get('/security/export', [\App\Http\Controllers\Dashboard\SecurityController::class, 'export'])->name('security.export');
        Route::patch('/security/settings', [\App\Http\Controllers\Dashboard\SecurityController::class, 'settings'])->name('security.settings');
        Route::post('/security/test-alert', [\App\Http\Controllers\Dashboard\SecurityController::class, 'testAlert'])->name('security.test-alert');
        Route::post('/security/backups', [\App\Http\Controllers\Dashboard\SecurityController::class, 'createBackup'])->middleware('staff.permission:backups.manage')->name('security.backups.create');
        Route::post('/security/backups/{backup}/verify', [\App\Http\Controllers\Dashboard\SecurityController::class, 'verifyBackup'])->middleware('staff.permission:backups.manage')->name('security.backups.verify');
        Route::post('/security/failed-jobs/retry-all', [\App\Http\Controllers\Dashboard\SecurityController::class, 'retryAllFailedJobs'])->name('security.failed-jobs.retry-all');
        Route::post('/security/failed-jobs/{uuid}/retry', [\App\Http\Controllers\Dashboard\SecurityController::class, 'retryFailedJob'])->name('security.failed-jobs.retry');
        Route::delete('/security/failed-jobs/{uuid}', [\App\Http\Controllers\Dashboard\SecurityController::class, 'deleteFailedJob'])->name('security.failed-jobs.delete');
        Route::get('/integration-health', [\App\Http\Controllers\Dashboard\IntegrationHealthController::class, 'index'])->name('integration-health.index');
        Route::post('/integration-health/{health}/test', [\App\Http\Controllers\Dashboard\IntegrationHealthController::class, 'test'])->name('integration-health.test');
        Route::patch('/integration-health/{health}', [\App\Http\Controllers\Dashboard\IntegrationHealthController::class, 'update'])->name('integration-health.update');
        Route::post('/integration-health/{health}/reset', [\App\Http\Controllers\Dashboard\IntegrationHealthController::class, 'reset'])->name('integration-health.reset');
    });
    Route::middleware(['hotel.feature:reports', 'staff.permission:reports'])->group(function () {
        Route::get('/reports', [\App\Http\Controllers\Dashboard\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [\App\Http\Controllers\Dashboard\ReportController::class, 'export'])->name('reports.export');
    });
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/platform.php';

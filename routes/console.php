<?php

use App\Jobs\SyncLockDevice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('storage:asset-check', function () {
    $disk = config('filesystems.asset_disk', 'public');
    $path = 'health/storage-check-'.str()->uuid().'.txt';
    Storage::disk($disk)->put($path, 'HotelCheckin asset storage check '.now()->toISOString());
    $this->info("Write succeeded on [{$disk}]. URL: ".Storage::disk($disk)->url($path));
    Storage::disk($disk)->delete($path);
    $this->info('Read/write/delete asset storage check passed.');
})->purpose('Verify the configured public asset disk can write, resolve URLs, and delete.');

Artisan::command('privacy:purge-id-documents', function () {
    $purged = 0;
    \App\Models\Hotel::query()->each(function ($hotel) use (&$purged) {
        $days = (int) data_get($hotel->settings, 'branding.id_document_retention_days', 30);
        DB::table('pre_arrival_submissions')->where('hotel_id', $hotel->id)->whereNotNull('reviewed_at')->where('reviewed_at', '<=', now()->subDays(max(1, $days)))->where(fn ($query) => $query->whereNotNull('id_document_front')->orWhereNotNull('id_document_back'))->orderBy('id')->each(function ($submission) use (&$purged) {
            foreach ([$submission->id_document_front, $submission->id_document_back] as $path) {
                if ($path) {
                    Storage::disk('local')->delete($path);
                }
            }DB::table('pre_arrival_submissions')->where('id', $submission->id)->update(['id_document_front' => null, 'id_document_back' => null, 'id_number' => null, 'updated_at' => now()]);
            $purged++;
        });
    });
    $this->info("Purged identification documents from {$purged} submission(s).");
})->purpose('Delete reviewed guest ID documents after each hotel retention period.');

Schedule::command('privacy:purge-id-documents')->dailyAt('02:30')->withoutOverlapping();

Artisan::command('locks:health-check', function () {
    $count = 0;
    \App\Models\LockDevice::withoutGlobalScopes()->whereNotNull('room_id')->orderBy('id')->each(function ($device) use (&$count) {
        SyncLockDevice::dispatch($device->id);
        $count++;
    });
    $this->info("Queued health checks for {$count} lock(s).");
})->purpose('Queue a vendor-neutral status synchronization for every assigned lock.');

$lockInterval = max(1, min(59, (int) config('operations.lock_health_interval_minutes', 15)));
Schedule::command('locks:health-check')->cron("*/{$lockInterval} * * * *")->withoutOverlapping();
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever('operations.scheduler.last_run', now()->toISOString()))
    ->name('operations-heartbeat')->everyMinute()->withoutOverlapping();

Artisan::command('operations:monitor', function (\App\Services\Operations\OperationsMonitor $monitor) {
    $checked = 0;
    \App\Models\Hotel::where('status', 'active')->each(function ($hotel) use ($monitor, &$checked) {
        $monitor->inspect($hotel);
        $checked++;
    });
    $this->info("Checked operations for {$checked} hotel(s).");
})->purpose('Check each hotel for lock and mobile-delivery issues and send configured alerts.');

Schedule::command('operations:monitor')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:prune-failed', ['--hours' => 168])->dailyAt('03:15');
Schedule::command('queue:prune-batches', ['--hours' => 168, '--unfinished' => 168, '--cancelled' => 168])->dailyAt('03:20');
Artisan::command('backups:create', function () {
    \App\Models\Hotel::where('status', 'active')->each(fn ($hotel) => \App\Jobs\CreateHotelBackup::dispatch($hotel->id));
    $this->info('Queued backups for active hotels.');
});
Artisan::command('backups:verify', function (\App\Services\Backups\HotelBackupManager $manager) {
    \App\Models\Hotel::where('status', 'active')->each(function ($hotel) use ($manager) {
        $backup = \App\Models\HotelBackup::withoutGlobalScopes()->where('hotel_id', $hotel->id)->latest()->first();
        if ($backup) {
            try {
                $manager->verify($backup);
            } catch (\Throwable) {
            }
        } $manager->prune($hotel);
    });
    $this->info('Verified latest backups and applied retention.');
});
Schedule::command('backups:create')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('backups:verify')->dailyAt('04:00')->withoutOverlapping();

Artisan::command('subscriptions:process-trials', function () {
    $processed = 0;
    \App\Models\HotelSubscription::with('hotel')->whereIn('status', ['trialing', 'grace'])->each(function ($subscription) use (&$processed) {
        $admins = \App\Models\User::withoutGlobalScopes()->where('hotel_id', $subscription->hotel_id)->where('role', 'super_admin')->where('status', 'active')->get();

        if ($subscription->status === 'trialing' && $subscription->trial_ends_at?->isFuture()) {
            $days = max(1, (int) ceil(now()->diffInSeconds($subscription->trial_ends_at) / 86400));
            $sent = $subscription->trial_reminders_sent ?? [];
            if (in_array($days, [7, 3, 1], true) && ! in_array($days, $sent, true)) {
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\SubscriptionTrialNotice("Your {$subscription->hotel->name} free trial ends in {$days} day".($days === 1 ? '.' : 's.'), 'ending'));
                $subscription->update(['trial_reminders_sent' => [...$sent, $days]]);
                $processed++;
            }
            return;
        }

        if ($subscription->status === 'trialing' && $subscription->trial_ends_at?->isPast()) {
            $graceEnd = $subscription->trial_ends_at->copy()->addDays(config('subscriptions.grace_days'));
            $subscription->update(['status' => $graceEnd->isPast() ? 'expired' : 'grace', 'grace_ends_at' => $graceEnd]);
            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\SubscriptionTrialNotice($graceEnd->isPast() ? 'Your free trial and read-only grace period have expired.' : "Your free trial has ended. The portal is read-only until {$graceEnd->toFormattedDateString()}.", $graceEnd->isPast() ? 'expired' : 'grace'));
            $processed++;
            return;
        }

        if ($subscription->status === 'grace' && $subscription->grace_ends_at?->isPast()) {
            $subscription->update(['status' => 'expired']);
            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\SubscriptionTrialNotice('Your free trial and read-only grace period have expired.', 'expired'));
            $processed++;
        }
    });
    $this->info("Processed {$processed} trial subscription update(s).");
})->purpose('Send trial reminders and transition expired trials into read-only and expired states.');
Schedule::command('subscriptions:process-trials')->hourly()->withoutOverlapping();

Artisan::command('folios:post-nightly {date?}', function (\App\Services\NightAudit\NightAuditService $audit) {
    $date = \Carbon\Carbon::parse($this->argument('date') ?: today()->subDay()->toDateString())->startOfDay();
    $posted = 0;
    \App\Models\Hotel::where('status', 'active')->each(function ($hotel) use ($date, $audit, &$posted) {
        app()->instance('currentHotel', $hotel);
        $posted += $audit->postCharges($date);
    });
    $this->info("Posted {$posted} nightly room charge(s) for {$date->toDateString()}.");
})->purpose('Post idempotent nightly room charges to active guest folios.');
Schedule::command('folios:post-nightly')->dailyAt('00:10')->withoutOverlapping();

Artisan::command('communications:financial-reminders', function (\App\Services\Finance\FinancialCommunicator $communicator) {
    $queued = 0;
    \App\Models\Hotel::where('status', 'active')->each(function ($hotel) use ($communicator, &$queued) {
        $settings = $communicator->settings($hotel);
        if (! $settings['enabled']) {
            return;
        }
        \App\Models\CorporateInvoice::withoutGlobalScopes()->with('account')->where('hotel_id', $hotel->id)->whereNotIn('status', ['paid', 'void'])->each(function ($invoice) use ($hotel, $settings, $communicator, &$queued) {
            if (blank($invoice->account?->email)) {
                return;
            }
            $days = today($hotel->timezone)->diffInDays($invoice->due_date, false);
            $upcoming = (int) $days === (int) $settings['due_days_before'];
            $overdueDays = $invoice->due_date->diffInDays(today($hotel->timezone), false);
            $overdue = $overdueDays > 0 && $overdueDays % (int) $settings['overdue_repeat_days'] === 0;
            if ($upcoming || $overdue) {
                $communicator->queue($hotel, 'reminder', $invoice->id, $invoice->account->email, null, 'reminder:'.$invoice->id.':'.today($hotel->timezone)->toDateString());
                $queued++;
            }
        });
    });
    $this->info("Queued {$queued} financial reminder(s).");
})->purpose('Queue upcoming and overdue corporate invoice reminders.');
Schedule::command('communications:financial-reminders')->dailyAt('09:00')->withoutOverlapping();

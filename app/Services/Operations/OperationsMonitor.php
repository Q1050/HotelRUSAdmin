<?php

namespace App\Services\Operations;

use App\Models\Hotel;
use App\Models\HotelBackup;
use App\Models\LockDevice;
use App\Models\MobileNotification;
use App\Models\User;
use App\Notifications\OperationsAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class OperationsMonitor
{
    public static function defaults(): array
    {
        return [
            'alerts_enabled' => true,
            'email_enabled' => true,
            'alert_roles' => ['super_admin', 'manager'],
            'alert_emails' => '',
            'low_battery_threshold' => 25,
            'lock_offline_minutes' => 30,
            'fcm_failure_threshold' => 3,
            'alert_cooldown_minutes' => 60,
        ];
    }

    public function settings(Hotel $hotel): array
    {
        return array_merge(self::defaults(), data_get($hotel->settings, 'operations', []));
    }

    public function summary(Hotel $hotel): array
    {
        $settings = $this->settings($hotel);
        $locks = LockDevice::withoutGlobalScopes()->where('hotel_id', $hotel->id);
        $offline = (clone $locks)->where(function ($query) use ($settings) {
            $query->where('status', '!=', 'online')
                ->orWhereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', now()->subMinutes($settings['lock_offline_minutes']));
        })->count();
        $lowBattery = (clone $locks)->where('battery_level', '<=', $settings['low_battery_threshold'])->count();
        $fcmFailures = MobileNotification::withoutGlobalScopes()->where('hotel_id', $hotel->id)
            ->where('updated_at', '>=', now()->subHour())
            ->whereIn('delivery_status', ['failed', 'retrying', 'partial'])->count();
        $latestBackup = Schema::hasTable('hotel_backups') ? HotelBackup::withoutGlobalScopes()->where('hotel_id', $hotel->id)->latest()->first() : null;
        $backupIssue = ! $latestBackup || in_array($latestBackup->status, ['failed', 'corrupt'], true) || ! $latestBackup->verified_at || $latestBackup->created_at->lt(now()->subHours(config('operations.backup_stale_hours'))) || $latestBackup->size_bytes > config('operations.backup_max_mb') * 1048576 || count(data_get($latestBackup?->manifest, 'missing_files', [])) > 0;

        return [
            'offlineLocks' => $offline,
            'lowBatteryLocks' => $lowBattery,
            'fcmFailures' => $fcmFailures,
            'backupIssue' => $backupIssue,
            'lastBackupAt' => $latestBackup?->created_at?->toISOString(),
            'status' => ($offline || $lowBattery || $fcmFailures >= $settings['fcm_failure_threshold'] || $backupIssue) ? 'attention' : 'healthy',
        ];
    }

    public function inspect(Hotel $hotel, bool $force = false): array
    {
        $settings = $this->settings($hotel);
        $summary = $this->summary($hotel);
        if (! $settings['alerts_enabled'] && ! $force) {
            return $summary;
        }

        $issues = [];
        if ($summary['offlineLocks']) {
            $issues[] = "{$summary['offlineLocks']} lock(s) are offline or stale";
        }
        if ($summary['lowBatteryLocks']) {
            $issues[] = "{$summary['lowBatteryLocks']} lock(s) have low batteries";
        }
        if ($summary['fcmFailures'] >= $settings['fcm_failure_threshold']) {
            $issues[] = "{$summary['fcmFailures']} mobile deliveries failed in the last hour";
        }
        if ($summary['backupIssue']) {
            $issues[] = 'the latest property backup is missing, stale, failed, or unverified';
        }
        if ($force && ! $issues) {
            $issues[] = 'This is a test alert; current hotel operations are healthy';
        }
        if (! $issues) {
            return $summary;
        }

        $fingerprint = hash('sha256', implode('|', $issues));
        $cooldown = max(5, (int) $settings['alert_cooldown_minutes']);
        if (! $force && ! Cache::add("operations-alert:{$hotel->id}:{$fingerprint}", true, now()->addMinutes($cooldown))) {
            return $summary;
        }

        $title = $force ? "{$hotel->name}: test operations alert" : "{$hotel->name}: operations need attention";
        $message = implode('. ', $issues).'.';
        $users = User::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('status', 'active')
            ->whereIn('role', $settings['alert_roles'])->get();
        Notification::send($users, new OperationsAlert($title, $message, 'warning', (bool) $settings['email_enabled']));

        if ($settings['email_enabled']) {
            collect(preg_split('/[,;\s]+/', (string) $settings['alert_emails'], -1, PREG_SPLIT_NO_EMPTY))
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                ->each(fn ($email) => Notification::route('mail', $email)->notify(new OperationsAlert($title, $message)));
        }

        return $summary;
    }
}

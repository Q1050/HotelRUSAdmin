<?php

namespace Tests\Feature;

use App\Jobs\SyncLockDevice;
use App\Models\LockDevice;
use App\Models\Room;
use App\Models\User;
use App\Notifications\OperationsAlert;
use App\Services\Backups\HotelBackupManager;
use App\Services\Operations\OperationsMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductionOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_operational_checks_without_secrets(): void
    {
        Cache::forever('operations.scheduler.last_run', now()->toISOString());

        $this->getJson('/health')->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.scheduler.status', 'ok')
            ->assertJsonPath('version', config('version.number'))
            ->assertJsonMissing(['password', 'token', 'credentials']);
    }

    public function test_lock_health_command_queues_every_assigned_lock(): void
    {
        Queue::fake();
        $room = Room::create(['number' => '950', 'type' => 'Standard', 'floor' => 9, 'status' => 'available', 'price' => 200]);
        $device = LockDevice::create(['room_id' => $room->id, 'provider' => 'simulator', 'external_id' => 'ops-950', 'name' => 'Room 950 Lock', 'status' => 'online', 'battery_level' => 90]);

        $this->artisan('locks:health-check')->assertSuccessful();

        Queue::assertPushed(SyncLockDevice::class, fn ($job) => $job->deviceId === $device->id && $job->queue === 'locks');
    }

    public function test_security_center_shows_safe_failed_job_summary(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        DB::table('failed_jobs')->insert([
            'uuid' => '00000000-0000-4000-8000-000000000001', 'connection' => 'database', 'queue' => 'notifications',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\DeliverMobileNotification', 'data' => ['command' => 'private serialized data']]),
            'exception' => "RuntimeException: Provider unavailable\nprivate stack trace", 'failed_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('dashboard.security.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('failedJobs.0.name', 'App\\Jobs\\DeliverMobileNotification')
            ->where('failedJobs.0.error', 'RuntimeException: Provider unavailable')
            ->missing('failedJobs.0.payload')
            ->where('queueStats.failed', 1));
    }

    public function test_property_operations_alerts_are_configurable_and_sent_to_selected_roles(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $manager = User::factory()->create(['hotel_id' => $admin->hotel_id, 'role' => 'manager']);
        $room = Room::create(['number' => '951', 'type' => 'Standard', 'floor' => 9, 'status' => 'available', 'price' => 200]);
        LockDevice::create(['room_id' => $room->id, 'provider' => 'simulator', 'external_id' => 'ops-951', 'name' => 'Room 951 Lock', 'status' => 'offline', 'battery_level' => 10]);

        $payload = ['retention_days' => 365, 'two_factor_enabled' => false, 'alerts_enabled' => true, 'email_enabled' => true,
            'alert_roles' => ['super_admin', 'manager'], 'alert_emails' => '', 'low_battery_threshold' => 20,
            'lock_offline_minutes' => 30, 'fcm_failure_threshold' => 3, 'alert_cooldown_minutes' => 60];
        $this->actingAs($admin)->patch(route('dashboard.security.settings'), $payload)->assertSessionHasNoErrors();

        app(OperationsMonitor::class)->inspect($admin->hotel->fresh());

        Notification::assertSentTo([$admin, $manager], OperationsAlert::class);
        $this->assertSame(20, data_get($admin->hotel->fresh()->settings, 'operations.low_battery_threshold'));
    }

    public function test_property_backup_is_encrypted_verified_and_detects_corruption(): void
    {
        Storage::fake('local');
        config(['operations.backup_disk' => 'local']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        Room::create(['number' => '952', 'type' => 'Standard', 'floor' => 9, 'status' => 'available', 'price' => 200]);

        $backup = app(HotelBackupManager::class)->create($admin->hotel_id);

        $this->assertSame('verified', $backup->status);
        $this->assertGreaterThan(0, $backup->manifest['records']);
        Storage::disk('local')->assertExists($backup->path);
        $this->assertStringNotContainsString('952', Storage::disk('local')->get($backup->path));

        Storage::disk('local')->put($backup->path, 'corrupt');
        try {
            app(HotelBackupManager::class)->verify($backup);
        } catch (\Throwable) {
        }
        $this->assertSame('corrupt', $backup->fresh()->status);
    }
}

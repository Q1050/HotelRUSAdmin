<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = ['application' => ['status' => 'ok']];
        try {
            DB::select('select 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (Throwable) {
            $checks['database'] = ['status' => 'failed'];
        }

        $lastRun = Cache::get('operations.scheduler.last_run');
        $schedulerHealthy = $lastRun && now()->diffInMinutes($lastRun) <= (int) config('operations.scheduler_stale_minutes', 5);
        $checks['scheduler'] = ['status' => $schedulerHealthy ? 'ok' : 'warning', 'last_run_at' => $lastRun];
        $checks['queue'] = [
            'status' => 'ok',
            'pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
        ];
        $checks['storage'] = ['status' => is_writable(storage_path()) ? 'ok' : 'failed'];
        $healthy = $checks['database']['status'] === 'ok' && $checks['storage']['status'] === 'ok';

        return response()->json([
            'status' => $healthy ? ($schedulerHealthy ? 'ok' : 'degraded') : 'failed',
            'version' => config('version.number'),
            'checks' => $checks,
            'checked_at' => now()->toISOString(),
        ], $healthy ? 200 : 503);
    }
}

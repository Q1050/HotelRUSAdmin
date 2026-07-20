<?php

namespace App\Services\Backups;

use App\Models\Hotel;
use App\Models\HotelBackup;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HotelBackupManager
{
    public function create(int $hotelId): HotelBackup
    {
        $hotel = Hotel::findOrFail($hotelId);
        $disk = config('operations.backup_disk');
        $backup = HotelBackup::withoutGlobalScopes()->create(['hotel_id' => $hotel->id, 'uuid' => (string) Str::uuid(), 'disk' => $disk, 'status' => 'running']);
        try {
            $tables = [];
            $records = 0;
            foreach (Schema::getTableListing() as $table) {
                if ($table === 'hotel_backups' || ! Schema::hasColumn($table, 'hotel_id')) {
                    continue;
                }
                $rows = DB::table($table)->where('hotel_id', $hotel->id)->get()->map(fn ($row) => (array) $row)->all();
                if ($rows) {
                    $tables[$table] = $rows;
                    $records += count($rows);
                }
            }
            $files = [];
            $missing = [];
            foreach ($this->filePaths($hotel->id) as $path) {
                if (Storage::disk('local')->exists($path)) {
                    $files[$path] = base64_encode(Storage::disk('local')->get($path));
                } else {
                    $missing[] = $path;
                }
            }
            $manifest = ['format' => 1, 'hotel_id' => $hotel->id, 'created_at' => now()->toISOString(), 'tables' => collect($tables)->map(fn ($rows) => count($rows)), 'records' => $records, 'files' => count($files), 'missing_files' => $missing];
            $payload = gzencode(json_encode(['manifest' => $manifest, 'hotel' => $hotel->toArray(), 'database' => $tables, 'files' => $files], JSON_THROW_ON_ERROR), 9);
            $encrypted = Crypt::encryptString($payload);
            $path = "hotel-backups/{$hotel->id}/{$backup->uuid}.backup";
            if (! Storage::disk($disk)->put($path, $encrypted)) {
                throw new RuntimeException('Backup storage rejected the bundle.');
            }
            $backup->update(['path' => $path, 'status' => 'completed', 'size_bytes' => strlen($encrypted), 'checksum' => hash('sha256', $encrypted), 'manifest' => $manifest, 'completed_at' => now()]);

            return $this->verify($backup);
        } catch (Throwable $e) {
            $backup->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }
    }

    public function verify(HotelBackup $backup): HotelBackup
    {
        $backup = HotelBackup::withoutGlobalScopes()->findOrFail($backup->id);
        try {
            $encrypted = Storage::disk($backup->disk)->get($backup->path);
            if (hash('sha256', $encrypted) !== $backup->checksum) {
                throw new RuntimeException('Backup checksum does not match.');
            }
            $data = json_decode(gzdecode(Crypt::decryptString($encrypted)), true, flags: JSON_THROW_ON_ERROR);
            if (($data['manifest']['hotel_id'] ?? null) !== $backup->hotel_id || empty($data['database'])) {
                throw new RuntimeException('Backup manifest is incomplete.');
            }
            $backup->update(['status' => 'verified', 'verified_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $backup->update(['status' => 'corrupt', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }

        return $backup->fresh();
    }

    public function prune(Hotel $hotel): int
    {
        $cutoff = now()->subDays((int) data_get($hotel->settings, 'backup.retention_days', config('operations.backup_retention_days')));
        $count = 0;
        HotelBackup::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('created_at', '<', $cutoff)->each(function ($backup) use (&$count) {
            if ($backup->path) {
                Storage::disk($backup->disk)->delete($backup->path);
            } $backup->delete();
            $count++;
        });

        return $count;
    }

    private function filePaths(int $hotelId): array
    {
        $paths = DB::table('pre_arrival_submissions')->where('hotel_id', $hotelId)->get(['id_document_front', 'id_document_back'])->flatMap(fn ($r) => [$r->id_document_front, $r->id_document_back]);
        $requestIds = DB::table('guest_service_requests')->where('hotel_id', $hotelId)->pluck('id');

        return $paths->merge(DB::table('guest_service_requests')->whereIn('id', $requestIds)->pluck('completion_photo'))->merge(DB::table('guest_request_messages')->whereIn('guest_service_request_id', $requestIds)->pluck('attachment_path'))->filter()->unique()->values()->all();
    }
}

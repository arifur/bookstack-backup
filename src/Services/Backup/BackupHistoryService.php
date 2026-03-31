<?php

namespace Arifur\BookstackBackup\Services\Backup;

use Arifur\BookstackBackup\Models\Backup;
use BookStack\Users\Models\User;
use Illuminate\Support\Facades\File;

class BackupHistoryService
{
    /**
     * @return array<array<string, mixed>>
     */
    public function getBackups(string $backupPath): array
    {
        $deletedUserIds = Backup::query()
            ->whereNotNull('deleted_by')
            ->pluck('deleted_by')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $deletedUsers = User::query()
            ->whereIn('id', $deletedUserIds)
            ->pluck('name', 'id');

        return Backup::query()
            ->orderByRaw("CASE WHEN status = 'deleted' THEN 1 ELSE 0 END ASC")
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Backup $backup) use ($backupPath, $deletedUsers) {
                $filename = $backup->file_path ?: $backup->title;
                $filePath = $backupPath . '/' . $filename;
                $createdAt = $backup->created_at?->timestamp ?? 0;
                $deletedByName = null;
                if (is_numeric($backup->deleted_by)) {
                    $deletedByName = $deletedUsers->get((int) $backup->deleted_by);
                }

                return [
                    'filename' => $filename,
                    'path' => $filePath,
                    'size' => File::exists($filePath) ? $this->formatFileSize(File::size($filePath)) : '-',
                    'created_at' => $createdAt,
                    'created_date' => $backup->created_at?->format('Y-m-d H:i:s') ?? '',
                    'sha256' => $backup->sha_hash,
                    'status' => $backup->status ?? 'pending',
                    'deleted_by_name' => $deletedByName,
                ];
            })
            ->all();
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

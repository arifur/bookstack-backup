<?php

namespace Arifur\BookstackBackup\Services\Backup;

use Arifur\BookstackBackup\Models\Backup;

class BackupAuditService
{
    public function recordBackupCreation(string $filename, string $shaHash, ?int $currentUserId): void
    {
        Backup::query()->updateOrCreate(
            ['file_path' => $filename],
            [
                'title' => $filename,
                'file_path' => $filename,
                'sha_hash' => $shaHash,
                'status' => 'completed',
                'created_by' => $currentUserId !== null ? (string) $currentUserId : 'system',
            ]
        );
    }

    public function recordBackupDownload(string $filename, ?int $currentUserId): void
    {
        $backup = Backup::query()->firstOrCreate(
            ['file_path' => $filename],
            [
                'title' => $filename,
                'created_by' => 'system',
            ]
        );

        $updatedDownloadedBy = $this->appendUserIdToDownloadHistory($backup->downloaded_by, $currentUserId);

        $backup->forceFill([
            'downloaded_by' => $updatedDownloadedBy,
        ])->save();
    }

    public function recordBackupDeletion(string $filename, ?int $currentUserId): void
    {
        $backup = Backup::query()->firstOrCreate(
            ['file_path' => $filename],
            [
                'title' => $filename,
                'created_by' => 'system',
            ]
        );

        $backup->forceFill([
            'deleted_by' => $currentUserId !== null ? (string) $currentUserId : null,
            'status' => 'deleted',
        ])->save();
    }

    private function appendUserIdToDownloadHistory(?string $downloadHistoryText, ?int $userId): ?string
    {
        if ($userId === null) {
            return $downloadHistoryText;
        }

        $userIdString = (string) $userId;
        $existingIds = $downloadHistoryText ? array_filter(array_map('trim', explode(',', $downloadHistoryText))) : [];

        if (!in_array($userIdString, $existingIds, true)) {
            $existingIds[] = $userIdString;
        }

        return implode(',', $existingIds);
    }
}

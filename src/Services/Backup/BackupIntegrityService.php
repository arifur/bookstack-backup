<?php

namespace Arifur\BookstackBackup\Services\Backup;

use Arifur\BookstackBackup\Models\Backup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BackupIntegrityService
{
    /**
     * @return array{valid: bool, hash?: string, error?: string}
     */
    public function validateAndHashBackup(string $backupPath, string $filename): array
    {
        $zip = new \ZipArchive();
        $openResult = $zip->open($backupPath);

        if ($openResult !== true) {
            return [
                'valid' => false,
                'error' => "ZIP archive validation failed (Code: $openResult)",
            ];
        }

        if ($zip->numFiles === 0) {
            $zip->close();
            return [
                'valid' => false,
                'error' => 'Backup archive is empty',
            ];
        }

        $zip->close();

        $hash = hash_file('sha256', $backupPath);
        if (!$hash) {
            return [
                'valid' => false,
                'error' => 'Failed to calculate SHA256 hash',
            ];
        }

        Log::info('Backup hash calculated and stored', [
            'filename' => $filename,
            'sha256' => $hash,
            'size' => File::size($backupPath),
        ]);

        return [
            'valid' => true,
            'hash' => $hash,
        ];
    }

    /**
     * @return array{valid: bool, message: string, sha256?: string, stored_hash?: string, current_hash?: string}
     */
    public function verifyBackupIntegrity(string $filename, string $storagePath): array
    {
        $filePath = rtrim($storagePath, '/') . '/' . $filename;

        if (!File::exists($filePath)) {
            return [
                'valid' => false,
                'message' => 'Backup file not found',
            ];
        }

        $backup = Backup::query()->where('file_path', $filename)->first();
        if ($backup === null || empty($backup->sha_hash)) {
            return [
                'valid' => false,
                'message' => 'SHA256 hash not found in database for this backup',
            ];
        }

        $storedHash = trim((string) $backup->sha_hash);
        $currentHash = hash_file('sha256', $filePath);

        if ($storedHash !== $currentHash) {
            Log::warning('Backup integrity check failed', [
                'filename' => $filename,
                'stored_hash' => $storedHash,
                'current_hash' => $currentHash,
            ]);

            return [
                'valid' => false,
                'message' => 'Backup file appears corrupted (hash mismatch)',
                'stored_hash' => $storedHash,
                'current_hash' => $currentHash,
            ];
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [
                'valid' => false,
                'message' => 'Backup archive is not readable',
            ];
        }
        $zip->close();

        Log::info('Backup integrity verified', [
            'filename' => $filename,
            'sha256' => $currentHash,
        ]);

        return [
            'valid' => true,
            'message' => 'Backup archive verified successfully',
            'sha256' => $currentHash,
        ];
    }
}

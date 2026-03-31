<?php

namespace Arifur\BookstackBackup\Services\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BackupArchiveService
{
    public function createDatabaseDump(string $dbPath): bool
    {
        $dbHost = env('DB_HOST', 'localhost');
        $dbDatabase = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD', '');

        if (!$dbDatabase || !$dbUser) {
            return false;
        }

        $passwordArg = $dbPass !== '' ? ' -p' . escapeshellarg($dbPass) : '';
        $command = sprintf(
            'mysqldump -h %s -u %s%s %s > %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $passwordArg,
            escapeshellarg($dbDatabase),
            escapeshellarg($dbPath)
        );

        $result = Process::run($command);
        if (!$result->successful()) {
            Log::error('Database dump command failed', [
                'command' => $command,
                'error' => $result->errorOutput(),
            ]);
        }

        return $result->successful() && File::exists($dbPath);
    }

    public function addDiskFilesToZip(\ZipArchive $zip, string $diskName, string $sourcePath, string $archivePrefix): void
    {
        $disk = Storage::disk($diskName);
        if (!$disk->exists($sourcePath)) {
            return;
        }

        $sourcePath = trim($sourcePath, '/');
        $archivePrefix = trim($archivePrefix, '/');

        foreach ($disk->allFiles($sourcePath) as $diskFilePath) {
            $absolutePath = $disk->path($diskFilePath);
            if (!is_file($absolutePath)) {
                continue;
            }

            $relativePath = ltrim(substr($diskFilePath, strlen($sourcePath)), '/');
            $zip->addFile($absolutePath, $archivePrefix . '/' . $relativePath);
        }
    }
}

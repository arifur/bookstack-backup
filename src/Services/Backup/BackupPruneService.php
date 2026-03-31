<?php

namespace Arifur\BookstackBackup\Services\Backup;

use Illuminate\Support\Facades\File;

class BackupPruneService
{
    public function pruneBackups(string $backupPath, int $maxBackups): void
    {
        if (!File::exists($backupPath)) {
            return;
        }

        $files = array_filter(File::files($backupPath), function ($file) {
            return strtolower($file->getExtension()) === 'zip';
        });

        usort($files, function ($left, $right) {
            return $right->getCTime() <=> $left->getCTime();
        });

        foreach ($files as $index => $file) {
            $shouldDeleteByCount = $index >= $maxBackups;

            if ($shouldDeleteByCount) {
                File::delete($file->getPathname());
            }
        }
    }
}

<?php

namespace Arifur\BookstackBackup\Services\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BackupCreationService
{
    public function __construct(
        protected BackupArchiveService $archiveService,
        protected BackupProgressService $progressService,
        protected BackupRemoteUploadService $remoteUploadService,
        protected BackupIntegrityService $integrityService,
        protected BackupAuditService $auditService,
        protected BackupPruneService $pruneService,
    ) {
    }

    /**
     * @param array{
     *   filename_prefix: string,
     *   storage_path: string,
     *   include_database: bool,
     *   include_files: bool,
     *   remote_upload_on_create: bool,
     *   remote_default_provider: string,
        *   progress_token?: string,
    *   remote: array{
    *     ftp: array{enabled: bool, host: string, port: int, username: string, password: string, path: string, passive: bool},
    *     google_drive: array{enabled: bool, access_token: string, folder_id: string}
    *   },
     *   max_backups: int
     * } $options
     * @return array{success: bool, error_key?: string}
     */
    public function createBackup(array $options, ?int $currentUserId): array
    {
        $progressToken = isset($options['progress_token']) && is_string($options['progress_token']) ? $options['progress_token'] : null;
        $timestamp = date('Y-m-d_H-i-s');
        $filename = $options['filename_prefix'] . '_' . $timestamp . '.zip';
        $backupPath = $options['storage_path'];

        if ($progressToken !== null) {
            $this->progressService->update($progressToken, 5, 'Preparing backup', 'preparing');
        }

        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        if (!$options['include_database'] && !$options['include_files']) {
            if ($progressToken !== null) {
                $this->progressService->fail($progressToken, 'No backup sources selected');
            }

            return ['success' => false, 'error_key' => 'backup_nothing_selected'];
        }

        $dbPath = $backupPath . '/database_' . $timestamp . '.sql';
        $zip = new \ZipArchive();
        $zipFilePath = $backupPath . '/' . $filename;

        Log::info(
            "Creating backup: {$filename} (Include DB: " . ($options['include_database'] ? 'Yes' : 'No') .
            ", Include Files: " . ($options['include_files'] ? 'Yes' : 'No') . ')'
        );

        if ($zip->open($zipFilePath, \ZipArchive::CREATE) !== true) {
            if ($progressToken !== null) {
                $this->progressService->fail($progressToken, 'Could not create archive');
            }

            return ['success' => false, 'error_key' => 'backup_failed'];
        }

        if ($options['include_database']) {
            if ($progressToken !== null) {
                $this->progressService->update($progressToken, 12, 'Exporting database', 'preparing');
            }

            $databaseCreated = $this->archiveService->createDatabaseDump($dbPath);
            if (!$databaseCreated || !File::exists($dbPath)) {
                $zip->close();
                if (File::exists($zipFilePath)) {
                    File::delete($zipFilePath);
                }

                if ($progressToken !== null) {
                    $this->progressService->fail($progressToken, 'Database export failed');
                }

                return ['success' => false, 'error_key' => 'backup_database_failed'];
            }

            $zip->addFile($dbPath, 'database.sql');
        }

        if ($options['include_files']) {
            if ($progressToken !== null) {
                $this->progressService->update($progressToken, 22, 'Adding uploaded files', 'preparing');
            }

            $this->archiveService->addDiskFilesToZip($zip, 'local', 'uploads', 'storage');
            $this->archiveService->addDiskFilesToZip($zip, 'public', 'uploads', 'uploads');
        }

        $zip->close();

        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }

        if ($progressToken !== null) {
            $this->progressService->update($progressToken, 35, 'Validating archive', 'preparing');
        }

        $validationResult = $this->integrityService->validateAndHashBackup($zipFilePath, $filename);
        if (!$validationResult['valid']) {
            Log::error('Backup archive validation failed', [
                'filename' => $filename,
                'error' => $validationResult['error'] ?? 'unknown',
            ]);
            File::delete($zipFilePath);

            if ($progressToken !== null) {
                $this->progressService->fail($progressToken, 'Archive validation failed');
            }

            return ['success' => false, 'error_key' => 'backup_validation_failed'];
        }

        if ($options['remote_upload_on_create'] && $options['remote_default_provider'] !== 'none') {
            if ($progressToken !== null) {
                $this->progressService->update($progressToken, 45, 'Uploading backup', 'uploading');
            }

            $uploaded = $this->remoteUploadService->uploadBackupToRemote(
                $zipFilePath,
                $filename,
                $options['remote_default_provider'],
                $options['remote'],
                $progressToken
            );

            if (!$uploaded) {
                if ($progressToken !== null) {
                    $this->progressService->fail($progressToken, 'Remote upload failed');
                }

                return ['success' => false, 'error_key' => 'backup_remote_upload_failed'];
            }
        }

        if ($progressToken !== null) {
            $this->progressService->update($progressToken, 95, 'Finalizing backup', 'finalizing');
        }

        $this->auditService->recordBackupCreation($filename, $validationResult['hash'], $currentUserId);
        $this->pruneService->pruneBackups($backupPath, $options['max_backups']);

        if ($progressToken !== null) {
            $this->progressService->complete($progressToken, 'Backup upload completed');
        }

        return ['success' => true];
    }
}

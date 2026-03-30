<?php

namespace Arifur\BookstackBackup\Http\Controllers;

use Arifur\BookstackBackup\Settings\BackupSettingsStore;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use BookStack\App\AppVersion;
use BookStack\Http\Controller;
use BookStack\Permissions\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\Rule;
use Throwable;

class BackupController extends Controller
{
    protected const SECTION_BACKUP = 'backup';
    protected const SECTION_SCHEDULE = 'schedule';
    protected const SECTION_BACKUP_SETTINGS = 'backup-settings';
    protected const SECTION_REMOTE = 'remote';

    public function index()
    {
        return $this->renderSection(self::SECTION_BACKUP);
    }

    public function schedule()
    {
        return $this->renderSection(self::SECTION_SCHEDULE);
    }

    public function backupSettings()
    {
        return $this->renderSection(self::SECTION_BACKUP_SETTINGS);
    }

    public function remote()
    {
        return $this->renderSection(self::SECTION_REMOTE);
    }

    public function updateBackupSettings(Request $request, BackupSettingsStore $settingsStore)
    {
        $this->validate($request, [
            'setting-backup-filename-prefix' => ['required', 'string', 'max:100'],
            'setting-backup-include-database' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-include-files' => ['required', Rule::in(['true', 'false'])],
        ]);

        return $this->persistSettings(
            fn () => $settingsStore->storeBackupSettings($request),
            '/settings/backups'
        );
    }

    public function updateScheduleSettings(Request $request, BackupSettingsStore $settingsStore)
    {
        $this->validate($request, [
            'setting-backup-schedule-enabled' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-schedule-frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'setting-backup-schedule-time' => ['required', 'date_format:H:i'],
            'setting-backup-schedule-day-of-week' => ['required', 'integer', 'between:0,6'],
            'setting-backup-schedule-day-of-month' => ['required', 'integer', 'between:1,28'],
            'setting-backup-schedule-keep-local-copy' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-schedule-notify-email' => ['nullable', 'email', 'max:255'],
        ]);

        return $this->persistSettings(
            fn () => $settingsStore->storeScheduleSettings($request),
            '/settings/backups/schedule'
        );
    }

    public function updateBackupSettingsSection(Request $request, BackupSettingsStore $settingsStore)
    {
        $this->validate($request, [
            'setting-backup-filename-prefix' => ['required', 'string', 'max:100'],
            'setting-backup-include-database' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-include-files' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-max-backups' => ['required', 'integer', 'between:1,1000'],
        ]);

        return $this->persistSettings(
            function () use ($settingsStore, $request) {
                $settingsStore->storeBackupSettings($request);
                $settingsStore->storeBackupSettingsSection($request);
                // Apply max-backup changes immediately instead of waiting for next backup run.
                $this->pruneBackups();
            },
            '/settings/backups/backup-settings'
        );
    }

    public function updateRemoteSettings(Request $request, BackupSettingsStore $settingsStore)
    {
        $this->validate($request, [
            'setting-backup-remote-default-provider' => ['required', Rule::in(['none', 'ftp'])],
            'setting-backup-remote-upload-on-schedule' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-ftp-enabled' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-ftp-host' => ['nullable', 'string', 'max:255'],
            'setting-backup-ftp-port' => ['nullable', 'integer', 'between:1,65535'],
            'setting-backup-ftp-username' => ['nullable', 'string', 'max:255'],
            'setting-backup-ftp-password' => ['nullable', 'string', 'max:255'],
            'setting-backup-ftp-path' => ['nullable', 'string', 'max:255'],
            'setting-backup-ftp-passive' => ['required', Rule::in(['true', 'false'])],
        ]);

        return $this->persistSettings(
            fn () => $settingsStore->storeRemoteSettings($request),
            '/settings/backups/remote'
        );
    }

    public function create(Request $request)
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filenamePrefix = $this->setting('backup-filename-prefix', 'bookstack_backup');
        $filename = "{$filenamePrefix}_{$timestamp}.zip";
        $backupPath = config('backups.storage_path');
        $includeDatabase = $this->boolSetting('backup-include-database', true);
        $includeFiles = $this->boolSetting('backup-include-files', true);

        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        if (!$includeDatabase && !$includeFiles) {
            return redirect('/settings/backups')
                ->with('error', trans('bookstack-backup::settings.backup_nothing_selected'));
        }

        $dbPath = config('backups.storage_path') . "/database_{$timestamp}.sql";
        $zip = new \ZipArchive();
        $zipFilePath = $backupPath . '/' . $filename;

        Log::info(
            "Creating backup: {$filename} (Include DB: " . ($includeDatabase ? 'Yes' : 'No') .
            ", Include Files: " . ($includeFiles ? 'Yes' : 'No') . ")"
        );

        if ($zip->open($zipFilePath, \ZipArchive::CREATE) === true) {
            if ($includeDatabase) {
                $databaseCreated = $this->createDatabaseDump($dbPath);
                if (!$databaseCreated || !File::exists($dbPath)) {
                    $zip->close();
                    if (File::exists($zipFilePath)) {
                        File::delete($zipFilePath);
                    }

                    return redirect('/settings/backups')
                        ->with('error', trans('bookstack-backup::settings.backup_database_failed'));
                }

                $zip->addFile($dbPath, 'database.sql');
            }

            if ($includeFiles) {
                $this->addDiskFilesToZip($zip, 'local', 'uploads', 'storage');
                $this->addDiskFilesToZip($zip, 'public', 'uploads', 'uploads');
            }

            $zip->close();

            if (File::exists($dbPath)) {
                File::delete($dbPath);
            }

            if (
                $this->boolSetting('backup-remote-upload-on-create', false)
                && $this->setting('backup-remote-default-provider', 'none') === 'ftp'
            ) {
                $this->uploadBackupToFtp($zipFilePath, $filename);
            }

            $this->pruneBackups();

            return redirect('/settings/backups')
                ->with('success', trans('bookstack-backup::settings.backup_created'));
        }

        return redirect('/settings/backups')
            ->with('error', trans('bookstack-backup::settings.backup_failed'));
    }

    public function downloadBackup($filename)
    {
        $backupPath = config('backups.storage_path');
        $filePath = $backupPath . '/' . $filename;

        if (File::exists($filePath)) {
            return response()->download($filePath)->deleteFileAfterSend(false);
        }

        return redirect('/settings/backups')
            ->with('error', trans('bookstack-backup::settings.backup_not_found'));
    }

    public function delete($filename)
    {
        $backupPath = config('backups.storage_path');
        $filePath = $backupPath . '/' . $filename;

        if (File::exists($filePath)) {
            File::delete($filePath);

            return redirect('/settings/backups')
                ->with('success', trans('bookstack-backup::settings.backup_deleted'));
        }

        return redirect('/settings/backups')
            ->with('error', trans('bookstack-backup::settings.backup_not_found'));
    }

    public function confirmDelete($filename)
    {
        $backupPath = config('backups.storage_path');
        $filePath = $backupPath . '/' . $filename;

        if (!File::exists($filePath)) {
            return redirect('/settings/backups')
                ->with('error', trans('bookstack-backup::settings.backup_not_found'));
        }

        $this->checkPermission(Permission::SettingsManage);
        $this->setPageTitle(trans('bookstack-backup::settings.history_delete'));

        
        return view('bookstack-backup::parts.backups.delete-confirm', [
            'selected' => 'backups',
            'section' => self::SECTION_BACKUP,
            'version' => AppVersion::get(),
            'sections' => $this->sectionLinks(),
            'filename' => $filename,
        ]);
    }

    protected function renderSection(string $section)
    {
        $this->checkPermission(Permission::SettingsManage);
        $this->setPageTitle(trans('bookstack-backup::settings.backups'));

        return view('bookstack-backup::backups', [
            'selected' => 'backups',
            'section' => $section,
            'version' => AppVersion::get(),
            'backups' => $this->getBackups(),
            'sections' => $this->sectionLinks(),
        ]);
    }

    protected function sectionLinks(): array
    {
        return [
            ['key' => self::SECTION_BACKUP, 'label' => trans('bookstack-backup::settings.menu_backup'), 'url' => url('/settings/backups')],
            ['key' => self::SECTION_SCHEDULE, 'label' => trans('bookstack-backup::settings.menu_schedule'), 'url' => url('/settings/backups/schedule')],
            ['key' => self::SECTION_BACKUP_SETTINGS, 'label' => trans('bookstack-backup::settings.menu_backup_settings'), 'url' => url('/settings/backups/backup-settings')],
            ['key' => self::SECTION_REMOTE, 'label' => trans('bookstack-backup::settings.menu_remote'), 'url' => url('/settings/backups/remote')],
        ];
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        return setting($key, $default);
    }

    protected function boolSetting(string $key, bool $default = false): bool
    {
        $value = setting($key, $default ? 'true' : 'false');

        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, ['true', '1', 1, true], true);
    }

    protected function createDatabaseDump(string $dbPath): bool
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

        protected function addDiskFilesToZip(\ZipArchive $zip, string $diskName, string $sourcePath, string $archivePrefix): void
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

        protected function uploadBackupToFtp(string $localFilePath, string $filename): bool
        {
            if (!File::exists($localFilePath) || !$this->boolSetting('backup-ftp-enabled', false)) {
                return false;
            }

            $host = (string) $this->setting('backup-ftp-host', '');
            $username = (string) $this->setting('backup-ftp-username', '');
            if ($host === '' || $username === '') {
                Log::warning('FTP upload skipped due to missing host or username settings.');
                return false;
            }

            $remoteBasePath = trim((string) $this->setting('backup-ftp-path', '/'), '/');
            $remoteFilePath = ($remoteBasePath !== '' ? $remoteBasePath . '/' : '') . $filename;

            try {
                $ftpDisk = Storage::build([
                    'driver' => 'ftp',
                    'host' => $host,
                    'port' => (int) $this->setting('backup-ftp-port', 21),
                    'username' => $username,
                    'password' => (string) $this->setting('backup-ftp-password', ''),
                    'root' => '/',
                    'passive' => $this->boolSetting('backup-ftp-passive', true),
                    'ssl' => false,
                    'timeout' => 30,
                ]);

                $stream = fopen($localFilePath, 'r');
                if ($stream === false) {
                    Log::error('FTP upload failed: Could not read local backup file stream.', ['path' => $localFilePath]);
                    return false;
                }

                try {
                    $uploaded = (bool) $ftpDisk->put($remoteFilePath, $stream);
                } finally {
                    fclose($stream);
                }

                if (!$uploaded) {
                    Log::error('FTP upload failed while writing file.', ['remote_path' => $remoteFilePath]);
                    return false;
                }

                Log::info('Backup uploaded to FTP successfully.', ['remote_path' => $remoteFilePath]);
                return true;
            } catch (Throwable $exception) {
                Log::error('FTP upload failed with exception.', [
                    'message' => $exception->getMessage(),
                    'remote_path' => $remoteFilePath,
                ]);

                return false;
            }
        }

    protected function persistSettings(callable $callback, string $redirectPath)
    {
        try {
            $callback();
            $this->showSuccessNotification(trans('bookstack-backup::settings.settings_saved'));

            return redirect($redirectPath);
        } catch (Throwable $exception) {
            report($exception);
            $this->showErrorNotification(trans('bookstack-backup::settings.settings_save_failed'));

            return redirect($redirectPath)->withInput();
        }
    }

    protected function pruneBackups(): void
    {
        $backupPath = config('backups.storage_path');
        if (!File::exists($backupPath)) {
            return;
        }

        $maxBackups = (int) setting('backup-max-backups', config('backups.max_backups', 10));

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

    private function getBackups()
    {
        $backupPath = config('backups.storage_path');
        $backups = [];

        if (File::exists($backupPath)) {
            $files = array_filter(File::files($backupPath), function ($file) {
                return strtolower($file->getExtension()) === 'zip';
            });
            foreach ($files as $file) {
                $backups[] = [
                    'filename' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $this->formatFileSize($file->getSize()),
                    'created_at' => $file->getCTime(),
                    'created_date' => date('Y-m-d H:i:s', $file->getCTime()),
                ];
            }

            usort($backups, function ($left, $right) {
                return $right['created_at'] <=> $left['created_at'];
            });
        }

        return $backups;
    }

    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
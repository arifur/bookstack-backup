<?php

namespace Arifur\BookstackBackup\Console\Commands;

use Arifur\BookstackBackup\Services\Backup\BackupCreationService;
use BookStack\Settings\SettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunScheduledBackupCommand extends Command
{
    protected $signature = 'bookstack-backup:run-scheduled';

    protected $description = 'Runs scheduled backup process for the BookStack backup package';

    public function handle(BackupCreationService $creationService, SettingService $settings): int
    {
        if (!$this->boolSetting('backup-schedule-enabled', false)) {
            return self::SUCCESS;
        }

        $now = Carbon::now();
        $scheduledTime = (string) setting('backup-schedule-time', '02:00');

        if ($now->format('H:i') !== $scheduledTime) {
            return self::SUCCESS;
        }

        $frequency = (string) setting('backup-schedule-frequency', 'daily');
        if (!$this->isDueForFrequency($now, $frequency)) {
            return self::SUCCESS;
        }

        $periodToken = $this->periodToken($now, $frequency);
        $lastPeriodToken = (string) setting('backup-schedule-last-period-token', '');
        if ($lastPeriodToken === $periodToken) {
            return self::SUCCESS;
        }

        $result = $creationService->createBackup([
            'filename_prefix' => (string) setting('backup-filename-prefix', 'bookstack_backup'),
            'storage_path' => (string) config('backups.storage_path'),
            'include_database' => $this->boolSetting('backup-include-database', true),
            'include_files' => $this->boolSetting('backup-include-files', true),
            'remote_upload_on_create' => $this->boolSetting('backup-remote-upload-on-schedule', false),
            'remote_default_provider' => (string) setting('backup-remote-default-provider', 'none'),
            'remote' => [
                'ftp' => [
                    'enabled' => $this->boolSetting('backup-ftp-enabled', false),
                    'host' => (string) setting('backup-ftp-host', ''),
                    'port' => (int) setting('backup-ftp-port', 21),
                    'username' => (string) setting('backup-ftp-username', ''),
                    'password' => (string) setting('backup-ftp-password', ''),
                    'path' => (string) setting('backup-ftp-path', '/'),
                    'passive' => $this->boolSetting('backup-ftp-passive', true),
                ],
                'google_drive' => [
                    'enabled' => $this->boolSetting('backup-google-drive-enabled', false),
                    'access_token' => (string) setting('backup-google-drive-access-token', ''),
                    'folder_id' => (string) setting('backup-google-drive-folder-id', ''),
                ],
            ],
            'max_backups' => (int) setting('backup-max-backups', config('backups.max_backups', 10)),
        ], null);

        if (!$result['success']) {
            $this->error('Scheduled backup failed: ' . ($result['error_key'] ?? 'backup_failed'));
            return self::FAILURE;
        }

        $settings->put('backup-schedule-last-period-token', $periodToken);
        $settings->put('backup-schedule-last-run-at', $now->toDateTimeString());

        $this->info('Scheduled backup completed.');

        return self::SUCCESS;
    }

    private function isDueForFrequency(Carbon $now, string $frequency): bool
    {
        if ($frequency === 'weekly') {
            $configuredDow = (int) setting('backup-schedule-day-of-week', 0);
            return $now->dayOfWeek === $configuredDow;
        }

        if ($frequency === 'monthly') {
            $configuredDom = (int) setting('backup-schedule-day-of-month', 1);
            return $now->day === $configuredDom;
        }

        return true;
    }

    private function periodToken(Carbon $now, string $frequency): string
    {
        if ($frequency === 'weekly') {
            return $now->format('o-W');
        }

        if ($frequency === 'monthly') {
            return $now->format('Y-m');
        }

        return $now->format('Y-m-d');
    }

    private function boolSetting(string $key, bool $default = false): bool
    {
        $value = setting($key, $default ? 'true' : 'false');

        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, ['true', '1', 1, true], true);
    }
}

<?php

namespace Arifur\BookstackBackup\Console\Commands;

use Arifur\BookstackBackup\Services\Backup\BackupCreationService;
use BookStack\Settings\SettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RunScheduledBackupCommand extends Command
{
    protected $signature = 'bookstack-backup:run-scheduled';

    protected $description = 'Runs scheduled backup process for the BookStack backup package';

    public function handle(BackupCreationService $creationService, SettingService $settings): int
    {
        if (!$this->boolSetting('backup-schedule-enabled', false)) {
            // Log::debug('Scheduled backup skipped: schedule disabled.');
            return self::SUCCESS;
        }

        $timezone = $this->scheduleTimezone();
        $now = Carbon::now($timezone);
        $scheduledTime = (string) setting('backup-schedule-time', '02:00');

        if (!$this->isScheduledTimeReached($now, $scheduledTime)) {
            // Log::debug('Scheduled backup skipped: before configured time.', [
            //     'now' => $now->toDateTimeString(),
            //     'timezone' => $timezone,
            //     'scheduled_time' => $scheduledTime,
            // ]);
            return self::SUCCESS;
        }

        $frequency = (string) setting('backup-schedule-frequency', 'daily');
        if (!$this->isDueForFrequency($now, $frequency)) {
            // Log::debug('Scheduled backup skipped: frequency/day conditions not met.', [
            //     'now' => $now->toDateTimeString(),
            //     'frequency' => $frequency,
            //     'configured_day_of_week' => (int) setting('backup-schedule-day-of-week', 0),
            //     'configured_day_of_month' => (int) setting('backup-schedule-day-of-month', 1),
            // ]);
            return self::SUCCESS;
        }

        $periodToken = $this->periodToken($now, $frequency);
        $lastPeriodToken = (string) setting('backup-schedule-last-period-token', '');
        if ($lastPeriodToken === $periodToken) {
            // Log::debug('Scheduled backup skipped: period already processed.', [
            //     'period_token' => $periodToken,
            // ]);
            return self::SUCCESS;
        }

        Log::info('Scheduled backup started.', [
            'period_token' => $periodToken,
            'frequency' => $frequency,
            'now' => $now->toDateTimeString(),
            'timezone' => $timezone,
        ]);

        $remoteConfig = [
            'ftp' => [
                'enabled' => $this->boolSetting('backup-ftp-enabled', false),
                'host' => (string) setting('backup-ftp-host', ''),
                'port' => (int) setting('backup-ftp-port', 21),
                'username' => (string) setting('backup-ftp-username', ''),
                'password' => (string) setting('backup-ftp-password', ''),
                'path' => (string) setting('backup-ftp-path', '/'),
                'passive' => true,
            ],
        ];

        $result = $creationService->createBackup([
            'filename_prefix' => (string) setting('backup-filename-prefix', 'bookstack_backup'),
            'storage_path' => (string) config('backups.storage_path'),
            'include_database' => $this->boolSetting('backup-include-database', true),
            'include_files' => $this->boolSetting('backup-include-files', true),
            'remote_upload_on_create' => $this->boolSetting('backup-remote-enabled', true)
                && $this->boolSetting('backup-remote-upload-on-schedule', false),
            'remote_providers' => $this->enabledRemoteProviders($remoteConfig),
            'remote' => $remoteConfig,
            'max_backups' => (int) setting('backup-max-backups', config('backups.max_backups', 10)),
        ], null);

        if (!$result['success']) {
            Log::error('Scheduled backup failed.', [
                'error_key' => $result['error_key'] ?? 'backup_failed',
                'period_token' => $periodToken,
            ]);
            $this->error('Scheduled backup failed: ' . ($result['error_key'] ?? 'backup_failed'));
            return self::FAILURE;
        }

        $settings->put('backup-schedule-last-period-token', $periodToken);
        $settings->put('backup-schedule-last-run-at', $now->toDateTimeString());

        Log::info('Scheduled backup completed.', [
            'period_token' => $periodToken,
            'completed_at' => $now->toDateTimeString(),
            'timezone' => $timezone,
        ]);

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

    private function isScheduledTimeReached(Carbon $now, string $scheduledTime): bool
    {
        $parts = explode(':', $scheduledTime);
        if (count($parts) !== 2) {
            return false;
        }

        $hour = (int) $parts[0];
        $minute = (int) $parts[1];
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return false;
        }

        $scheduledAt = $now->copy()->setTime($hour, $minute);

        return $now->greaterThanOrEqualTo($scheduledAt);
    }

    private function boolSetting(string $key, bool $default = false): bool
    {
        $value = setting($key, $default ? 'true' : 'false');

        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, ['true', '1', 1, true], true);
    }

    private function scheduleTimezone(): string
    {
        $timezone = (string) setting('backup-schedule-timezone', config('app.timezone', 'UTC'));

        return in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : (string) config('app.timezone', 'UTC');
    }

    /**
     * @return array<int, string>
     */
    private function enabledRemoteProviders(array $remoteConfig): array
    {
        $providers = [];

        foreach ($remoteConfig as $provider => $config) {
            if (is_string($provider) && is_array($config) && !empty($config['enabled'])) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}

<?php

namespace Arifur\BookstackBackup\Http\Controllers;

use Arifur\BookstackBackup\Settings\BackupSettingsStore;
use Arifur\BookstackBackup\Services\Backup\BackupPruneService;
use BookStack\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class BackupSettingsController extends Controller
{
    public function __construct(
        protected BackupPruneService $pruneService,
    ) {
    }

    public function updateBackupSettings(Request $request, BackupSettingsStore $settingsStore): RedirectResponse
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

    public function updateScheduleSettings(Request $request, BackupSettingsStore $settingsStore): RedirectResponse
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

    public function updateBackupSettingsSection(Request $request, BackupSettingsStore $settingsStore): RedirectResponse
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
                $this->pruneService->pruneBackups((string) config('backups.storage_path'), (int) setting('backup-max-backups', config('backups.max_backups', 10)));
            },
            '/settings/backups/backup-settings'
        );
    }

    public function updateRemoteSettings(Request $request, BackupSettingsStore $settingsStore): RedirectResponse
    {
        $this->validate($request, [
            'setting-backup-remote-default-provider' => ['required', Rule::in(['none', 'ftp', 'google_drive'])],
            'setting-backup-remote-upload-on-create' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-remote-upload-on-schedule' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-ftp-enabled' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-ftp-host' => ['nullable', 'string', 'max:255'],
            'setting-backup-ftp-port' => ['nullable', 'integer', 'between:1,65535'],
            'setting-backup-ftp-username' => ['nullable', 'string', 'max:255'],
            'setting-backup-ftp-password' => ['nullable', 'string', 'max:255'],
            'setting-backup-ftp-path' => ['nullable', 'string', 'max:255'],
            'setting-backup-ftp-passive' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-google-drive-enabled' => ['required', Rule::in(['true', 'false'])],
            'setting-backup-google-drive-access-token' => ['nullable', 'string', 'max:4096'],
            'setting-backup-google-drive-folder-id' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->persistSettings(
            fn () => $settingsStore->storeRemoteSettings($request),
            '/settings/backups/remote'
        );
    }

    protected function persistSettings(callable $callback, string $redirectPath): RedirectResponse
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
}

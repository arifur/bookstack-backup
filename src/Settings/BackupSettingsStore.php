<?php

namespace Arifur\BookstackBackup\Settings;

use BookStack\Settings\SettingService;
use Illuminate\Http\Request;

class BackupSettingsStore
{
    public function __construct(
        protected SettingService $settings,
    ) {
    }

    public function storeBackupSettings(Request $request): void
    {
        $this->storeAllowed($request, [
            'backup-filename-prefix',
            'backup-include-database',
            'backup-include-files',
            'backup-remote-upload-on-create',
        ]);
    }

    public function storeScheduleSettings(Request $request): void
    {
        $this->storeAllowed($request, [
            'backup-schedule-enabled',
            'backup-schedule-frequency',
            'backup-schedule-time',
            'backup-schedule-day-of-week',
            'backup-schedule-day-of-month',
            'backup-schedule-timezone',
            'backup-schedule-keep-local-copy',
            'backup-schedule-notify-email',
        ]);
    }

    public function storeBackupSettingsSection(Request $request): void
    {
        $this->storeAllowed($request, [
            'backup-max-backups',
        ]);
    }

    public function storeRemoteSettings(Request $request): void
    {
        $this->storeAllowed($request, [
            'backup-remote-default-provider',
            'backup-remote-upload-on-schedule',
            'backup-ftp-enabled',
            'backup-ftp-host',
            'backup-ftp-port',
            'backup-ftp-username',
            'backup-ftp-password',
            'backup-ftp-path',
            'backup-ftp-passive',
        ]);
    }

    protected function storeAllowed(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            $requestKey = 'setting-' . $key;
            if (!$request->exists($requestKey)) {
                continue;
            }

            $this->settings->put($key, $request->input($requestKey));
        }
    }
}
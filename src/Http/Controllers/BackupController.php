<?php

namespace Arifur\BookstackBackup\Http\Controllers;

use Arifur\BookstackBackup\Services\Backup\BackupAuditService;
use Arifur\BookstackBackup\Services\Backup\BackupCreationService;
use Arifur\BookstackBackup\Services\Backup\BackupHistoryService;
use Arifur\BookstackBackup\Services\Backup\BackupIntegrityService;
use Arifur\BookstackBackup\Services\Backup\BackupProgressService;
use Illuminate\Support\Facades\Auth;
use BookStack\App\AppVersion;
use BookStack\Http\Controller;
use BookStack\Permissions\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class BackupController extends Controller
{
    protected const SECTION_BACKUP = 'backup';
    protected const SECTION_SCHEDULE = 'schedule';
    protected const SECTION_BACKUP_SETTINGS = 'backup-settings';
    protected const SECTION_REMOTE = 'remote';

    public function __construct(
        protected BackupCreationService $creationService,
        protected BackupIntegrityService $integrityService,
        protected BackupAuditService $auditService,
        protected BackupHistoryService $historyService,
        protected BackupProgressService $progressService,
    ) {
    }

    public function index(): View
    {
        return $this->renderSection(self::SECTION_BACKUP);
    }

    public function schedule(): View
    {
        return $this->renderSection(self::SECTION_SCHEDULE);
    }

    public function backupSettings(): View
    {
        return $this->renderSection(self::SECTION_BACKUP_SETTINGS);
    }

    public function remote(): View
    {
        return $this->renderSection(self::SECTION_REMOTE);
    }

    public function create(Request $request): RedirectResponse|JsonResponse
    {
        $progressToken = $request->string('progress_token')->toString();
        $remoteUploadEnabled = $this->boolSetting('backup-remote-enabled', true)
            && $this->boolSetting('backup-remote-upload-on-create', false);
        $remoteConfig = [
            'ftp' => [
                'enabled' => $this->boolSetting('backup-ftp-enabled', false),
                'host' => (string) $this->setting('backup-ftp-host', ''),
                'port' => (int) $this->setting('backup-ftp-port', 21),
                'username' => (string) $this->setting('backup-ftp-username', ''),
                'password' => (string) $this->setting('backup-ftp-password', ''),
                'path' => (string) $this->setting('backup-ftp-path', '/'),
                'passive' => true,
            ],
        ];
        $remoteProviders = $this->enabledRemoteProviders($remoteConfig);

        if ($progressToken !== '' && $remoteUploadEnabled && count($remoteProviders) > 0) {
            $this->progressService->start($progressToken, $remoteProviders);
        }

        $result = $this->creationService->createBackup([
            'filename_prefix' => (string) $this->setting('backup-filename-prefix', 'bookstack_backup'),
            'storage_path' => (string) config('backups.storage_path'),
            'include_database' => $this->boolSetting('backup-include-database', true),
            'include_files' => $this->boolSetting('backup-include-files', true),
            'remote_upload_on_create' => $remoteUploadEnabled,
            'remote_providers' => $remoteProviders,
            'progress_token' => $progressToken !== '' ? $progressToken : null,
            'remote' => $remoteConfig,
            'max_backups' => (int) setting('backup-max-backups', config('backups.max_backups', 10)),
        ], Auth::id());

        if ($result['success']) {
            $message = trans('bookstack-backup::settings.backup_created');

            if ($request->expectsJson()) {
                $request->session()->flash('success', $message);

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'redirect_url' => url('/settings/backups'),
                ]);
            }

            return redirect('/settings/backups')->with('success', $message);
        }

        $message = trans('bookstack-backup::settings.' . ($result['error_key'] ?? 'backup_failed'));

        if ($progressToken !== '') {
            $this->progressService->fail($progressToken, $message);
        }

        if ($request->expectsJson()) {
            $request->session()->flash('error', $message);

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect('/settings/backups')->with('error', $message);
    }

    public function createProgress(string $token): JsonResponse
    {
        return response()->json($this->progressService->get($token));
    }

    public function downloadBackup($filename): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
    {
        $backupPath = config('backups.storage_path');
        $filePath = $backupPath . '/' . $filename;

        if (File::exists($filePath)) {
            $this->auditService->recordBackupDownload($filename, Auth::id());

            return response()->download($filePath)->deleteFileAfterSend(false);
        }

        return redirect('/settings/backups')
            ->with('error', trans('bookstack-backup::settings.backup_not_found'));
    }

    public function delete($filename): RedirectResponse
    {
        $backupPath = config('backups.storage_path');
        $filePath = $backupPath . '/' . $filename;

        $this->auditService->recordBackupDeletion($filename, Auth::id());

        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        return redirect('/settings/backups')
            ->with('success', trans('bookstack-backup::settings.backup_deleted'));
    }

    public function confirmDelete($filename): RedirectResponse|View
    {
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

    protected function renderSection(string $section): View
    {
        $this->checkPermission(Permission::SettingsManage);
        $this->setPageTitle(trans('bookstack-backup::settings.backups'));

        return view('bookstack-backup::backups', [
            'selected' => 'backups',
            'section' => $section,
            'version' => AppVersion::get(),
            'backups' => $this->historyService->getBackups(rtrim((string) config('backups.storage_path'), '/')),
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

    /**
     * @return array<int, string>
     */
    protected function enabledRemoteProviders(array $remoteConfig): array
    {
        $providers = [];

        foreach ($remoteConfig as $provider => $config) {
            if (is_string($provider) && is_array($config) && !empty($config['enabled'])) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Verify backup archive integrity using SHA256 hash stored in database.
     */
    public function verifyBackupIntegrity(string $filename): array
    {
        return $this->integrityService->verifyBackupIntegrity($filename, (string) config('backups.storage_path'));
    }
}
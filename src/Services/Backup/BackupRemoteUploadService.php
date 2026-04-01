<?php

namespace Arifur\BookstackBackup\Services\Backup;

use FTP\Connection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupRemoteUploadService
{
    public function __construct(
        protected BackupProgressService $progressService,
    ) {
    }

    /**
     * @param array{
     *   ftp: array{enabled: bool, host: string, port: int, username: string, password: string, path: string, passive: bool}
     * } $config
     */
    public function uploadBackupToRemote(string $localFilePath, string $filename, string $provider, array $config, ?string $progressToken = null, int $providerIndex = 0, int $providerCount = 1): bool
    {
        if ($provider === 'ftp') {
            return $this->uploadBackupToFtp($localFilePath, $filename, $config['ftp'], $progressToken, $providerIndex, $providerCount);
        }

        return false;
    }

    /**
     * @param array{enabled: bool, host: string, port: int, username: string, password: string, path: string, passive: bool} $config
     */
    private function uploadBackupToFtp(string $localFilePath, string $filename, array $config, ?string $progressToken = null, int $providerIndex = 0, int $providerCount = 1): bool
    {
        if (!File::exists($localFilePath) || !$config['enabled']) {
            return false;
        }

        if ($config['host'] === '' || $config['username'] === '') {
            Log::warning('FTP upload skipped due to missing host or username settings.');
            return false;
        }

        $remoteBasePath = trim($config['path'], '/');
        $remoteFilePath = ($remoteBasePath !== '' ? $remoteBasePath . '/' : '') . $filename;
        $timeoutSeconds = 300;
        $fileSize = filesize($localFilePath);

        if ($fileSize === false) {
            Log::error('FTP upload failed: Could not determine local file size.', ['path' => $localFilePath]);
            if ($progressToken !== null) {
                $this->progressService->fail($progressToken, 'Could not determine upload size');
            }

            return false;
        }

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            if (!function_exists('ftp_connect')) {
                Log::error('FTP upload failed: PHP FTP extension is not available.');
                return false;
            }

            $connection = @ftp_connect($config['host'], $config['port'], $timeoutSeconds);
            if ($connection === false) {
                Log::error('FTP upload failed: Could not connect to FTP server.', [
                    'host' => $config['host'],
                    'port' => $config['port'],
                ]);

                return false;
            }

            $stream = fopen($localFilePath, 'r');
            if ($stream === false) {
                @ftp_close($connection);
                Log::error('FTP upload failed: Could not open local file stream.', ['path' => $localFilePath]);
                if ($progressToken !== null) {
                    $this->progressService->fail($progressToken, 'Could not read local backup file');
                }

                return false;
            }

            try {
                if (!@ftp_login($connection, $config['username'], $config['password'])) {
                    fclose($stream);
                    Log::error('FTP upload failed: Authentication failed.', [
                        'host' => $config['host'],
                        'username' => $config['username'],
                    ]);

                    if ($progressToken !== null) {
                        $this->progressService->fail($progressToken, 'FTP authentication failed');
                    }

                    return false;
                }

                @ftp_set_option($connection, FTP_TIMEOUT_SEC, $timeoutSeconds);

                if (!@ftp_pasv($connection, (bool) $config['passive'])) {
                    Log::warning('FTP upload: Could not set passive mode preference.', [
                        'passive' => (bool) $config['passive'],
                    ]);
                }

                if ($remoteBasePath !== '' && !$this->ensureFtpDirectoryExists($connection, $remoteBasePath)) {
                    fclose($stream);
                    Log::error('FTP upload failed: Could not create or access remote directory.', [
                        'remote_path' => $remoteBasePath,
                    ]);

                    if ($progressToken !== null) {
                        $this->progressService->fail($progressToken, 'Could not create remote directory');
                    }

                    return false;
                }

                $this->updateUploadProgress($progressToken, $providerIndex, $providerCount, 0.0, $this->uploadingMessage('FTP', $providerIndex, $providerCount));

                $status = @ftp_nb_fput($connection, $remoteFilePath, $stream, FTP_BINARY);
                while ($status === FTP_MOREDATA) {
                    $bytesTransferred = ftell($stream);
                    if ($progressToken !== null && $bytesTransferred !== false) {
                        $uploadRatio = $fileSize > 0 ? ($bytesTransferred / $fileSize) : 1.0;
                        $this->updateUploadProgress($progressToken, $providerIndex, $providerCount, $uploadRatio, $this->uploadingMessage('FTP', $providerIndex, $providerCount));
                    }

                    $status = @ftp_nb_continue($connection);
                }

                fclose($stream);
                $uploaded = $status === FTP_FINISHED;
            } finally {
                @ftp_close($connection);
            }

            if (!$uploaded) {
                Log::error('FTP upload failed while writing file.', ['remote_path' => $remoteFilePath]);
                if ($progressToken !== null) {
                    $this->progressService->fail($progressToken, 'FTP upload failed');
                }
                return false;
            }

            Log::info('Backup uploaded to FTP successfully.', ['remote_path' => $remoteFilePath]);
            $this->updateUploadProgress($progressToken, $providerIndex, $providerCount, 1.0, $this->uploadingMessage('FTP', $providerIndex, $providerCount));
            return true;
        } catch (Throwable $exception) {
            Log::error('FTP upload failed with exception.', [
                'message' => $exception->getMessage(),
                'remote_path' => $remoteFilePath,
            ]);

            if ($progressToken !== null) {
                $this->progressService->fail($progressToken, 'FTP upload failed');
            }

            return false;
        }
    }

    private function ensureFtpDirectoryExists(Connection $connection, string $remotePath): bool
    {
        $parts = array_values(array_filter(explode('/', trim($remotePath, '/'))));
        if (count($parts) === 0) {
            return true;
        }

        $originalDir = @ftp_pwd($connection) ?: '/';
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= '/' . $part;

            if (@ftp_chdir($connection, $currentPath)) {
                continue;
            }

            if (@ftp_mkdir($connection, $currentPath) === false) {
                @ftp_chdir($connection, $originalDir);
                return false;
            }
        }

        @ftp_chdir($connection, $originalDir);
        return true;
    }

    private function updateUploadProgress(?string $progressToken, int $providerIndex, int $providerCount, float $ratio, string $message): void
    {
        if ($progressToken === null) {
            return;
        }

        [$startPercent, $endPercent] = $this->progressRangeForProvider($providerIndex, $providerCount);
        $clampedRatio = max(0.0, min(1.0, $ratio));
        $percent = (int) round($startPercent + (($endPercent - $startPercent) * $clampedRatio));

        $this->progressService->update($progressToken, $percent, $message, 'uploading');
    }

    /**
     * @return array{0:int,1:int}
     */
    private function progressRangeForProvider(int $providerIndex, int $providerCount): array
    {
        $overallStart = 45;
        $overallEnd = 90;
        $count = max(1, $providerCount);
        $slotSize = ($overallEnd - $overallStart) / $count;
        $startPercent = (int) round($overallStart + ($slotSize * max(0, $providerIndex)));
        $endPercent = $providerIndex >= ($count - 1)
            ? $overallEnd
            : (int) round($overallStart + ($slotSize * max(0, $providerIndex + 1)));

        return [$startPercent, max($startPercent, $endPercent)];
    }

    private function uploadingMessage(string $providerLabel, int $providerIndex, int $providerCount): string
    {
        if ($providerCount <= 1) {
            return 'Uploading backup to ' . $providerLabel;
        }

        return 'Uploading backup to ' . $providerLabel . ' (' . ($providerIndex + 1) . '/' . $providerCount . ')';
    }
}

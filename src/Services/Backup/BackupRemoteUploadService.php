<?php

namespace Arifur\BookstackBackup\Services\Backup;

use FTP\Connection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BackupRemoteUploadService
{
    public function __construct(
        protected BackupProgressService $progressService,
    ) {
    }

    /**
     * @param array{
     *   ftp: array{enabled: bool, host: string, port: int, username: string, password: string, path: string, passive: bool},
     *   google_drive: array{enabled: bool, access_token: string, folder_id: string}
     * } $config
     */
    public function uploadBackupToRemote(string $localFilePath, string $filename, string $provider, array $config, ?string $progressToken = null): bool
    {
        if ($provider === 'ftp') {
            return $this->uploadBackupToFtp($localFilePath, $filename, $config['ftp'], $progressToken);
        }

        if ($provider === 'google_drive') {
            return $this->uploadBackupToGoogleDrive($localFilePath, $filename, $config['google_drive'], $progressToken);
        }

        return false;
    }

    /**
     * @param array{enabled: bool, host: string, port: int, username: string, password: string, path: string, passive: bool} $config
     */
    private function uploadBackupToFtp(string $localFilePath, string $filename, array $config, ?string $progressToken = null): bool
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

                if ($progressToken !== null) {
                    $this->progressService->update($progressToken, 45, 'Uploading backup to FTP', 'uploading');
                }

                $status = @ftp_nb_fput($connection, $remoteFilePath, $stream, FTP_BINARY);
                while ($status === FTP_MOREDATA) {
                    $bytesTransferred = ftell($stream);
                    if ($progressToken !== null && $bytesTransferred !== false) {
                        $uploadPercent = $fileSize > 0 ? (int) floor(($bytesTransferred / $fileSize) * 50) : 50;
                        $this->progressService->update($progressToken, min(95, 45 + $uploadPercent), 'Uploading backup to FTP', 'uploading');
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

    /**
     * @param array{enabled: bool, access_token: string, folder_id: string} $config
     */
    private function uploadBackupToGoogleDrive(string $localFilePath, string $filename, array $config, ?string $progressToken = null): bool
    {
        if (!File::exists($localFilePath) || !$config['enabled']) {
            return false;
        }

        if ($config['access_token'] === '') {
            Log::warning('Google Drive upload skipped due to missing access token.');
            return false;
        }

        if ($progressToken !== null) {
            $this->progressService->update($progressToken, 55, 'Uploading backup to Google Drive', 'uploading');
        }

        $metadata = [
            'name' => $filename,
        ];

        if ($config['folder_id'] !== '') {
            $metadata['parents'] = [$config['folder_id']];
        }

        $fileContent = File::get($localFilePath);
        $mimeType = File::mimeType($localFilePath) ?: 'application/zip';
        $boundary = 'bookstack-backup-' . Str::random(24);
        $lineBreak = "\r\n";

        $body = '';
        $body .= '--' . $boundary . $lineBreak;
        $body .= 'Content-Type: application/json; charset=UTF-8' . $lineBreak . $lineBreak;
        $body .= json_encode($metadata, JSON_UNESCAPED_SLASHES) . $lineBreak;
        $body .= '--' . $boundary . $lineBreak;
        $body .= 'Content-Type: ' . $mimeType . $lineBreak . $lineBreak;
        $body .= $fileContent . $lineBreak;
        $body .= '--' . $boundary . '--';

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withToken($config['access_token'])
                ->withHeaders([
                    'Content-Type' => 'multipart/related; boundary=' . $boundary,
                ])
                ->withBody($body, 'multipart/related; boundary=' . $boundary)
                ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name');

            if (!$response->successful()) {
                Log::error('Google Drive upload failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if ($progressToken !== null) {
                    $this->progressService->fail($progressToken, 'Google Drive upload failed');
                }

                return false;
            }

            if ($progressToken !== null) {
                $this->progressService->update($progressToken, 90, 'Finishing Google Drive upload', 'uploading');
            }

            Log::info('Backup uploaded to Google Drive successfully.', [
                'response' => $response->json(),
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('Google Drive upload failed with exception.', [
                'message' => $exception->getMessage(),
            ]);

            if ($progressToken !== null) {
                $this->progressService->fail($progressToken, 'Google Drive upload failed');
            }

            return false;
        }
    }
}

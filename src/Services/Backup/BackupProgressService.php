<?php

namespace Arifur\BookstackBackup\Services\Backup;

use Illuminate\Support\Facades\Cache;

class BackupProgressService
{
    private const CACHE_PREFIX = 'bookstack-backup-progress:';
    private const TTL_SECONDS = 1800;

    public function start(string $token, string $provider): void
    {
        $this->store($token, [
            'status' => 'preparing',
            'percent' => 0,
            'message' => 'Preparing backup',
            'provider' => $provider,
            'complete' => false,
            'success' => false,
        ]);
    }

    public function update(string $token, int $percent, string $message, string $status = 'running'): void
    {
        $existing = $this->get($token);

        $this->store($token, array_merge($existing, [
            'status' => $status,
            'percent' => max(0, min(100, $percent)),
            'message' => $message,
            'complete' => false,
        ]));
    }

    public function complete(string $token, string $message = 'Backup completed'): void
    {
        $existing = $this->get($token);

        $this->store($token, array_merge($existing, [
            'status' => 'completed',
            'percent' => 100,
            'message' => $message,
            'complete' => true,
            'success' => true,
        ]));
    }

    public function fail(string $token, string $message = 'Backup failed'): void
    {
        $existing = $this->get($token);

        $this->store($token, array_merge($existing, [
            'status' => 'failed',
            'message' => $message,
            'complete' => true,
            'success' => false,
        ]));
    }

    /**
     * @return array{status:string,percent:int,message:string,provider:?string,complete:bool,success:bool,updated_at:string}
     */
    public function get(string $token): array
    {
        $value = Cache::get($this->cacheKey($token));

        if (!is_array($value)) {
            return [
                'status' => 'idle',
                'percent' => 0,
                'message' => 'Waiting to start',
                'provider' => null,
                'complete' => false,
                'success' => false,
                'updated_at' => now()->toIso8601String(),
            ];
        }

        return $value;
    }

    private function store(string $token, array $payload): void
    {
        $payload['updated_at'] = now()->toIso8601String();
        Cache::put($this->cacheKey($token), $payload, now()->addSeconds(self::TTL_SECONDS));
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX . $token;
    }
}
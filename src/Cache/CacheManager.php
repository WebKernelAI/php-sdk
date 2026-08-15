<?php

namespace WebKernelAI\SDK\Cache;

class CacheManager
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\');
        $this->ensureCacheDir();
    }

    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
        
        // Protect directory with htaccess if running on Apache
        $htaccess = $this->cacheDir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
    }

    public function get(string $key, $default = null)
    {
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return $default;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return $default;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['expires_at'])) {
            return $default;
        }

        if ($data['expires_at'] !== 0 && time() > $data['expires_at']) {
            $this->delete($key);
            return $default;
        }

        return $data['value'] ?? $default;
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        $this->ensureCacheDir();
        $filePath = $this->getFilePath($key);
        
        $payload = [
            'key'        => $key,
            'expires_at' => $ttl > 0 ? (time() + $ttl) : 0,
            'updated_at' => time(),
            'value'      => $value
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        // Atomic file write using temp file renaming
        $tmpFile = $filePath . '.' . uniqid('tmp_', true);
        if (@file_put_contents($tmpFile, $json, LOCK_EX) !== false) {
            return @rename($tmpFile, $filePath);
        }

        return false;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return true;
    }

    public function purge(): bool
    {
        if (!is_dir($this->cacheDir)) {
            return true;
        }

        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.json');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        return true;
    }

    private function getFilePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        return $this->cacheDir . DIRECTORY_SEPARATOR . 'wk_' . $safeKey . '.json';
    }
}

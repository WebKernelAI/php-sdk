<?php

namespace WebKernelAI\SDK;

class Config
{
    private string $siteId;
    private string $pairingSecret;
    private string $apiBaseUrl;
    private string $apiEndpoint;
    private string $cacheDir;
    private int $nonceTtl;
    private bool $enableWaf;
    private bool $enableHeaders;
    private bool $enableSeoEngine;
    private bool $enableIntegrity;
    private bool $enableUploadGuard;
    private bool $enableRateLimiter;
    private bool $enableTelemetry;
    private int $rateLimitMaxRequests;
    private int $rateLimitWindow;
    private string $integrityAction; // 'block', 'quarantine', 'alert_only'
    private string $rootDir;
    private array $allowedDirs;
    private int $timeout;
    private int $maxRetries;

    public function __construct(array $config = [])
    {
        $this->siteId        = $config['site_id'] ?? $config['site_token'] ?? (getenv('WEBKERNELAI_SITE_ID') ?: '');
        $this->pairingSecret = $config['pairing_secret'] ?? $config['api_key'] ?? (getenv('WEBKERNELAI_PAIRING_SECRET') ?: '');
        $this->apiBaseUrl    = rtrim($config['api_base_url'] ?? $config['api_url'] ?? (getenv('WEBKERNELAI_API_URL') ?: 'https://api.webkernelai.com'), '/');
        $this->apiEndpoint   = '/' . ltrim($config['api_endpoint'] ?? '/webkernelai-api', '/');
        
        $defaultCacheDir     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'webkernelai_cache';
        $this->cacheDir      = rtrim($config['cache_dir'] ?? (getenv('WEBKERNELAI_CACHE_DIR') ?: $defaultCacheDir), '/\\');
        
        $this->nonceTtl             = (int) ($config['nonce_ttl'] ?? 300);
        $this->enableWaf            = (bool) ($config['enable_waf'] ?? true);
        $this->enableHeaders        = (bool) ($config['enable_headers'] ?? true);
        $this->enableSeoEngine      = (bool) ($config['enable_seo_engine'] ?? true);
        $this->enableIntegrity      = (bool) ($config['enable_integrity'] ?? true);
        $this->enableUploadGuard    = (bool) ($config['enable_upload_guard'] ?? true);
        $this->enableRateLimiter    = (bool) ($config['enable_rate_limiter'] ?? true);
        $this->enableTelemetry      = (bool) ($config['enable_telemetry'] ?? true);
        $this->rateLimitMaxRequests  = (int) ($config['rate_limit_max_requests'] ?? 60);
        $this->rateLimitWindow      = (int) ($config['rate_limit_window'] ?? 60);
        $this->integrityAction      = (string) ($config['integrity_action'] ?? ($config['auto_purge'] ?? false ? 'quarantine' : 'block'));
        
        $defaultRoot = $_SERVER['DOCUMENT_ROOT'] ?? (defined('FCPATH') ? FCPATH : getcwd());
        $this->rootDir              = rtrim($config['root_dir'] ?? (getenv('WEBKERNELAI_ROOT_DIR') ?: $defaultRoot), '/\\');
        $this->allowedDirs          = (array) ($config['allowed_dirs'] ?? []);
        
        $this->timeout              = (int) ($config['timeout'] ?? 10);
        $this->maxRetries           = (int) ($config['max_retries'] ?? 3);
    }

    public function getSiteId(): string { return $this->siteId; }
    public function getPairingSecret(): string { return $this->pairingSecret; }
    public function getApiBaseUrl(): string { return $this->apiBaseUrl; }
    public function getApiUrl(): string { return $this->apiBaseUrl; }
    public function getApiEndpoint(): string { return $this->apiEndpoint; }
    public function getCacheDir(): string { return $this->cacheDir; }
    public function getNonceTtl(): int { return $this->nonceTtl; }
    public function isWafEnabled(): bool { return $this->enableWaf; }
    public function isHeadersEnabled(): bool { return $this->enableHeaders; }
    public function isSeoEngineEnabled(): bool { return $this->enableSeoEngine; }
    public function isIntegrityEnabled(): bool { return $this->enableIntegrity; }
    public function isUploadGuardEnabled(): bool { return $this->enableUploadGuard; }
    public function isRateLimiterEnabled(): bool { return $this->enableRateLimiter; }
    public function isTelemetryEnabled(): bool { return $this->enableTelemetry; }
    public function getRateLimitMaxRequests(): int { return $this->rateLimitMaxRequests; }
    public function getRateLimitWindow(): int { return $this->rateLimitWindow; }
    public function getIntegrityAction(): string { return $this->integrityAction; }
    public function getRootDir(): string { return $this->rootDir; }
    public function getAllowedDirs(): array { return $this->allowedDirs; }
    public function getTimeout(): int { return $this->timeout; }
    public function getMaxRetries(): int { return $this->maxRetries; }

    public function isValid(): bool
    {
        return !empty($this->siteId) && !empty($this->pairingSecret);
    }
}

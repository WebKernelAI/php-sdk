<?php

namespace WebKernelAI\SDK;

class ConfigBuilder
{
    private string $siteId = '';
    private string $pairingSecret = '';
    private string $apiBaseUrl = 'https://api.webkernelai.com';
    private string $apiEndpoint = '/webkernelai-api';
    private ?string $cacheDir = null;
    private int $nonceTtl = 300;
    private bool $enableWaf = true;
    private bool $enableHeaders = true;
    private bool $enableSeoEngine = true;
    private bool $enableIntegrity = true;
    private bool $enableUploadGuard = true;
    private bool $enableRateLimiter = true;
    private bool $enableTelemetry = true;
    private int $rateLimitMaxRequests = 60;
    private int $rateLimitWindow = 60;
    private bool $autoPurge = true;
    private ?string $rootDir = null;
    private array $allowedDirs = [];
    private int $timeout = 10;
    private int $maxRetries = 3;

    public static function make(): self
    {
        return new self();
    }

    public function siteId(string $siteId): self
    {
        $this->siteId = $siteId;
        return $this;
    }

    public function pairingSecret(string $pairingSecret): self
    {
        $this->pairingSecret = $pairingSecret;
        return $this;
    }

    public function apiBaseUrl(string $apiBaseUrl): self
    {
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        return $this;
    }

    public function apiEndpoint(string $endpoint): self
    {
        $this->apiEndpoint = '/' . ltrim($endpoint, '/');
        return $this;
    }

    public function cacheDir(string $cacheDir): self
    {
        $this->cacheDir = $cacheDir;
        return $this;
    }

    public function enableWaf(bool $enable = true): self
    {
        $this->enableWaf = $enable;
        return $this;
    }

    public function enableHeaders(bool $enable = true): self
    {
        $this->enableHeaders = $enable;
        return $this;
    }

    public function enableSeoEngine(bool $enable = true): self
    {
        $this->enableSeoEngine = $enable;
        return $this;
    }

    public function enableIntegrity(bool $enable = true): self
    {
        $this->enableIntegrity = $enable;
        return $this;
    }

    public function enableUploadGuard(bool $enable = true): self
    {
        $this->enableUploadGuard = $enable;
        return $this;
    }

    public function enableRateLimiter(bool $enable = true): self
    {
        $this->enableRateLimiter = $enable;
        return $this;
    }

    public function enableTelemetry(bool $enable = true): self
    {
        $this->enableTelemetry = $enable;
        return $this;
    }

    public function rateLimit(int $maxRequests, int $windowSeconds = 60): self
    {
        $this->rateLimitMaxRequests = $maxRequests;
        $this->rateLimitWindow      = $windowSeconds;
        return $this;
    }

    public function autoPurge(bool $enable = true): self
    {
        $this->autoPurge = $enable;
        return $this;
    }

    public function rootDir(string $rootDir): self
    {
        $this->rootDir = $rootDir;
        return $this;
    }

    public function allowedDirs(array $allowedDirs): self
    {
        $this->allowedDirs = $allowedDirs;
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    private array $excludedRoutes = ['/admin', '/backend', '/administrator', '/dashboard'];
    private array $whitelistedIps = [];

    public function excludedRoutes(array $routes): self
    {
        $this->excludedRoutes = $routes;
        return $this;
    }

    public function whitelistedIps(array $ips): self
    {
        $this->whitelistedIps = $ips;
        return $this;
    }

    public function maxRetries(int $retries): self
    {
        $this->maxRetries = $retries;
        return $this;
    }

    public function build(): Config
    {
        return new Config([
            'site_id'                  => $this->siteId,
            'pairing_secret'           => $this->pairingSecret,
            'api_base_url'             => $this->apiBaseUrl,
            'api_endpoint'             => $this->apiEndpoint,
            'cache_dir'                => $this->cacheDir,
            'nonce_ttl'                => $this->nonceTtl,
            'enable_waf'               => $this->enableWaf,
            'enable_headers'           => $this->enableHeaders,
            'enable_seo_engine'        => $this->enableSeoEngine,
            'enable_integrity'         => $this->enableIntegrity,
            'enable_upload_guard'      => $this->enableUploadGuard,
            'enable_rate_limiter'      => $this->enableRateLimiter,
            'enable_telemetry'         => $this->enableTelemetry,
            'rate_limit_max_requests'  => $this->rateLimitMaxRequests,
            'rate_limit_window'        => $this->rateLimitWindow,
            'auto_purge'               => $this->autoPurge,
            'root_dir'                 => $this->rootDir,
            'allowed_dirs'             => $this->allowedDirs,
            'excluded_routes'          => $this->excludedRoutes,
            'whitelisted_ips'          => $this->whitelistedIps,
            'timeout'                  => $this->timeout,
            'max_retries'              => $this->maxRetries,
        ]);
    }
}

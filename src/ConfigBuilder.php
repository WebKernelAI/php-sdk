<?php

namespace WebKernelAI\SDK;

use WebKernelAI\SDK\Exceptions\ConfigurationException;

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

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
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
            'site_id'        => $this->siteId,
            'pairing_secret' => $this->pairingSecret,
            'api_base_url'   => $this->apiBaseUrl,
            'api_endpoint'   => $this->apiEndpoint,
            'cache_dir'      => $this->cacheDir,
            'nonce_ttl'      => $this->nonceTtl,
            'enable_waf'     => $this->enableWaf,
            'enable_headers' => $this->enableHeaders,
            'enable_seo_engine' => $this->enableSeoEngine,
            'timeout'        => $this->timeout,
            'max_retries'    => $this->maxRetries,
        ]);
    }
}

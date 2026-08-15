<?php

namespace WebKernelAI\SDK\Services;

use WebKernelAI\SDK\Config;
use WebKernelAI\SDK\Support\Environment;
use WebKernelAI\SDK\Http\ApiClient;
use WebKernelAI\SDK\Http\Response;

class HealthService
{
    private Config $config;
    private ApiClient $api;

    public function __construct(Config $config, ApiClient $api)
    {
        $this->config = $config;
        $this->api    = $api;
    }

    public function check(): array
    {
        $telemetry = Environment::getTelemetry();
        
        $cacheStatus = is_writable($this->config->getCacheDir());

        return [
            'status'          => 'healthy',
            'site_id'         => $this->config->getSiteId(),
            'cache_writable'  => $cacheStatus,
            'waf_enabled'     => $this->config->isWafEnabled(),
            'headers_enabled' => $this->config->isHeadersEnabled(),
            'telemetry'       => $telemetry,
            'timestamp'       => time(),
        ];
    }

    public function sendHeartbeat(): Response
    {
        $health = $this->check();
        return $this->api->post('/v1/sites/' . $this->config->getSiteId() . '/heartbeat', $health);
    }
}

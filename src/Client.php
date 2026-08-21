<?php

namespace WebKernelAI\SDK;

use WebKernelAI\SDK\Cache\CacheManager;
use WebKernelAI\SDK\Events\EventDispatcher;
use WebKernelAI\SDK\Http\ApiClient;
use WebKernelAI\SDK\Services\SecurityService;
use WebKernelAI\SDK\Services\SeoService;
use WebKernelAI\SDK\Services\DashboardService;
use WebKernelAI\SDK\Services\HealthService;

class Client
{
    private Config $config;
    private CacheManager $cache;
    private EventDispatcher $events;
    private ApiClient $api;

    private SecurityService $securityService;
    private SeoService $seoService;
    private DashboardService $dashboardService;
    private HealthService $healthService;

    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config = new Config($config);
        } elseif ($config instanceof Config) {
            $this->config = $config;
        } else {
            $this->config = new Config();
        }

        $this->cache     = new CacheManager($this->config->getCacheDir());
        $this->events    = new EventDispatcher();
        $this->api       = new ApiClient($this->config);

        // Instantiate modular services
        $this->securityService  = new SecurityService($this->config, $this->cache, $this->events);
        $this->seoService       = new SeoService($this->cache, $this->events);
        $this->dashboardService = new DashboardService($this->config, $this->cache, $this->events, $this->api, $this->seoService);
        $this->healthService    = new HealthService($this->config, $this->api);
    }

    public function security(): SecurityService
    {
        return $this->securityService;
    }

    public function seo(): SeoService
    {
        return $this->seoService;
    }

    public function dashboard(): DashboardService
    {
        return $this->dashboardService;
    }

    public function health(): HealthService
    {
        return $this->healthService;
    }

    public function api(): ApiClient
    {
        return $this->api;
    }

    public function cache(): CacheManager
    {
        return $this->cache;
    }

    public function on(string $event, callable $listener): self
    {
        $this->events->listen($event, $listener);
        return $this;
    }

    /**
     * Boot all SDK modules in a single call.
     */
    public function boot(): void
    {
        $this->dashboardService->boot();
        $this->securityService->boot();
        $this->seoService->boot();
    }

    /**
     * Quick-boot helper for Core PHP and custom setups in 1 line of code.
     */
    public static function quickBoot(array $config = []): self
    {
        $client = new self($config);
        $client->boot();
        return $client;
    }
}

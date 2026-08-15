<?php

namespace WebKernelAI\SDK\Laravel;

use Illuminate\Support\ServiceProvider;
use WebKernelAI\SDK\Client;
use WebKernelAI\SDK\ConfigBuilder;

class WebKernelAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function ($app) {
            $config = ConfigBuilder::make()
                ->siteId(config('services.webkernelai.site_id', ''))
                ->pairingSecret(config('services.webkernelai.pairing_secret', ''))
                ->cacheDir(storage_path('framework/cache/webkernelai'))
                ->build();

            return new Client($config);
        });
    }

    public function boot(): void
    {
        // Auto-boot security headers and WAF when registered as Laravel middleware
    }
}

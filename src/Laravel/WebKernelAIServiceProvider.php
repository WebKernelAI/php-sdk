<?php

namespace WebKernelAI\SDK\Laravel;

if (!class_exists('Illuminate\Support\ServiceProvider')) {
    abstract class BaseServiceProvider
    {
        protected $app;
        public function __construct($app = null) { $this->app = $app; }
    }
} else {
    class_alias('Illuminate\Support\ServiceProvider', 'WebKernelAI\SDK\Laravel\BaseServiceProvider');
}

class WebKernelAIServiceProvider extends BaseServiceProvider
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

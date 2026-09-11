<?php

namespace WebKernelAI\SDK\Laravel;

use Closure;
use WebKernelAI\SDK\Client;

class WebKernelAISecurityMiddleware
{
    private Client $sdk;

    public function __construct()
    {
        $this->sdk = new Client([
            'site_id'         => config('services.webkernelai.site_id') ?: env('WEBKERNELAI_SITE_ID'),
            'pairing_secret'  => config('services.webkernelai.pairing_secret') ?: env('WEBKERNELAI_PAIRING_SECRET'),
            'excluded_routes' => config('services.webkernelai.excluded_routes') ?: (env('WEBKERNELAI_EXCLUDED_ROUTES') ? explode(',', env('WEBKERNELAI_EXCLUDED_ROUTES')) : ['/admin', '/backend', '/administrator', '/nova', '/filament', '/dashboard']),
            'whitelisted_ips' => config('services.webkernelai.whitelisted_ips') ?: (env('WEBKERNELAI_WHITELISTED_IPS') ? explode(',', env('WEBKERNELAI_WHITELISTED_IPS')) : []),
            'cache_dir'       => storage_path('framework/cache/webkernelai'),
        ]);
    }

    public function handle($request, Closure $next)
    {
        // Boot SDK (Handles Headers, WAF, and Remote Sync Endpoint)
        $this->sdk->boot();

        $response = $next($request);

        // Inject Meta Tags into HTML response if applicable
        if (method_exists($response, 'getContent') && method_exists($response, 'setContent')) {
            $content = $response->getContent();
            if (is_string($content) && strpos($content, '</head>') !== false) {
                $modifiedContent = $this->sdk->getSeoEngine()->injectMetaTags($content, []);
                $response->setContent($modifiedContent);
            }
        }

        return $response;
    }
}

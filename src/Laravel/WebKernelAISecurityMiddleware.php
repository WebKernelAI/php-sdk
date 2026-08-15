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
            'site_id'        => config('services.webkernelai.site_id'),
            'pairing_secret' => config('services.webkernelai.pairing_secret'),
            'cache_dir'      => storage_path('framework/cache/webkernelai'),
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

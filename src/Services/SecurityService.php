<?php

namespace WebKernelAI\SDK\Services;

use WebKernelAI\SDK\Config;
use WebKernelAI\SDK\Security\Waf;
use WebKernelAI\SDK\Security\Headers;
use WebKernelAI\SDK\Events\EventDispatcher;
use WebKernelAI\SDK\Exceptions\WafBlockException;

class SecurityService
{
    private Config $config;
    private EventDispatcher $events;
    private Waf $waf;

    public function __construct(Config $config, EventDispatcher $events)
    {
        $this->config = $config;
        $this->events = $events;
        $this->waf    = new Waf();
    }

    public function boot(): self
    {
        if ($this->config->isHeadersEnabled()) {
            Headers::inject();
        }

        if ($this->config->isWafEnabled()) {
            $wafResult = $this->waf->inspectRequest();
            if ($wafResult['blocked']) {
                $this->events->dispatch('ThreatBlocked', $wafResult);
                
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'error'   => 'Forbidden',
                    'message' => 'Request blocked by WebKernelAI WAF Firewall.',
                    'type'    => $wafResult['type']
                ]);
                exit(0);
            }
        }

        return $this;
    }
}

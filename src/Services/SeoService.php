<?php

namespace WebKernelAI\SDK\Services;

use WebKernelAI\SDK\Cache\CacheManager;
use WebKernelAI\SDK\Engine\SeoEngine;
use WebKernelAI\SDK\Events\EventDispatcher;

class SeoService
{
    private CacheManager $cache;
    private SeoEngine $engine;
    private EventDispatcher $events;

    public function __construct(CacheManager $cache, EventDispatcher $events)
    {
        $this->cache  = $cache;
        $this->events = $events;
        $this->engine = new SeoEngine($this->cache);
    }

    public function boot(): self
    {
        $this->engine->handleRequest();
        return $this;
    }

    public function updateRules(array $rules): bool
    {
        $success = $this->engine->updateRules($rules);
        if ($success) {
            $this->events->dispatch('RulesUpdated', ['rules' => $rules]);
        }
        return $success;
    }

    public function getEngine(): SeoEngine
    {
        return $this->engine;
    }
}

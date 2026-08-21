<?php

namespace WebKernelAI\SDK\Http;

use WebKernelAI\SDK\Config;
use WebKernelAI\SDK\Cache\CacheManager;

class TelemetryQueue
{
    private Config $config;
    private CacheManager $cache;
    private const QUEUE_CACHE_KEY = 'telemetry_queue';

    public function __construct(Config $config, CacheManager $cache)
    {
        $this->config = $config;
        $this->cache  = $cache;
    }

    /**
     * Enqueue a security event for telemetry.
     */
    public function record(string $eventType, array $data = []): void
    {
        if (!$this->config->isTelemetryEnabled()) {
            return;
        }

        $event = [
            'id'         => uniqid('evt_', true),
            'site_id'    => $this->config->getSiteId(),
            'event_type' => $eventType,
            'timestamp'  => time(),
            'client_ip'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'uri'        => $_SERVER['REQUEST_URI'] ?? '/',
            'data'       => $data,
        ];

        $queue = $this->cache->get(self::QUEUE_CACHE_KEY, []);
        $queue[] = $event;

        // Keep queue capped to 100 items max to prevent excessive file size
        if (count($queue) > 100) {
            $queue = array_slice($queue, -100);
        }

        $this->cache->set(self::QUEUE_CACHE_KEY, $queue, 86400);
    }

    /**
     * Asynchronously flush queued events to the central API without delaying the user's page response.
     */
    public function flushAsync(): bool
    {
        if (!$this->config->isTelemetryEnabled() || !$this->config->isValid()) {
            return false;
        }

        $queue = $this->cache->get(self::QUEUE_CACHE_KEY, []);
        if (empty($queue)) {
            return true;
        }

        // Clear queue locally before sending to prevent re-sending
        $this->cache->delete(self::QUEUE_CACHE_KEY);

        // If fastcgi is available, close HTTP response first so user doesn't wait
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // Send non-blocking background curl request
        $url = $this->config->getApiBaseUrl() . $this->config->getApiEndpoint();
        $payload = json_encode([
            'action' => 'telemetry_batch',
            'events' => $queue
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-WebKernel-Site-ID: ' . $this->config->getSiteId(),
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 2000); // Max 2s timeout
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        @curl_exec($ch);
        @curl_close($ch);

        return true;
    }

    public function getQueue(): array
    {
        return $this->cache->get(self::QUEUE_CACHE_KEY, []);
    }
}

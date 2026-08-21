<?php

namespace WebKernelAI\SDK\Security;

use WebKernelAI\SDK\Cache\CacheManager;

class RateLimiter
{
    private CacheManager $cache;
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(CacheManager $cache, int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->cache         = $cache;
        $this->maxRequests   = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Check if client IP exceeded rate limit. Returns rate limit evaluation result.
     */
    public function inspect(): array
    {
        $ip = $this->getClientIp();
        $cacheKey = 'ratelimit_' . md5($ip);

        $now = time();
        $record = $this->cache->get($cacheKey, [
            'count'      => 0,
            'reset_time' => $now + $this->windowSeconds,
            'banned_until' => 0,
        ]);

        // Check if currently banned
        if (isset($record['banned_until']) && $record['banned_until'] > $now) {
            return [
                'blocked'      => true,
                'ip'           => $ip,
                'retry_after'  => $record['banned_until'] - $now,
                'reason'       => 'ip_temporarily_banned',
            ];
        }

        // Reset window if expired
        if ($now > $record['reset_time']) {
            $record = [
                'count'        => 1,
                'reset_time'   => $now + $this->windowSeconds,
                'banned_until' => 0,
            ];
            $this->cache->set($cacheKey, $record, $this->windowSeconds * 2);
            return ['blocked' => false, 'remaining' => $this->maxRequests - 1];
        }

        $record['count']++;

        if ($record['count'] > $this->maxRequests) {
            // Ban IP for 5 minutes (300s) on severe violation
            $banDuration = 300;
            $record['banned_until'] = $now + $banDuration;
            $this->cache->set($cacheKey, $record, $banDuration + 60);

            return [
                'blocked'      => true,
                'ip'           => $ip,
                'retry_after'  => $banDuration,
                'reason'       => 'rate_limit_exceeded',
            ];
        }

        $this->cache->set($cacheKey, $record, $this->windowSeconds * 2);

        return [
            'blocked'   => false,
            'remaining' => max(0, $this->maxRequests - $record['count'])
        ];
    }

    public function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Standard proxy
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ipList = explode(',', $_SERVER[$header]);
                $candidate = trim($ipList[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

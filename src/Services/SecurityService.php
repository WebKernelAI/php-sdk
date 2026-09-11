<?php

namespace WebKernelAI\SDK\Services;

use WebKernelAI\SDK\Config;
use WebKernelAI\SDK\Cache\CacheManager;
use WebKernelAI\SDK\Security\Waf;
use WebKernelAI\SDK\Security\Headers;
use WebKernelAI\SDK\Security\UploadGuard;
use WebKernelAI\SDK\Security\IntegrityEngine;
use WebKernelAI\SDK\Security\MalwareScanner;
use WebKernelAI\SDK\Security\RateLimiter;
use WebKernelAI\SDK\Http\TelemetryQueue;
use WebKernelAI\SDK\Events\EventDispatcher;

class SecurityService
{
    private Config $config;
    private CacheManager $cache;
    private EventDispatcher $events;
    private Waf $waf;
    private RateLimiter $rateLimiter;
    private TelemetryQueue $telemetry;

    public function __construct(Config $config, CacheManager $cache, EventDispatcher $events)
    {
        $this->config      = $config;
        $this->cache       = $cache;
        $this->events      = $events;
        $this->rateLimiter = new RateLimiter($this->cache, $config->getRateLimitMaxRequests(), $config->getRateLimitWindow());
        $this->telemetry   = new TelemetryQueue($config, $this->cache);

        // Load dynamic rules & banned IPs from local cache
        $dynamicRules   = $this->cache->get('dynamic_waf_rules', []);
        $bannedIps      = $this->cache->get('banned_ips', []);
        $excludedRoutes = $config->getExcludedRoutes();
        $whitelistedIps = $config->getWhitelistedIps();
        $this->waf      = new Waf($dynamicRules, $bannedIps, $excludedRoutes, $whitelistedIps);

        // Register async shutdown flush for telemetry
        register_shutdown_function([$this->telemetry, 'flushAsync']);
    }

    public function boot(): self
    {
        // 1. Enforce HTTP Security Headers
        if ($this->config->isHeadersEnabled()) {
            Headers::inject();
        }

        // 2. IP Rate Limiting & Anti-Brute-Force Shield
        if ($this->config->isRateLimiterEnabled()) {
            $rateCheck = $this->rateLimiter->inspect();
            if ($rateCheck['blocked']) {
                $this->events->dispatch('RateLimitExceeded', $rateCheck);
                $this->telemetry->record('RATE_LIMIT_BLOCKED', $rateCheck);

                http_response_code(429);
                header('Retry-After: ' . ($rateCheck['retry_after'] ?? 60));
                header('Content-Type: application/json');
                echo json_encode([
                    'error'       => 'Too Many Requests',
                    'message'     => 'Rate limit exceeded. Access temporarily throttled.',
                    'retry_after' => $rateCheck['retry_after'] ?? 60
                ]);
                exit(0);
            }
        }

        // 3. Scan $_FILES Upload Guard
        if ($this->config->isUploadGuardEnabled()) {
            $uploadThreat = UploadGuard::inspect();
            if ($uploadThreat !== null) {
                $this->events->dispatch('UploadBlocked', $uploadThreat);
                $this->telemetry->record('UPLOAD_THREAT_BLOCKED', $uploadThreat);

                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'error'   => 'Forbidden',
                    'message' => 'Upload blocked by WebKernelAI Upload Guard.',
                    'reason'  => $uploadThreat['reason'] ?? 'dangerous_payload'
                ]);
                exit(0);
            }
        }

        // 4. Inspect WAF Rules (Builtin + Dynamic Cloud Signatures)
        if ($this->config->isWafEnabled()) {
            $wafResult = $this->waf->inspectRequest();
            if ($wafResult['blocked']) {
                $this->events->dispatch('ThreatBlocked', $wafResult);
                $this->telemetry->record('WAF_THREAT_BLOCKED', $wafResult);

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

        // 5. Deep File Malware & Suspicious Directory Scanner (Non-destructive Alerting)
        if ($this->config->isIntegrityEnabled()) {
            $rootDir = $this->config->getRootDir();
            if (!empty($rootDir) && is_dir($rootDir)) {
                $integrityResult = IntegrityEngine::inspect(
                    $rootDir,
                    $this->config->getAllowedDirs()
                );

                if (!empty($integrityResult['flagged'])) {
                    $this->events->dispatch('MalwareDetected', $integrityResult);
                    $this->telemetry->record('MALWARE_SUSPICIOUS_FILE_ALERT', [
                        'summary' => 'Suspicious files or directories detected in root inspection',
                        'flagged' => $integrityResult['flagged'],
                    ]);
                }

                IntegrityEngine::protectCoreFiles($rootDir);
            }
        }

        return $this;
    }

    public function runIntegrityCheck(): array
    {
        return IntegrityEngine::inspect(
            $this->config->getRootDir(),
            $this->config->getAllowedDirs()
        );
    }

    public function scanFile(string $filePath): ?array
    {
        return MalwareScanner::scanFile($filePath);
    }

    public function scanDirectory(string $dirPath, int $maxDepth = 3): array
    {
        return MalwareScanner::scanDirectory($dirPath, $maxDepth);
    }

    public function telemetry(): TelemetryQueue
    {
        return $this->telemetry;
    }

    public function rateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }

    public function waf(): Waf
    {
        return $this->waf;
    }
}

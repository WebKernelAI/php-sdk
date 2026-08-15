<?php

namespace WebKernelAI\SDK\Security;

use WebKernelAI\SDK\Config;
use WebKernelAI\SDK\Cache\CacheManager;

class Authenticator
{
    public const TOKEN_HEADER     = 'HTTP_X_WEBKERNELAI_SECURITY_TOKEN';
    public const TIMESTAMP_HEADER = 'HTTP_X_WEBKERNELAI_TIMESTAMP';
    public const NONCE_HEADER     = 'HTTP_X_WEBKERNELAI_NONCE';
    public const SIGNATURE_HEADER = 'HTTP_X_WEBKERNELAI_SIGNATURE';

    private Config $config;
    private CacheManager $cache;

    public function __construct(Config $config, CacheManager $cache)
    {
        $this->config = $config;
        $this->cache = $cache;
    }

    /**
     * Authenticate an incoming remote request from WebKernelAI Cloud.
     */
    public function authenticateRequest(array $serverHeaders, string $rawBody): array
    {
        if (!$this->config->isValid()) {
            return ['success' => false, 'code' => 500, 'message' => 'SDK credentials unconfigured.'];
        }

        // Normalize all header keys to lowercase for robust web server compatibility (Apache, Nginx, LiteSpeed, FPM)
        $normalizedHeaders = [];
        foreach ($serverHeaders as $k => $v) {
            $key = strtolower(str_replace('_', '-', (string) $k));
            if (strpos($key, 'http-') === 0) {
                $key = substr($key, 5);
            }
            $normalizedHeaders[$key] = is_array($v) ? implode(',', $v) : (string) $v;
        }

        $timestamp = $normalizedHeaders['x-webkernelai-timestamp'] ?? '';
        $nonce     = $normalizedHeaders['x-webkernelai-nonce'] ?? '';
        $signature = $normalizedHeaders['x-webkernelai-signature'] ?? '';

        if (empty($timestamp) || empty($nonce) || empty($signature)) {
            return ['success' => false, 'code' => 401, 'message' => 'Missing cryptographic headers.'];
        }

        // 1. Validate timestamp freshness (Replay attack prevention)
        $currentTime = time();
        $requestTime = (int) $timestamp;
        if (abs($currentTime - $requestTime) > $this->config->getNonceTtl()) {
            return ['success' => false, 'code' => 403, 'message' => 'Request timestamp expired.'];
        }

        // 2. Validate single-use nonce
        $nonceKey = 'nonce_' . md5($nonce);
        if ($this->cache->has($nonceKey)) {
            return ['success' => false, 'code' => 403, 'message' => 'Nonce replay detected.'];
        }

        // 3. Verify HMAC signature (Primary: timestamp.nonce.sha256(rawBody))
        $isValidSig = Signer::verifySignature(
            $rawBody,
            $timestamp,
            $nonce,
            $signature,
            $this->config->getPairingSecret()
        );

        // Fallback: If primary failed, verify multiline canonical HMAC signature format
        if (!$isValidSig) {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $route  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $bodyHash = hash('sha256', $rawBody);
            $canonical = strtoupper($method) . "\n" . $route . "\n" . $timestamp . "\n" . $nonce . "\n" . $bodyHash;
            $fallbackSig = hash_hmac('sha256', $canonical, $this->config->getPairingSecret());
            if (hash_equals($fallbackSig, $signature)) {
                $isValidSig = true;
            }
        }

        if (!$isValidSig) {
            return ['success' => false, 'code' => 403, 'message' => 'Invalid request signature.'];
        }

        // Save nonce to prevent replay within TTL window
        $this->cache->set($nonceKey, true, $this->config->getNonceTtl());

        return ['success' => true, 'code' => 200, 'message' => 'Authenticated successfully.'];
    }
}

<?php

namespace WebKernelAI\SDK\Http;

use WebKernelAI\SDK\Config;
use WebKernelAI\SDK\Security\Signer;
use WebKernelAI\SDK\Exceptions\ApiException;

class ApiClient
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function post(string $path, array $payload = []): Response
    {
        return $this->request('POST', $path, $payload);
    }

    public function get(string $path, array $queryParams = []): Response
    {
        $urlPath = !empty($queryParams) ? $path . '?' . http_build_query($queryParams) : $path;
        return $this->request('GET', $urlPath);
    }

    private function request(string $method, string $path, array $payload = []): Response
    {
        $url       = $this->config->getApiBaseUrl() . '/' . ltrim($path, '/');
        $rawBody   = !empty($payload) ? json_encode($payload) : '';
        $timestamp = (string) time();
        $nonce     = Signer::generateNonce();
        $signature = Signer::generateSignature($rawBody, $timestamp, $nonce, $this->config->getPairingSecret());

        $headers = [
            'Content-Type: application/json',
            'X-WebKernelAI-Site-ID: ' . $this->config->getSiteId(),
            'X-WebKernelAI-Timestamp: ' . $timestamp,
            'X-WebKernelAI-Nonce: ' . $nonce,
            'X-WebKernelAI-Signature: ' . $signature,
            'User-Agent: WebKernelAI-PHP-SDK/1.1.0 (' . PHP_OS_FAMILY . ')',
        ];

        $attempt     = 0;
        $maxRetries  = $this->config->getMaxRetries();
        $statusCode = 0;
        $responseBody = '';

        while ($attempt < $maxRetries) {
            $attempt++;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getTimeout());

            if ($method === 'POST' && !empty($rawBody)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
            }

            $responseBody = curl_exec($ch);
            $statusCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError    = curl_error($ch);
            curl_close($ch);

            // Success (2xx) or Client Error (4xx - no retry)
            if ($statusCode >= 200 && $statusCode < 500) {
                break;
            }

            // Exponential backoff wait (e.g. 200ms, 400ms, 800ms)
            if ($attempt < $maxRetries) {
                usleep((int) (pow(2, $attempt) * 100000));
            }
        }

        if ($statusCode === 0) {
            throw new ApiException("API connection failed to {$url}. Error: " . ($curlError ?? 'Unknown error'));
        }

        $decodedData = json_decode($responseBody, true) ?? [];
        return new Response($statusCode, $decodedData, $responseBody);
    }
}

<?php

namespace WebKernelAI\SDK\Security;

class Signer
{
    /**
     * Generate HMAC-SHA256 request signature.
     */
    public static function generateSignature(string $payload, string $timestamp, string $nonce, string $secret): string
    {
        $dataToSign = implode('.', [
            $timestamp,
            $nonce,
            hash('sha256', $payload)
        ]);

        return hash_hmac('sha256', $dataToSign, $secret);
    }

    /**
     * Verify HMAC-SHA256 request signature and enforce timestamp freshness (replay attack prevention).
     */
    public static function verifySignature(string $payload, string $timestamp, string $nonce, string $signature, string $secret, int $maxAge = 300): bool
    {
        if ($maxAge > 0 && abs(time() - (int) $timestamp) > $maxAge) {
            return false;
        }

        $expectedSignature = self::generateSignature($payload, $timestamp, $nonce, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate a cryptographically secure random nonce string.
     */
    public static function generateNonce(int $length = 32): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }
}

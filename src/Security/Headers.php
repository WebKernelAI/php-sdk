<?php

namespace WebKernelAI\SDK\Security;

class Headers
{
    /**
     * Send essential HTTP Security Headers.
     */
    public static function inject(array $customHeaders = []): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        foreach ($customHeaders as $header => $value) {
            header("{$header}: {$value}");
        }
    }

    /**
     * Instance wrapper to allow `$headers->apply()`.
     */
    public function apply(array $customHeaders = []): void
    {
        self::inject($customHeaders);
    }
}

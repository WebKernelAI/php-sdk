<?php

namespace WebKernelAI\SDK\Support;

class Environment
{
    public static function getTelemetry(): array
    {
        return [
            'sdk_version' => '1.1.0',
            'php_version' => PHP_VERSION,
            'os'          => PHP_OS_FAMILY,
            'server'      => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'framework'   => self::detectFramework(),
            'extensions'  => [
                'curl'    => extension_loaded('curl'),
                'openssl' => extension_loaded('openssl'),
                'json'    => extension_loaded('json'),
                'apcu'    => extension_loaded('apcu'),
            ],
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    public static function detectFramework(): string
    {
        if (class_exists('Illuminate\Foundation\Application')) {
            return 'Laravel';
        }
        if (class_exists('Symfony\Component\HttpKernel\Kernel')) {
            return 'Symfony';
        }
        if (defined('BASEPATH')) {
            return 'CodeIgniter';
        }
        if (defined('ABSPATH')) {
            return 'WordPress';
        }
        return 'Raw PHP';
    }
}

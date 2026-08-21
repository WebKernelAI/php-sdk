<?php

namespace WebKernelAI\SDK\Security;

class Waf
{
    private static array $builtinRules = [
        'sqli'      => '/(union\s+select|select\s+.*\s+from|insert\s+into|delete\s+from|drop\s+table|update\s+.*\s+set|alter\s+table|exec\s*\(|benchmark\s*\(|sleep\s*\()/i',
        'xss'       => '/(<script[\s>]|javascript:|onload\s*=|onerror\s*=|document\.cookie|document\.location|<iframe|<object|<embed)/i',
        'traversal' => '/(\.\.\/|\.\.\\\|proc\/self\/environ|etc\/passwd|boot\.ini|win\.ini)/i',
        'rce'       => '/(;\s*(cat|ls|whoami|nc|bash|sh|curl|wget|chmod|python|perl|php)(\s+|$|;)|eval\s*\(|system\s*\(|passthru\s*\(|shell_exec\s*\(|exec\s*\()/i',
        'wp_probe'  => '/(\bxmlrpc\.php\b|\bwp-login\.php\b|\bsetup-config\.php\b|\bwp-admin\/install\.php\b|\bwp-content\/plugins\/)/i',
        'wrappers'  => '/(php:\/\/input|php:\/\/filter|phar:\/\/|data:\/\/|expect:\/\/)/i',
    ];

    private array $dynamicRules = [];
    private array $bannedIps = [];

    public function __construct(array $dynamicRules = [], array $bannedIps = [])
    {
        $this->dynamicRules = $dynamicRules;
        $this->bannedIps    = $bannedIps;
    }

    public function setDynamicRules(array $rules): self
    {
        $this->dynamicRules = $rules;
        return $this;
    }

    public function setBannedIps(array $ips): self
    {
        $this->bannedIps = $ips;
        return $this;
    }

    /**
     * Inspect superglobal arrays, request URI, and IP for threats.
     */
    public function inspectRequest(): array
    {
        // 1. Check IP Blacklist
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($clientIp) && in_array($clientIp, $this->bannedIps, true)) {
            return [
                'blocked' => true,
                'type'    => 'banned_ip',
                'source'  => 'IP',
                'key'     => $clientIp
            ];
        }

        // 2. Check Request Payloads
        $payloads = [
            'GET'    => $_GET ?? [],
            'POST'   => $_POST ?? [],
            'COOKIE' => $_COOKIE ?? [],
            'URI'    => $_SERVER['REQUEST_URI'] ?? '',
        ];

        foreach ($payloads as $source => $data) {
            $threat = $this->scanData($data, $source);
            if ($threat !== null) {
                return [
                    'blocked' => true,
                    'type'    => $threat['type'],
                    'source'  => $source,
                    'key'     => $threat['key'],
                ];
            }
        }

        return ['blocked' => false];
    }

    private function scanData($data, string $source, string $parentKey = ''): ?array
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $fullKey = $parentKey ? "{$parentKey}.{$key}" : $key;
                $match = $this->scanData($value, $source, $fullKey);
                if ($match !== null) {
                    return $match;
                }
            }
            return null;
        }

        if (is_string($data)) {
            // Builtin rules
            foreach (self::$builtinRules as $type => $pattern) {
                if (preg_match($pattern, $data)) {
                    return ['type' => $type, 'key' => $parentKey ?: $source];
                }
            }

            // Dynamic cloud-synced rules
            foreach ($this->dynamicRules as $ruleName => $pattern) {
                if (@preg_match($pattern, $data)) {
                    return ['type' => 'dynamic_' . $ruleName, 'key' => $parentKey ?: $source];
                }
            }
        }

        return null;
    }
}

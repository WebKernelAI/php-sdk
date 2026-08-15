<?php

namespace WebKernelAI\SDK\Security;

class Waf
{
    private static array $rules = [
        'sqli'      => '/(union\s+select|select\s+.*\s+from|insert\s+into|delete\s+from|drop\s+table|update\s+.*\s+set|alter\s+table|exec\s*\(|benchmark\s*\(|sleep\s*\()/i',
        'xss'       => '/(<script[\s>]|javascript:|onload\s*=|onerror\s*=|document\.cookie|document\.location|<iframe|<object|<embed)/i',
        'traversal' => '/(\.\.\/|\.\.\\\|proc\/self\/environ|etc\/passwd|boot\.ini|win\.ini)/i',
        'rce'       => '/(;\s*(cat|ls|whoami|nc|bash|sh|curl|wget|chmod|python|perl|php)\s+|eval\s*\(|system\s*\(|passthru\s*\(|shell_exec\s*\(|exec\s*\()/i',
    ];

    /**
     * Inspect superglobal arrays for threats.
     */
    public function inspectRequest(): array
    {
        $payloads = [
            'GET'    => $_GET ?? [],
            'POST'   => $_POST ?? [],
            'COOKIE' => $_COOKIE ?? [],
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
            foreach (self::$rules as $type => $pattern) {
                if (preg_match($pattern, $data)) {
                    return ['type' => $type, 'key' => $parentKey];
                }
            }
        }

        return null;
    }
}

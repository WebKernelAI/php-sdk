<?php

namespace WebKernelAI\SDK\Security;

class UploadGuard
{
    private static array $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar',
        'inc', 'sh', 'bash', 'pl', 'py', 'cgi', 'htaccess', 'asp', 'aspx', 'jsp'
    ];

    /**
     * Inspect superglobal $_FILES for dangerous uploads, double extensions, and embedded code.
     */
    public static function inspect(): ?array
    {
        if (empty($_FILES)) {
            return null;
        }

        foreach ($_FILES as $field => $fileData) {
            $threat = self::scanEntry($fileData, (string) $field);
            if ($threat !== null) {
                return $threat;
            }
        }

        return null;
    }

    private static function scanEntry($fileData, string $field): ?array
    {
        if (!is_array($fileData) || !isset($fileData['name'])) {
            return null;
        }

        if (is_array($fileData['name'])) {
            foreach ($fileData['name'] as $idx => $name) {
                $tmpName = $fileData['tmp_name'][$idx] ?? '';
                $threat = self::validateSingle((string) $name, (string) $tmpName, "{$field}[{$idx}]");
                if ($threat !== null) {
                    return $threat;
                }
            }
            return null;
        }

        return self::validateSingle((string) $fileData['name'], (string) ($fileData['tmp_name'] ?? ''), $field);
    }

    private static function validateSingle(string $filename, string $tmpPath, string $field): ?array
    {
        if (empty($filename)) {
            return null;
        }

        // 1. Remove null-bytes and URL-encoded tricks
        $cleanName = str_replace(["\0", "%00", "\x00"], '', $filename);
        $cleanName = rawurldecode($cleanName);

        // 2. Multi-extension & dangerous extension inspection
        $parts = explode('.', strtolower($cleanName));
        if (count($parts) > 1) {
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if (in_array($trimmed, self::$dangerousExtensions, true)) {
                    return [
                        'blocked'  => true,
                        'reason'   => 'dangerous_extension',
                        'filename' => $filename,
                        'field'    => $field,
                        'match'    => $trimmed
                    ];
                }
            }
        }

        // Deep inspection of file content magic bytes & PHP tags
        if (!empty($tmpPath) && file_exists($tmpPath) && is_readable($tmpPath)) {
            $sample = @file_get_contents($tmpPath, false, null, 0, 2048);
            if ($sample && preg_match('/(<\?php|<\?=|<script\s+language=["\']php["\']|\beval\s*\(|\bsystem\s*\(|\bpassthru\s*\()/i', $sample)) {
                @unlink($tmpPath); // Remove infected temporary file immediately
                return [
                    'blocked'  => true,
                    'reason'   => 'embedded_php_payload',
                    'filename' => $filename,
                    'field'    => $field,
                    'match'    => 'executable_payload'
                ];
            }
        }

        return null;
    }
}

<?php

namespace WebKernelAI\SDK\Security;

class IntegrityEngine
{
    /**
     * Standard framework and CMS directory whitelist.
     */
    private static array $defaultAllowedDirs = [
        'application',
        'system',
        'app',
        'bootstrap',
        'config',
        'database',
        'public',
        'resources',
        'routes',
        'storage',
        'vendor',
        'assets',
        'uploads',
        'css',
        'js',
        'images',
        'img',
        'includes',
        'admin',
        '.well-known',
        'wp-admin',
        'wp-content',
        'wp-includes',
        'backend',
        'cgi-bin',
        'webkernelai-php-sdk'
    ];

    /**
     * Scan root directory for unrecognized folders and deeply scan files inside for malware or suspicious cloaking patterns.
     * Zero destructive or blocking actions are performed — all findings are classified and returned for alerting.
     *
     * @param string $rootDir Web root directory
     * @param array $allowedDirs Custom whitelist
     * @return array Returns structured report of flagged suspicious/malicious directories and files
     */
    public static function inspect(string $rootDir, array $allowedDirs = []): array
    {
        if (empty($rootDir) || !is_dir($rootDir)) {
            return [
                'success' => false,
                'flagged' => [],
                'error'   => 'Root directory does not exist or cannot be accessed.'
            ];
        }

        $whitelist = array_unique(array_map('strtolower', array_merge(self::$defaultAllowedDirs, $allowedDirs)));
        $flagged = [];

        $items = @scandir($rootDir);
        if (!$items) {
            return [
                'success' => false,
                'flagged' => [],
                'error'   => 'Unable to read root directory.'
            ];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || strpos($item, '.') === 0) {
                continue;
            }

            $fullPath = $rootDir . DIRECTORY_SEPARATOR . $item;

            // 1. If it's a directory
            if (is_dir($fullPath)) {
                $dirLower = strtolower($item);

                // Static asset folders (css, js, images, uploads) must NEVER contain executable PHP scripts
                $staticAssetDirs = ['css', 'js', 'images', 'img', 'assets', 'uploads'];
                if (in_array($dirLower, $staticAssetDirs, true)) {
                    $assetThreats = MalwareScanner::scanDirectory($fullPath, 2, 50);
                    // Also flag any PHP files in static asset directories regardless of content
                    $phpFilesInAsset = @glob($fullPath . '/*.php') ?: [];
                    if (!empty($phpFilesInAsset) || !empty($assetThreats)) {
                        $flagged[] = [
                            'type'            => 'ROGUE_EXECUTABLE_IN_ASSET_DIR',
                            'directory'       => $item,
                            'path'            => $fullPath,
                            'threat_count'    => count($assetThreats) + count($phpFilesInAsset),
                            'threats_details' => array_merge($assetThreats, array_map(function($f) {
                                return [
                                    'status'      => 'SUSPICIOUS',
                                    'severity'    => 'CRITICAL',
                                    'signature'   => 'php_in_static_asset_directory',
                                    'description' => 'Executable PHP script found inside static asset directory (' . basename($f) . ')',
                                    'file_path'   => $f,
                                ];
                            }, $phpFilesInAsset)),
                            'status'          => 'MALICIOUS',
                        ];
                    }
                    continue;
                }

                // If other folder is in whitelist, skip
                if (in_array($dirLower, $whitelist, true)) {
                    continue;
                }

                // If it's another legitimate full website (Addon domain / WordPress / Laravel), skip
                if (self::isLegitimateWebsite($fullPath)) {
                    continue;
                }

                // Deeply scan files inside this unrecognized directory for malware/cloaking
                $dirThreats = MalwareScanner::scanDirectory($fullPath, 2, 50);

                if (!empty($dirThreats)) {
                    $flagged[] = [
                        'type'            => 'SUSPICIOUS_DIRECTORY_MALWARE',
                        'directory'       => $item,
                        'path'            => $fullPath,
                        'threat_count'    => count($dirThreats),
                        'threats_details' => $dirThreats,
                        'status'          => 'SUSPICIOUS',
                    ];
                } else {
                    // Check if it matches rogue SEO directory patterns (e.g. only index.php and sitemap.xml)
                    $fileList = array_diff(@scandir($fullPath) ?: [], ['.', '..']);
                    if (count($fileList) <= 3 && (in_array('index.php', $fileList) || in_array('sitemap.xml', $fileList))) {
                        $flagged[] = [
                            'type'            => 'SUSPICIOUS_SEO_SPAM_DIRECTORY',
                            'directory'       => $item,
                            'path'            => $fullPath,
                            'threat_count'    => 1,
                            'threats_details' => [
                                [
                                    'status'      => 'SUSPICIOUS',
                                    'severity'    => 'WARNING',
                                    'signature'   => 'unrecognized_root_spam_directory',
                                    'description' => 'Unrecognized root directory containing only entry files',
                                    'file_path'   => $fullPath,
                                ]
                            ],
                            'status'          => 'SUSPICIOUS',
                        ];
                    }
                }
            } elseif (is_file($fullPath)) {
                // 2. If it's a standalone file in root, scan it directly
                $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                if (in_array($ext, ['php', 'phtml', 'html', 'js', 'xml', 'ico', 'txt', ''])) {
                    $fileThreat = MalwareScanner::scanFile($fullPath);
                    if ($fileThreat !== null) {
                        $flagged[] = [
                            'type'            => 'SUSPICIOUS_ROOT_FILE',
                            'file'            => $item,
                            'path'            => $fullPath,
                            'threat_count'    => 1,
                            'threats_details' => [$fileThreat],
                            'status'          => $fileThreat['status'],
                        ];
                    }
                }
            }
        }

        return [
            'success' => true,
            'flagged' => $flagged,
        ];
    }

    /**
     * Backward-compatible alias for inspect().
     */
    public static function enforce(string $rootDir, array $allowedDirs = [], string $action = 'alert_only'): array
    {
        return self::inspect($rootDir, $allowedDirs);
    }

    /**
     * Safety Check: Identifies if a folder is another full website (Addon domain, WordPress, Laravel, CI, etc.)
     */
    private static function isLegitimateWebsite(string $dir): bool
    {
        $siteIndicators = [
            'wp-config.php',
            'artisan',
            'composer.json',
            'application',
            'system',
            'wp-content',
            'wp-includes',
            'app',
            '.git'
        ];

        foreach ($siteIndicators as $indicator) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure critical files (.htaccess, index.php) are protected with read-only permissions.
     */
    public static function protectCoreFiles(string $rootDir): array
    {
        $protected = [];
        $filesToCheck = ['.htaccess', 'index.php'];

        foreach ($filesToCheck as $file) {
            $filePath = $rootDir . DIRECTORY_SEPARATOR . $file;
            if (file_exists($filePath)) {
                $currentPerms = fileperms($filePath) & 0777;
                if ($currentPerms !== 0444) {
                    if (@chmod($filePath, 0444)) {
                        $protected[] = $file;
                    }
                }
            }
        }

        return $protected;
    }
}

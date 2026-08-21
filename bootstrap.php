<?php
/**
 * WebKernelAI Standalone Auto-Prepend Security Bootstrap
 *
 * Can be loaded via php.ini, .user.ini, or .htaccess:
 *   auto_prepend_file = "/path/to/webkernelai-php-sdk/bootstrap.php"
 */

require_once __DIR__ . '/autoload.php';

use WebKernelAI\SDK\Client;

try {
    Client::quickBoot([
        'root_dir'     => $_SERVER['DOCUMENT_ROOT'] ?? getcwd(),
        'auto_purge'   => true,
    ]);
} catch (\Throwable $e) {
    // Fail silently in production to avoid crashing host scripts if misconfigured
    error_log('[WebKernelAI Bootstrap Error]: ' . $e->getMessage());
}

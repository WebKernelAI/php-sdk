<?php
/**
 * Standalone Autoloader for WebKernelAI PHP SDK.
 * Used when composer autoloader is not present in non-composer PHP applications.
 */

spl_autoload_register(function ($class) {
    $prefix = 'WebKernelAI\\SDK\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

<?php
/**
 * Basic Usage Example for Core / Custom PHP Application
 */

require_once __DIR__ . '/../autoload.php';

use WebKernelAI\SDK\Client;

// 1. Initialize SDK with credentials
$sdk = new Client([
    'site_id'        => 'site_uuid_998877665544332211',
    'pairing_secret' => 'hmac_pairing_secret_abcdef1234567890',
    'cache_dir'      => __DIR__ . '/cache',
    'enable_waf'     => true,
    'enable_headers' => true,
]);

// 2. Mock sample SEO metadata rules (Simulating cached synced rules from WebKernelAI dashboard)
$sdk->getSeoEngine()->updateRules([
    'redirects' => [
        '/old-about' => ['target' => '/about-us', 'code' => 301],
    ],
    'meta' => [
        '/about-us' => [
            'title'       => 'About Us - Ultra Secure WebKernelAI Powered Site',
            'description' => 'This page is dynamically managed and secured by WebKernelAI PHP SDK.'
        ]
    ]
]);

// 3. Boot SDK (WAF + Security Headers + SEO Meta Injector)
$sdk->boot();

// 4. Sample Application Output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Default Site Title</title>
</head>
<body>
    <h1>Welcome to My Custom PHP Website</h1>
    <p>WebKernelAI PHP SDK is running in zero-database mode!</p>
</body>
</html>

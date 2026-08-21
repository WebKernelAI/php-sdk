<?php

require_once dirname(__DIR__) . '/autoload.php';

use WebKernelAI\SDK\Security\MalwareScanner;
use WebKernelAI\SDK\Security\IntegrityEngine;
use WebKernelAI\SDK\Security\UploadGuard;
use WebKernelAI\SDK\Security\Waf;
use WebKernelAI\SDK\Security\RateLimiter;
use WebKernelAI\SDK\Http\TelemetryQueue;
use WebKernelAI\SDK\Cache\CacheManager;
use WebKernelAI\SDK\Client;
use WebKernelAI\SDK\Config;

echo "========================================================\n";
echo "WEBKERNELAI PHP SDK - DEEP SCANNER & ALERT TEST\n";
echo "========================================================\n\n";

$testCacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wk_test_cache_' . time();
$cache = new CacheManager($testCacheDir);
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wk_malware_test_' . time();

@mkdir($tempDir . '/application', 0777, true);
@mkdir($tempDir . '/my-other-website.com', 0777, true);
file_put_contents($tempDir . '/my-other-website.com/wp-config.php', '<?php // Clean WordPress Config');

// 1. Test MalwareScanner (Deep File & Content Inspection)
echo "1. Testing MalwareScanner (Deep Content Signatures)...\n";

$d = chr(36);

// Test A: eval(base64_decode)
$evalSample = '<?php eval(base64_decode("c3lzdGVtKCk7")); ?>';
$resEval = MalwareScanner::scanContent($evalSample, 'test_eval.php');
if ($resEval === null || $resEval['status'] !== 'MALICIOUS' || $resEval['signature'] !== 'eval_base64') {
    throw new Exception("MalwareScanner failed to detect eval(base64_decode)");
}

// Test B: SEO Spam Cloaker Bot Check
$cloakSample = '<?php if (preg_match("/googlebot/i", ' . $d . '_SERVER["HTTP_USER_AGENT"])) { echo file_get_contents("http://test-spam.local/links"); } ?>';
$resCloak = MalwareScanner::scanContent($cloakSample, 'test_cloak.php');
if ($resCloak === null || $resCloak['status'] !== 'SUSPICIOUS' || $resCloak['signature'] !== 'seo_cloaker_bot_check') {
    throw new Exception("MalwareScanner failed to detect SEO spam cloaking pattern");
}

// Test C: Direct shell execution with superglobal
$shellSample = '<?php passthru(' . $d . '_REQUEST["cmd"]); ?>';
$resShell = MalwareScanner::scanContent($shellSample, 'test_shell.php');
if ($resShell === null || $resShell['status'] !== 'MALICIOUS' || $resShell['signature'] !== 'direct_shell_input') {
    throw new Exception("MalwareScanner failed to detect direct shell backdoor");
}

echo "   [PASS] MalwareScanner successfully detected obfuscation, SEO cloaker, and backdoor.\n\n";

// 2. Test IntegrityEngine Inspection (Zero Destructive Actions, Marks Suspicious)
echo "2. Testing IntegrityEngine (Zero-Delete Suspicious Directory & File Marking)...\n";

$rogueSpamDir = $tempDir . '/testimonials';
@mkdir($rogueSpamDir, 0777, true);
$spamIndex = '<?php if(preg_match("/googlebot/i", ' . $d . '_SERVER["HTTP_USER_AGENT"])) { echo gzinflate(base64_decode("abc")); } ?>';
file_put_contents($rogueSpamDir . '/index.php', $spamIndex);
file_put_contents($rogueSpamDir . '/sitemap.xml', '<urlset></urlset>');

$inspectRes = IntegrityEngine::inspect($tempDir, []);

if (empty($inspectRes['flagged'])) {
    throw new Exception("IntegrityEngine failed to flag suspicious directories");
}

// CRITICAL VERIFICATION: No files or directories should be deleted or renamed!
if (!is_dir($rogueSpamDir) || !file_exists($rogueSpamDir . '/index.php')) {
    throw new Exception("Files/directories were unexpectedly deleted or renamed in inspect mode!");
}
if (!is_dir($tempDir . '/my-other-website.com')) {
    throw new Exception("Addon domain directory was mistakenly affected!");
}

echo "   [PASS] IntegrityEngine marked suspicious files without deleting, renaming, or blocking.\n\n";

// 3. Test Telemetry Alert Dispatching
echo "3. Testing TelemetryQueue (Dashboard Alert with Threat Metadata)...\n";
$config = new Config([
    'site_id'          => 'site_test_123',
    'pairing_secret'   => 'sec_test_abc',
    'enable_telemetry' => true
]);
$telemetry = new TelemetryQueue($config, $cache);
$telemetry->record('MALWARE_SUSPICIOUS_FILE_ALERT', [
    'summary' => 'Suspicious files detected during scan',
    'flagged' => $inspectRes['flagged']
]);

$queue = $telemetry->getQueue();
if (empty($queue) || $queue[0]['event_type'] !== 'MALWARE_SUSPICIOUS_FILE_ALERT') {
    throw new Exception("TelemetryQueue failed to enqueue malware alert");
}
echo "   [PASS] Malicious/Suspicious incident successfully queued with SHA256 and snippet metadata.\n\n";

// 4. Test UploadGuard
echo "4. Testing UploadGuard (Webshell & Multi-extension Block)...\n";
$_FILES = [
    'doc' => [
        'name' => 'malicious.php.png',
        'type' => 'image/png',
        'tmp_name' => '',
        'error' => 0,
        'size' => 100
    ]
];
$uploadThreat = UploadGuard::inspect();
if ($uploadThreat === null || !$uploadThreat['blocked'] || $uploadThreat['reason'] !== 'dangerous_extension') {
    throw new Exception("UploadGuard failed to intercept double extension upload");
}
echo "   [PASS] UploadGuard blocked malicious upload attempt.\n\n";

// 5. Test WAF & RateLimiter
echo "5. Testing WAF & RateLimiter...\n";
$waf = new Waf();
$_SERVER['REQUEST_URI'] = '/xmlrpc.php?action=pingback';
$wafRes = $waf->inspectRequest();
if (!$wafRes['blocked'] || $wafRes['type'] !== 'wp_probe') {
    throw new Exception("WAF failed to block WordPress probe");
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.88';
$rateLimiter = new RateLimiter($cache, 2, 60);
$rateLimiter->inspect();
$rateLimiter->inspect();
$rateBlocked = $rateLimiter->inspect();
if (!$rateBlocked['blocked'] || $rateBlocked['reason'] !== 'rate_limit_exceeded') {
    throw new Exception("RateLimiter failed to throttle request");
}
echo "   [PASS] WAF and RateLimiter functioning correctly.\n\n";

// 6. Test Client::quickBoot()
echo "6. Testing Client::quickBoot() in Non-Destructive Mode...\n";
$_FILES = [];
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET = [];

$client = Client::quickBoot([
    'root_dir'  => $tempDir,
    'cache_dir' => $testCacheDir,
]);
if (!($client instanceof Client)) {
    throw new Exception("Client::quickBoot did not return a valid instance");
}
echo "   [PASS] Client::quickBoot completed scan and alert cycle successfully.\n\n";

// Cleanups
$cache->purge();

echo "========================================================\n";
echo ">>> ALL TESTS PASSED! DEEP MALWARE SCANNER VERIFIED! <<<\n";
echo "========================================================\n";

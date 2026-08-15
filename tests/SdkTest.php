<?php

namespace WebKernelAI\SDK\Tests;

use PHPUnit\Framework\TestCase;
use WebKernelAI\SDK\Security\Signer;
use WebKernelAI\SDK\Security\Waf;
use WebKernelAI\SDK\Cache\CacheManager;

class SdkTest extends TestCase
{
    public function testHmacSignatureGenerationAndVerification()
    {
        $secret    = 'test_secret_1234567890';
        $payload   = json_encode(['action' => 'ping', 'timestamp' => 12345678]);
        $timestamp = (string) time();
        $nonce     = Signer::generateNonce();

        $signature = Signer::generateSignature($payload, $timestamp, $nonce, $secret);

        $this->assertNotEmpty($signature);
        $this->assertTrue(Signer::verifySignature($payload, $timestamp, $nonce, $signature, $secret));
        $this->assertFalse(Signer::verifySignature($payload, $timestamp, $nonce, 'invalid_sig', $secret));
    }

    public function testWafInspection()
    {
        $waf = new Waf();
        
        $_GET['test'] = "SELECT * FROM users WHERE 1=1 UNION SELECT 1,2,3";
        $result = $waf->inspectRequest();
        
        $this->assertTrue($result['blocked']);
        $this->assertEquals('sqli', $result['type']);

        unset($_GET['test']);
    }

    public function testCacheManager()
    {
        $cacheDir = sys_get_temp_dir() . '/test_wk_cache_' . time();
        $cache = new CacheManager($cacheDir);

        $cache->set('test_key', ['foo' => 'bar'], 10);
        $this->assertTrue($cache->has('test_key'));
        $this->assertEquals(['foo' => 'bar'], $cache->get('test_key'));

        $cache->purge();
        $this->assertFalse($cache->has('test_key'));
    }
}

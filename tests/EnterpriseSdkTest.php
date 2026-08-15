<?php

namespace WebKernelAI\SDK\Tests;

use PHPUnit\Framework\TestCase;
use WebKernelAI\SDK\Client;
use WebKernelAI\SDK\ConfigBuilder;
use WebKernelAI\SDK\Security\Signer;
use WebKernelAI\SDK\Exceptions\ApiException;

class EnterpriseSdkTest extends TestCase
{
    public function testFluentConfigBuilder()
    {
        $config = ConfigBuilder::make()
            ->siteId('site_test_123')
            ->pairingSecret('secret_test_456')
            ->apiEndpoint('/custom-endpoint')
            ->enableWaf(true)
            ->build();

        $this->assertEquals('site_test_123', $config->getSiteId());
        $this->assertEquals('secret_test_456', $config->getPairingSecret());
        $this->assertEquals('/custom-endpoint', $config->getApiEndpoint());
    }

    public function testModularServicesAndEvents()
    {
        $config = ConfigBuilder::make()
            ->siteId('site_test_123')
            ->pairingSecret('secret_test_456')
            ->build();

        $client = new Client($config);
        $eventFired = false;

        $client->on('RulesUpdated', function ($payload) use (&$eventFired) {
            $eventFired = true;
        });

        $client->seo()->updateRules([
            'redirects' => ['/old' => ['target' => '/new', 'code' => 301]]
        ]);

        $this->assertTrue($eventFired);
        $this->assertIsArray($client->health()->check());
    }
}

<?php

namespace WebKernelAI\SDK\CodeIgniter;

use WebKernelAI\SDK\Client;
use WebKernelAI\SDK\ConfigBuilder;

class WebKernelAISecurityFilter
{
    private static ?Client $client = null;

    private static function getClient(): Client
    {
        if (self::$client === null) {
            $siteId        = getenv('WEBKERNELAI_SITE_ID') ?: config_item('webkernelai_site_id');
            $pairingSecret = getenv('WEBKERNELAI_PAIRING_SECRET') ?: config_item('webkernelai_pairing_secret');

            $config = ConfigBuilder::make()
                ->siteId($siteId ?: '')
                ->pairingSecret($pairingSecret ?: '')
                ->cacheDir(defined('WRITEPATH') ? WRITEPATH . 'cache/webkernelai' : sys_get_temp_dir() . '/webkernelai_cache')
                ->build();

            self::$client = new Client($config);
        }

        return self::$client;
    }

    /**
     * CodeIgniter 4 Filter: before() method
     */
    public function before($request = null, $arguments = null)
    {
        self::getClient()->boot();
    }

    /**
     * CodeIgniter 4 Filter: after() method
     */
    public function after($request = null, $response = null, $arguments = null)
    {
        return $response;
    }

    /**
     * CodeIgniter 3 Hook: pre_system / post_controller_constructor
     */
    public static function hook()
    {
        self::getClient()->boot();
    }
}

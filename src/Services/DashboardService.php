<?php

namespace WebKernelAI\SDK\Services;

use WebKernelAI\SDK\Config;
use WebKernelAI\SDK\Cache\CacheManager;
use WebKernelAI\SDK\Security\Authenticator;
use WebKernelAI\SDK\Events\EventDispatcher;
use WebKernelAI\SDK\Http\ApiClient;
use WebKernelAI\SDK\Engine\SeoEngine;

class DashboardService
{
    private Config $config;
    private CacheManager $cache;
    private Authenticator $auth;
    private EventDispatcher $events;
    private ApiClient $api;
    private SeoService $seoService;

    public function __construct(Config $config, CacheManager $cache, EventDispatcher $events, ApiClient $api, SeoService $seoService)
    {
        $this->config     = $config;
        $this->cache      = $cache;
        $this->auth       = new Authenticator($this->config, $this->cache);
        $this->events     = $events;
        $this->api        = $api;
        $this->seoService = $seoService;
    }

    public function boot(): self
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, $this->config->getApiEndpoint()) === false) {
            return $this;
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $headers = function_exists('getallheaders') ? array_merge($_SERVER, getallheaders()) : $_SERVER;

        $authResult = $this->auth->authenticateRequest($headers, $rawBody);
        if (!$authResult['success']) {
            http_response_code($authResult['code']);
            header('Content-Type: application/json');
            echo json_encode($authResult);
            exit(0);
        }

        $data   = json_decode($rawBody, true) ?? [];
        $action = $data['action'] ?? '';
        
        if (empty($action)) {
            $pathOnly  = parse_url($requestUri, PHP_URL_PATH) ?: $requestUri;
            $pathParts = array_values(array_filter(explode('/', rtrim($pathOnly, '/'))));
            $action    = end($pathParts) ?: '';
        }

        switch ($action) {
            case 'ping':
            case 'capabilities':
            case 'info':
                $response = [
                    'status'       => 'ok',
                    'message'      => 'WebKernelAI PHP SDK Active',
                    'version'      => '1.1.0',
                    'site_id'      => $this->config->getSiteId(),
                    'capabilities' => [
                        'ping', 'info', 'files', 'meta-sync', 'control-config',
                        'taxonomy-scan', 'taxonomy-controls', 'seo-objects',
                        'security-hardening', 'security-hardening-reset-defaults',
                        'security-headers-apply', 'advanced-security',
                        'advanced-security-production-lock', 'advanced-security-history',
                        'advanced-security-rollback', 'text-controls', 'redirects', 'schemas'
                    ],
                    'timestamp'    => time()
                ];
                break;

            case 'control-config':
                $rules = $this->cache->get(SeoEngine::CACHE_KEY, []);
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($data['config'])) {
                    $rules['config'] = array_merge($rules['config'] ?? [], $data['config']);
                    $this->seoService->updateRules($rules);
                    $response = [
                        'status'  => 'success',
                        'success' => true,
                        'message' => 'Control configuration updated successfully.',
                        'config'  => $rules['config']
                    ];
                } else {
                    $storedConfig = $rules['config'] ?? [];
                    $response = [
                        'status' => 'success',
                        'config' => array_merge([
                            'robots_enabled'          => false,
                            'robots_content'          => "User-agent: *\nAllow: /\n",
                            'llms_enabled'            => false,
                            'llms_content'            => '',
                            'sitemap_enabled'         => true,
                            'xmlrpc_disabled'         => true,
                            'rest_api_hardening'      => true,
                            'file_editing_disabled'   => true,
                            'pingback_disabled'       => true,
                            'author_scan_disabled'    => true,
                            'directory_listing_block' => true,
                        ], $storedConfig)
                    ];
                }
                break;

            case 'text-controls':
                $response = [
                    'status' => 'success',
                    'controls' => [
                        'disable_comments' => false,
                        'strip_html_tags'  => false,
                    ]
                ];
                break;

            case 'redirects':
                $rules = $this->cache->get(SeoEngine::CACHE_KEY, []);
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                    $incomingRedirects = $data['redirects'] ?? [];
                    $redirectRules = [];
                    foreach ($incomingRedirects as $r) {
                        $source = $r['source_path'] ?? $r['source'] ?? '';
                        $target = $r['target_url'] ?? $r['target'] ?? '';
                        $code   = (int) ($r['status_code'] ?? $r['code'] ?? 301);
                        if (!empty($source) && !empty($target)) {
                            $cleanSource = '/' . ltrim($source, '/');
                            $redirectRules[$cleanSource] = [
                                'source_path' => $cleanSource,
                                'target_url'  => $target,
                                'target'      => $target,
                                'status_code' => $code,
                                'code'        => $code,
                                'is_regex'    => !empty($r['is_regex']),
                            ];
                        }
                    }
                    $rules['redirects'] = $redirectRules;
                    $this->seoService->updateRules($rules);
                    $response = [
                        'status'    => 'success',
                        'success'   => true,
                        'message'   => 'Redirect rules updated successfully.',
                        'redirects' => array_values($redirectRules)
                    ];
                } else {
                    $storedRedirects = $rules['redirects'] ?? [];
                    $response = [
                        'status'    => 'success',
                        'success'   => true,
                        'redirects' => array_values($storedRedirects)
                    ];
                }
                break;

            case 'schemas':
                $rules = $this->cache->get(SeoEngine::CACHE_KEY, []);
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                    $incomingSchemas = $data['schemas'] ?? $data['config'] ?? $data;
                    $rules['schemas'] = $incomingSchemas;
                    $this->seoService->updateRules($rules);
                    $response = [
                        'status'  => 'success',
                        'success' => true,
                        'message' => 'Schema configurations updated successfully.',
                        'schemas' => $rules['schemas']
                    ];
                } else {
                    $storedSchemas = $rules['schemas'] ?? [];
                    $response = [
                        'status'  => 'success',
                        'success' => true,
                        'schemas' => $storedSchemas
                    ];
                }
                break;

            case 'seo-objects':
            case 'seo-meta':
                $rules = $this->cache->get(SeoEngine::CACHE_KEY, []);
                $cachedMeta = $rules['meta'] ?? [];
                
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $host     = $_SERVER['HTTP_HOST'] ?? parse_url($this->config->getApiBaseUrl(), PHP_URL_HOST) ?? 'localhost';
                $siteUrl  = rtrim($protocol . $host, '/');
                
                $discoveredUrls = [];
                $sitemapContent = '';
                $sitemapUrls    = [];

                // --- METHOD 1: Read sitemap.xml first (Primary source of truth) ---
                $possibleSitemapPaths = [
                    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/sitemap.xml',
                    __DIR__ . '/../../../../sitemap.xml',
                    __DIR__ . '/../../../sitemap.xml',
                    dirname($_SERVER['SCRIPT_FILENAME'] ?? '') . '/sitemap.xml',
                ];

                foreach ($possibleSitemapPaths as $sitemapPath) {
                    if (!empty($sitemapPath) && file_exists($sitemapPath)) {
                        $sitemapContent = @file_get_contents($sitemapPath);
                        if ($sitemapContent) break;
                    }
                }

                // HTTP fallback if sitemap wasn't read from disk
                if (empty($sitemapContent)) {
                    $sitemapUrl = $siteUrl . '/sitemap.xml';
                    if (function_exists('curl_init')) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $sitemapUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                        curl_setopt($ch, CURLOPT_USERAGENT, 'WebKernelAI-SDK/1.1.0');
                        $sitemapContent = curl_exec($ch) ?: '';
                        curl_close($ch);
                    }
                    if (empty($sitemapContent)) {
                        $opts = ['http' => ['method' => 'GET', 'header' => "User-Agent: WebKernelAI-SDK/1.1.0\r\n"]];
                        $sitemapContent = @file_get_contents($sitemapUrl, false, stream_context_create($opts)) ?: '';
                    }
                }

                if (!empty($sitemapContent)) {
                    preg_match_all('/<loc>\s*(.*?)\s*<\/loc>/is', $sitemapContent, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $discoveredLoc) {
                            $sitemapUrls[] = trim($discoveredLoc);
                        }
                    }
                }

                // --- METHOD 2: CodeIgniter 3 routes.php (Only if sitemap.xml is missing) ---
                $ciRouteUrls = [];
                if (empty($sitemapUrls)) {
                    $possibleRouteFiles = [
                        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/application/config/routes.php',
                        __DIR__ . '/../../../../application/config/routes.php',
                        __DIR__ . '/../../../application/config/routes.php',
                    ];
                    foreach ($possibleRouteFiles as $routeFile) {
                        if (file_exists($routeFile)) {
                            $routeContent = @file_get_contents($routeFile);
                            if ($routeContent) {
                                preg_match_all('/\$route\[[\'\"]([^\'\"]+)[\'\"]\]/i', $routeContent, $routeMatches);
                                if (!empty($routeMatches[1])) {
                                    foreach ($routeMatches[1] as $ciRoute) {
                                        if ($ciRoute === '404_override' || strpos($ciRoute, 'translate_uri_dashes') !== false) {
                                            continue;
                                        }
                                        if ($ciRoute === 'default_controller') {
                                            $ciRouteUrls[] = $siteUrl . '/';
                                        } else {
                                            // Skip parameterized route templates
                                            if (strpos($ciRoute, '(') !== false || strpos($ciRoute, '$') !== false) {
                                                continue;
                                            }
                                            $ciRouteUrls[] = $siteUrl . '/' . ltrim($ciRoute, '/');
                                        }
                                    }
                                }
                            }
                            break;
                        }
                    }
                }

                // Combine discovered URLs: sitemap > CI routes > cached rules
                $discoveredUrls = !empty($sitemapUrls) ? $sitemapUrls : $ciRouteUrls;

                // Include any saved custom rules
                if (is_array($cachedMeta)) {
                    foreach (array_keys($cachedMeta) as $metaUrl) {
                        $discoveredUrls[] = $metaUrl;
                    }
                }

                // Clean and filter discovered URLs (remove placeholders, parameterized CI routes, and invalid URLs)
                $validUrls = [];
                foreach ($discoveredUrls as $rawUrl) {
                    $u = trim($rawUrl);
                    if (empty($u) || strpos($u, '(:') !== false || strpos($u, '$') !== false) {
                        continue; // Skip CodeIgniter regex route placeholders
                    }
                    if (!preg_match('/^https?:\/\//i', $u)) {
                        $u = $siteUrl . '/' . ltrim($u, '/');
                    }
                    // Validate URL format
                    if (filter_var($u, FILTER_VALIDATE_URL)) {
                        $validUrls[] = $u;
                    }
                }

                $discoveredUrls = array_unique($validUrls);

                // Fallback to home page if nothing found
                if (empty($discoveredUrls)) {
                    $discoveredUrls = [$siteUrl . '/'];
                }

                // Build full item list
                $metaList = [];
                foreach ($discoveredUrls as $url) {
                    $path = parse_url($url, PHP_URL_PATH) ?: '/';
                    $existing = $cachedMeta[$url] ?? [];
                    $displayTitle = !empty($existing['title']) 
                        ? $existing['title'] 
                        : ($path === '/' ? 'Home Page' : ucwords(str_replace(['-', '_', '/', '.html', '.php'], ' ', trim($path, '/'))));

                    $metaList[] = [
                        'id'               => abs(crc32($url)),
                        'type'             => 'page',
                        'post_type'        => 'custom_php',
                        'title'            => $displayTitle,
                        'url'              => $url,
                        'meta_title'       => !empty($existing['title']) ? $existing['title'] : $displayTitle,
                        'meta_description' => $existing['description'] ?? '',
                        'canonical'        => $existing['canonical'] ?? $url,
                        'og_title'         => $existing['og_title'] ?? '',
                        'og_description'   => $existing['og_description'] ?? '',
                        'in_sitemap'       => true
                    ];
                }

                $response = [
                    'status'  => 'success',
                    'success' => true,
                    'items'   => $metaList,
                    'total'   => count($metaList)
                ];
                break;

            case 'meta-sync':
                $items = $data['items'] ?? [];
                $rules = $this->cache->get(SeoEngine::CACHE_KEY, []);
                if (!isset($rules['meta']) || !is_array($rules['meta'])) {
                    $rules['meta'] = [];
                }

                $updatedCount = 0;
                foreach ($items as $item) {
                    $targetUrl = !empty($item['url']) ? $item['url'] : (!empty($item['canonical']) ? $item['canonical'] : '');
                    if (!empty($targetUrl)) {
                        $existingRule = $rules['meta'][$targetUrl] ?? [];
                        $incomingTitle = $item['title'] ?? $item['meta_title'] ?? null;
                        $incomingDesc  = $item['description'] ?? $item['meta_description'] ?? null;

                        $rules['meta'][$targetUrl] = [
                            'title'       => $incomingTitle !== null && $incomingTitle !== '' ? $incomingTitle : ($existingRule['title'] ?? ''),
                            'description' => $incomingDesc !== null && $incomingDesc !== '' ? $incomingDesc : ($existingRule['description'] ?? ''),
                            'canonical'   => !empty($item['canonical']) ? $item['canonical'] : ($existingRule['canonical'] ?? $targetUrl),
                            'in_sitemap'  => isset($item['in_sitemap']) ? (bool) $item['in_sitemap'] : ($existingRule['in_sitemap'] ?? true),
                        ];
                        $updatedCount++;
                    }
                }

                $this->seoService->updateRules($rules);
                $response = [
                    'status'  => 'success',
                    'success' => true,
                    'message' => "Successfully updated {$updatedCount} SEO meta rules.",
                    'synced'  => $updatedCount
                ];
                break;

            case 'sync_rules':
                $rules = $data['rules'] ?? [];
                $this->seoService->updateRules($rules);
                $response = ['status' => 'success', 'message' => 'SEO rules synced successfully.'];
                break;

            case 'purge_cache':
                $this->cache->purge();
                $this->events->dispatch('CachePurged');
                $response = ['status' => 'success', 'message' => 'Cache purged successfully.'];
                break;

            default:
                $response = ['status' => 'error', 'message' => 'Unknown action requested.'];
                http_response_code(400);
                break;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit(0);
    }

    public function pullLatestRules(): array
    {
        $response = $this->api->get('/v1/sites/' . $this->config->getSiteId() . '/rules');
        if ($response->isSuccess()) {
            $rules = $response->get('rules', []);
            $this->seoService->updateRules($rules);
            return $rules;
        }
        return [];
    }
}

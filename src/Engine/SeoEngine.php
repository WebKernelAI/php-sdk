<?php

namespace WebKernelAI\SDK\Engine;

use WebKernelAI\SDK\Cache\CacheManager;

class SeoEngine
{
    private CacheManager $cache;
    public const CACHE_KEY = 'seo_rules';

    public function __construct(CacheManager $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Store synced SEO rules into local cache.
     */
    public function updateRules(array $rules): bool
    {
        return $this->cache->set(self::CACHE_KEY, $rules, 0); // 0 = permanent until purged
    }

    /**
     * Execute SEO rules: handle redirects or inject Meta Tags into output buffer HTML.
     */
    public function handleRequest(): void
    {
        $rules = $this->cache->get(self::CACHE_KEY, []);
        if (empty($rules) || !is_array($rules)) {
            return;
        }

        $currentUri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $protocol    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host        = $_SERVER['HTTP_HOST'] ?? '';
        $fullUrl     = rtrim($protocol . $host . $currentUri, '/');
        $fullUrlSlash = $fullUrl . '/';

        // 0A. Handle Dynamic /robots.txt Requests
        if ($currentUri === '/robots.txt') {
            $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
            // If physical robots.txt file exists on disk, let web server serve it natively
            if (!empty($documentRoot) && file_exists(rtrim($documentRoot, '/\\') . '/robots.txt')) {
                return;
            }

            $config = $rules['config'] ?? [];
            $siteBase = rtrim($protocol . $host, '/');

            header('Content-Type: text/plain; charset=utf-8');
            if (!empty($config['robots_enabled']) && !empty($config['robots_content'])) {
                echo trim($config['robots_content']) . "\n";
            } else {
                echo "User-agent: *\n";
                echo "Allow: /\n\n";
                echo "Sitemap: {$siteBase}/sitemap.xml\n";
            }
            echo "# Generated dynamically by WebKernelAI SDK\n";
            exit(0);
        }

        // 0B. Handle Dynamic /sitemap.xml Requests
        if ($currentUri === '/sitemap.xml') {
            $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
            // If physical sitemap.xml file exists on disk, let web server serve it natively
            if (!empty($documentRoot) && file_exists(rtrim($documentRoot, '/\\') . '/sitemap.xml')) {
                return;
            }

            $config = $rules['config'] ?? [];
            if (isset($config['sitemap_enabled']) && $config['sitemap_enabled'] === false) {
                http_response_code(404);
                header('Content-Type: text/plain; charset=utf-8');
                echo "Sitemap disabled by site administrator.\n";
                exit(0);
            }
            // Generate XML sitemap dynamically from cached meta rules respecting in_sitemap flag
            $xmlUrls = [];
            if (!empty($rules['meta']) && is_array($rules['meta'])) {
                foreach ($rules['meta'] as $u => $metaItem) {
                    $inSitemap = is_array($metaItem) ? ($metaItem['in_sitemap'] ?? true) : true;
                    if ($inSitemap !== false && $inSitemap !== 0 && $inSitemap !== '0' && $inSitemap !== 'false') {
                        $xmlUrls[] = $u;
                    }
                }
            }
            if (empty($xmlUrls)) {
                $xmlUrls[] = rtrim($protocol . $host, '/') . '/';
            }

            header('Content-Type: application/xml; charset=utf-8');
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach (array_unique($xmlUrls) as $loc) {
                echo '  <url>' . "\n";
                echo '    <loc>' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
                echo '  </url>' . "\n";
            }
            echo '</urlset>' . "\n";
            echo '<!-- Generated dynamically by WebKernelAI SDK -->' . "\n";
            exit(0);
        }

        // 1. Handle 301 / 302 / 307 / 308 Redirect Rules
        if (!empty($rules['redirects']) && is_array($rules['redirects'])) {
            foreach ($rules['redirects'] as $src => $rule) {
                $target = $rule['target_url'] ?? $rule['target'] ?? '';
                $code   = (int) ($rule['status_code'] ?? $rule['code'] ?? 301);
                $isMatch = false;

                if (!empty($rule['is_regex'])) {
                    if (@preg_match('~' . $src . '~i', $currentUri)) {
                        $target = @preg_replace('~' . $src . '~i', $target, $currentUri);
                        $isMatch = true;
                    }
                } else {
                    $cleanSrc = '/' . ltrim($src, '/');
                    if ($cleanSrc === $currentUri || $src === $fullUrl || $src === $fullUrlSlash) {
                        $isMatch = true;
                    }
                }

                if ($isMatch && !empty($target) && !headers_sent()) {
                    header("Location: {$target}", true, $code);
                    exit(0);
                }
            }
        }

        // 2. Lookup Meta & Schema Rules matching URI, full URL, or trailing-slash full URL
        $metaData = null;
        if (isset($rules['meta'][$currentUri])) {
            $metaData = $rules['meta'][$currentUri];
        } elseif (isset($rules['meta'][$fullUrl])) {
            $metaData = $rules['meta'][$fullUrl];
        } elseif (isset($rules['meta'][$fullUrlSlash])) {
            $metaData = $rules['meta'][$fullUrlSlash];
        }

        $schemaData = $rules['schemas'] ?? null;

        if ($metaData || $schemaData) {
            ob_start(function ($html) use ($metaData, $schemaData) {
                return $this->injectMetaAndSchemas($html, $metaData, $schemaData);
            });
        }
    }

    /**
     * Inject Meta Tags and JSON-LD Schemas into HTML <head>.
     */
    public function injectMetaAndSchemas(string $html, ?array $meta, ?array $schemas): string
    {
        if (empty($html) || strpos($html, '</head>') === false) {
            return $html;
        }

        $injections = [];

        // 1. Meta Title Injection
        if (!empty($meta['title'])) {
            $safeTitle = htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8');
            $html = preg_replace('/<title[^>]*>.*?<\/title>/is', '', $html);
            $injections[] = "<title>{$safeTitle}</title>";
        }

        // 2. Meta Description Injection
        if (!empty($meta['description'])) {
            $safeDesc = htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8');
            $html = preg_replace('/<meta\s+name=["\']description["\'][^>]*>/i', '', $html);
            $injections[] = "<meta name=\"description\" content=\"{$safeDesc}\">";
        }

        // 3. JSON-LD Schema Injection
        if (!empty($schemas)) {
            $jsonSchema = is_string($schemas) ? $schemas : json_encode($schemas, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($jsonSchema) {
                $injections[] = "<script type=\"application/ld+json\">\n{$jsonSchema}\n</script>";
            }
        }

        if (empty($injections)) {
            return $html;
        }

        $injectionHtml = "\n<!-- WebKernelAI Dynamic Meta & Schema Injections -->\n" . implode("\n", $injections) . "\n</head>";
        return str_replace('</head>', $injectionHtml, $html);
    }

    /**
     * Legacy helper method for Meta Tag injection.
     */
    public function injectMetaTags(string $html, array $meta): string
    {
        return $this->injectMetaAndSchemas($html, $meta, null);
    }
}

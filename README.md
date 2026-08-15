# WebKernelAI PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webkernelai/php-sdk.svg?style=flat-square)](https://packagist.org/packages/webkernelai/php-sdk)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg?style=flat-square)](https://php.net)
[![Total Downloads](https://img.shields.io/packagist/dt/webkernelai/php-sdk.svg?style=flat-square)](https://packagist.org/packages/webkernelai/php-sdk)

Official PHP SDK for **[WebKernelAI](https://webkernelai.com)**. High-performance application security, automated Web Application Firewall (SQLi, XSS, RCE, LFI protection), cryptographic HMAC request signing, security headers injection, and SEO telemetry with first-class support for **Laravel**, **CodeIgniter**, **Symfony**, and **Vanilla PHP**.

---

## 🚀 Key Features

- 🛡️ **Embedded WAF (Web Application Firewall)**: Automatically inspects incoming `GET`, `POST`, and `COOKIE` parameters to block SQL injection, Cross-Site Scripting (XSS), Path Traversal (LFI/RFI), and Remote Command Injection (RCE).
- 🔐 **Cryptographic HMAC-SHA256 Signing**: Protects communication between your application and WebKernelAI Cloud with CSPRNG nonces and strict 300-second replay attack protection.
- ⚡ **Zero-Latency In-Memory & File Caching**: High-performance local cache manager with automated TTL expiration to prevent unnecessary network roundtrips.
- 🌐 **Automated Security Headers**: Injects production-grade HTTP security headers (`Content-Security-Policy`, `Strict-Transport-Security`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`).
- 📊 **SEO & Telemetry Sync**: Synchronize structured JSON-LD schemas, dynamic robots.txt rules, llms.txt signals, and blocked threat telemetry directly to your WebKernelAI dashboard.
- 🔌 **Native Framework Adapters**: Drop-in middleware and service providers for Laravel and CodeIgniter 3/4.

---

## 📦 Requirements

- PHP **7.4**, **8.0**, **8.1**, **8.2**, or **8.3+**
- `ext-json`, `ext-hash`, `ext-curl` (standard in modern PHP installations)

---

## 📥 Installation

Install via Composer:

```bash
composer require webkernelai/php-sdk
```

Or download and require the standalone autoloader:

```php
require_once __DIR__ . '/vendor/webkernelai/php-sdk/autoload.php';
```

---

## ⚡ Quick Start

### 1. Vanilla PHP / Custom Application

```php
use WebKernelAI\SDK\Config;
use WebKernelAI\SDK\Client;
use WebKernelAI\SDK\Security\Waf;
use WebKernelAI\SDK\Security\Headers;

require_once __DIR__ . '/vendor/autoload.php';

// 1. Initialize Configuration
$config = new Config([
    'site_id'        => 'YOUR_SITE_ID_OR_DOMAIN',
    'pairing_secret' => 'YOUR_PAIRING_SECRET_KEY',
    'api_url'        => 'https://api.webkernelai.com',
    'enable_waf'     => true,
    'enable_headers' => true,
]);

$client = new Client($config);

// 2. Run WAF Firewall Inspection
$waf = new Waf();
$threat = $waf->inspectRequest();

if ($threat['blocked']) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error'   => 'Blocked by WebKernelAI WAF',
        'type'    => $threat['type'],
    ]);
    exit;
}

// 3. Apply Hardened Security Headers
$headers = new Headers();
$headers->apply();
```

---

### 2. Laravel Integration

The SDK includes automated service discovery for **Laravel 9, 10, and 11+**.

1. Add your credentials to your `.env` file:

```env
WEBKERNELAI_SITE_ID=your_site_id
WEBKERNELAI_PAIRING_SECRET=your_pairing_secret_key
WEBKERNELAI_API_URL=https://api.webkernelai.com
```

2. Register the security middleware in `app/Http/Kernel.php` (or `bootstrap/app.php` for Laravel 11):

```php
// app/Http/Kernel.php
protected $middleware = [
    // ...
    \WebKernelAI\SDK\Laravel\WebKernelAISecurityMiddleware::class,
];
```

---

### 3. CodeIgniter 3 & 4 Integration

For **CodeIgniter 4**, add the filter in `app/Config/Filters.php`:

```php
// app/Config/Filters.php
public array $aliases = [
    // ...
    'webkernelai' => \WebKernelAI\SDK\CodeIgniter\WebKernelAISecurityFilter::class,
];

public array $globals = [
    'before' => [
        'webkernelai',
    ],
];
```

---

## 🔒 Cryptographic Request Signing (HMAC-SHA256)

For secure remote commands and webhook verification:

```php
use WebKernelAI\SDK\Security\Signer;

$secret    = 'your_pairing_secret';
$payload   = json_encode(['action' => 'sync_seo', 'site_id' => 123]);
$timestamp = (string) time();
$nonce     = Signer::generateNonce();

// Generate Signature
$signature = Signer::generateSignature($payload, $timestamp, $nonce, $secret);

// Verify Signature (enforces 300-second replay window protection)
$isValid = Signer::verifySignature($payload, $timestamp, $nonce, $signature, $secret, 300);

if (!$isValid) {
    throw new Exception('Invalid signature or expired request replay attempt.');
}
```

---

## ⚙️ Configuration Reference

| Key | Environment Variable | Default | Description |
|---|---|---|---|
| `site_id` | `WEBKERNELAI_SITE_ID` | `''` | Your WebKernelAI registered Domain or Site ID. |
| `pairing_secret` | `WEBKERNELAI_PAIRING_SECRET` | `''` | Cryptographic secret key from your dashboard. |
| `api_url` | `WEBKERNELAI_API_URL` | `https://api.webkernelai.com` | WebKernelAI API base endpoint. |
| `enable_waf` | `WEBKERNELAI_ENABLE_WAF` | `true` | Enable real-time SQLi, XSS, and RCE filtering. |
| `enable_headers` | `WEBKERNELAI_ENABLE_HEADERS` | `true` | Enable automated CSP, HSTS, and X-Frame headers. |
| `timeout` | `WEBKERNELAI_TIMEOUT` | `10` | HTTP request timeout in seconds. |
| `cache_dir` | `WEBKERNELAI_CACHE_DIR` | System Temp | Directory for caching telemetry & security policies. |

---

## 🧪 Testing

Run PHPUnit tests:

```bash
composer test
```

---

## 🤝 Contributing

Contributions, bug reports, and feature requests are welcome!
Feel free to check the [Issues page](https://github.com/WebKernelAI/php-sdk/issues).

---

## 📄 License

This SDK is open-source software licensed under the **[MIT License](LICENSE)**.

---

<p align="center">
  Built with ❤️ by the <strong><a href="https://webkernelai.com">WebKernelAI</a></strong> Team.
</p>

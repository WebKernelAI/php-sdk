# WebKernelAI PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webkernelai/php-sdk.svg?style=flat-square)](https://packagist.org/packages/webkernelai/php-sdk)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg?style=flat-square)](https://php.net)
[![Total Downloads](https://img.shields.io/packagist/dt/webkernelai/php-sdk.svg?style=flat-square)](https://packagist.org/packages/webkernelai/php-sdk)

Official PHP SDK for **[WebKernelAI](https://webkernelai.com)** — the next-generation enterprise infrastructure for **Technical SEO Intelligence**, **Zero-Trust Application Security**, and **Answer Engine Optimization (AEO / GEO)**.

This SDK provides real-time Web Application Firewall (WAF) filtering, cryptographic HMAC-SHA256 request verification, automated security headers injection, and real-time telemetry synchronization with first-class support for **Laravel**, **CodeIgniter**, **Symfony**, and **Core PHP**.

---

## 🔑 Generating Your Cryptographic Pairing Secret & Site ID

To securely connect your PHP application to the WebKernelAI Cloud without database dependencies, you need a high-entropy `WEBKERNELAI_PAIRING_SECRET` and `WEBKERNELAI_SITE_ID`.

### 👉 **[Generate Secure Pairing Secret Online](https://webkernelai.com/php-sdk)**

1. Visit **[https://webkernelai.com/php-sdk](https://webkernelai.com/php-sdk)**.
2. Enter your domain (e.g., `theoneeducation.in` or `yourdomain.com`).
3. Click **"Generate 256-Bit Pairing Secret"**.
4. The studio uses a cryptographically secure pseudorandom number generator (**256-bit CSPRNG**) to generate a collision-resistant `wk_sec_...` secret and unique `wk_...` site identifier.
5. Copy your credentials into your `.env` or application config.

> **🔒 Enterprise Cryptographic Guarantee**:
> WebKernelAI signatures use standard **HMAC-SHA256** message authentication with timestamp freshness validation (300-second replay attack protection window) and constant-time `hash_equals()` comparisons to prevent timing and replay exploits.

---

## 🚀 Key Features

- 🛡️ **Embedded WAF (Web Application Firewall)**: Automatically inspects incoming `GET`, `POST`, and `COOKIE` parameters to block SQL injection (UNION / Blind SQLi), Cross-Site Scripting (XSS), Path Traversal (LFI/RFI directory climbing), and Remote Command Injection (RCE).
- 🔐 **Cryptographic HMAC-SHA256 Signing**: Protects communication between your application and WebKernelAI Cloud with CSPRNG nonces and strict 300-second replay attack protection.
- ⚡ **Zero-Latency In-Memory & File Caching**: High-performance local cache manager with automated TTL expiration to keep server memory consumption under 2MB.
- 🌐 **Automated Security Headers**: Injects production-grade HTTP security headers (`Content-Security-Policy`, `Strict-Transport-Security`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`).
- 📊 **SEO & Telemetry Sync**: Synchronize structured JSON-LD schemas, dynamic robots.txt rules, llms.txt signals, and blocked threat telemetry directly to your WebKernelAI dashboard.
- 🔌 **Native Framework Adapters**: Drop-in middleware and service providers for Laravel (9, 10, 11+) and CodeIgniter (3 & 4).

---

## 📦 Requirements

- PHP **7.4**, **8.0**, **8.1**, **8.2**, or **8.3+**
- `ext-json`, `ext-hash`, `ext-curl` (standard in modern PHP distributions)

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

### 1. Core PHP / Custom Application

```php
use WebKernelAI\SDK\Config;
use WebKernelAI\SDK\Client;
use WebKernelAI\SDK\Security\Waf;
use WebKernelAI\SDK\Security\Headers;

require_once __DIR__ . '/vendor/autoload.php';

// 1. Initialize Configuration (Get keys from https://webkernelai.com/php-sdk)
$config = new Config([
    'site_id'        => 'YOUR_SITE_ID',
    'pairing_secret' => 'YOUR_PAIRING_SECRET_KEY',
    'api_url'        => 'https://api.webkernelai.com',
    'enable_waf'     => true,
    'enable_headers' => true,
]);

$client = new Client($config);

// 2. Run Real-Time WAF Threat Filter (SQLi, XSS, RCE, LFI)
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

// 3. Inject Production Hardened Security Headers (CSP, HSTS, X-Frame)
$headers = new Headers();
$headers->apply();
```

---

### 2. Laravel Integration (Laravel 9, 10, 11+)

The SDK includes automated service discovery for modern Laravel applications.

1. Add your credentials to your `.env` file (generated from [webkernelai.com/php-sdk](https://webkernelai.com/php-sdk)):

```env
WEBKERNELAI_SITE_ID=your_site_id
WEBKERNELAI_PAIRING_SECRET=your_pairing_secret_key
WEBKERNELAI_API_URL=https://api.webkernelai.com
WEBKERNELAI_ENABLE_WAF=true
WEBKERNELAI_ENABLE_HEADERS=true
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

For **CodeIgniter 4**, add your credentials in `.env` and register the filter in `app/Config/Filters.php`:

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

For secure remote command execution, telemetry reporting, and webhook verification:

```php
use WebKernelAI\SDK\Security\Signer;

$secret    = 'your_pairing_secret';
$payload   = json_encode(['action' => 'security_audit', 'site_id' => 123]);
$timestamp = (string) time();
$nonce     = Signer::generateNonce();

// 1. Generate Signature
$signature = Signer::generateSignature($payload, $timestamp, $nonce, $secret);

// 2. Verify Signature (enforces 300-second replay window protection)
$isValid = Signer::verifySignature($payload, $timestamp, $nonce, $signature, $secret, 300);

if (!$isValid) {
    throw new Exception('Invalid signature or expired replay attempt.');
}
```

---

## ⚙️ Configuration Reference

| Key | Environment Variable | Default | Description |
|---|---|---|---|
| `site_id` | `WEBKERNELAI_SITE_ID` | `''` | Registered Site ID from [webkernelai.com/php-sdk](https://webkernelai.com/php-sdk). |
| `pairing_secret` | `WEBKERNELAI_PAIRING_SECRET` | `''` | 256-bit CSPRNG cryptographic secret key. |
| `api_url` | `WEBKERNELAI_API_URL` | `https://api.webkernelai.com` | WebKernelAI API base endpoint. |
| `enable_waf` | `WEBKERNELAI_ENABLE_WAF` | `true` | Enable real-time SQLi, XSS, and RCE filtering. |
| `enable_headers` | `WEBKERNELAI_ENABLE_HEADERS` | `true` | Enable automated CSP, HSTS, and X-Frame headers. |
| `timeout` | `WEBKERNELAI_TIMEOUT` | `10` | HTTP request timeout in seconds. |
| `cache_dir` | `WEBKERNELAI_CACHE_DIR` | System Temp | Directory for caching telemetry & security policies. |

---

## 🌐 WebKernelAI Cloud Ecosystem

The PHP SDK seamlessly communicates with the **[WebKernelAI Command Center](https://webkernelai.com)**:
- **Centralized Telemetry**: Track blocked attacks, brute-force attempts, and WAF triggers in real time.
- **Automated Security Hardening**: Generate Content Security Policies (CSP), HSTS headers, and indexation controls from the cloud.
- **Deep Technical SEO**: Audit sitemaps, Core Web Vitals, and Answer Engine Optimization (AEO) visibility across ChatGPT, Perplexity, and Claude.

---

## 🧪 Testing

Run the automated test suite:

```bash
composer test
```

---

## 🤝 Contributing

Contributions, bug reports, and pull requests are welcome!
Feel free to open an issue on the **[GitHub Issues page](https://github.com/WebKernelAI/php-sdk/issues)**.

---

## 📄 License

This SDK is open-source software licensed under the **[MIT License](LICENSE)**.

---

<p align="center">
  Built with ❤️ by the <strong><a href="https://webkernelai.com">WebKernelAI</a></strong> Infrastructure Team.
</p>

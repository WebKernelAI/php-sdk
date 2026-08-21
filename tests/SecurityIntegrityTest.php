<?php

namespace WebKernelAI\SDK\Tests;

use PHPUnit\Framework\TestCase;
use WebKernelAI\SDK\Security\IntegrityEngine;
use WebKernelAI\SDK\Security\UploadGuard;
use WebKernelAI\SDK\Security\Waf;

class SecurityIntegrityTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wk_test_root_' . uniqid();
        @mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            IntegrityEngine::purgeDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function testIntegrityEnginePurgesRogueDirectories(): void
    {
        // Create legitimate framework directories
        @mkdir($this->tempDir . '/application', 0777, true);
        @mkdir($this->tempDir . '/uploads', 0777, true);

        // Create rogue directories (simulating the SEO hack folders)
        $rogueDir1 = $this->tempDir . '/testimonials';
        $rogueDir2 = $this->tempDir . '/itproductservices';
        @mkdir($rogueDir1, 0777, true);
        @mkdir($rogueDir2, 0777, true);
        file_put_contents($rogueDir1 . '/index.php', '<?php echo "spam";');
        file_put_contents($rogueDir2 . '/sitemap.xml', '<urlset></urlset>');

        $result = IntegrityEngine::enforce($this->tempDir, [], true);

        $this->assertTrue($result['success']);
        $this->assertContains('testimonials', $result['purged']);
        $this->assertContains('itproductservices', $result['purged']);
        $this->assertFileDoesNotExist($rogueDir1);
        $this->assertFileDoesNotExist($rogueDir2);

        // Verify legitimate folders remain intact
        $this->assertDirectoryExists($this->tempDir . '/application');
        $this->assertDirectoryExists($this->tempDir . '/uploads');
    }

    public function testUploadGuardBlocksDangerousExtensions(): void
    {
        $_FILES = [
            'avatar' => [
                'name'     => 'malicious_shell.php.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => '',
                'error'    => 0,
                'size'     => 1024,
            ]
        ];

        $threat = UploadGuard::inspect();
        $this->assertNotNull($threat);
        $this->assertTrue($threat['blocked']);
        $this->assertEquals('dangerous_extension', $threat['reason']);
    }

    public function testUploadGuardBlocksEmbeddedPhpCode(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tmpFile, "GIF89a\n<?php eval(\$_POST['cmd']); ?>");

        $_FILES = [
            'file' => [
                'name'     => 'image.gif',
                'type'     => 'image/gif',
                'tmp_name' => $tmpFile,
                'error'    => 0,
                'size'     => filesize($tmpFile),
            ]
        ];

        $threat = UploadGuard::inspect();
        $this->assertNotNull($threat);
        $this->assertTrue($threat['blocked']);
        $this->assertEquals('embedded_php_payload', $threat['reason']);
    }

    public function testWafBlocksWordPressProbesAndRce(): void
    {
        $waf = new Waf();

        $_GET = ['test' => 'eval(base64_decode("test"))'];
        $result = $waf->inspectRequest();
        $this->assertTrue($result['blocked']);
        $this->assertEquals('rce', $result['type']);

        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/xmlrpc.php?action=pingback';
        $result = $waf->inspectRequest();
        $this->assertTrue($result['blocked']);
        $this->assertEquals('wp_probe', $result['type']);
    }
}

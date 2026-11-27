<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Upload;

class UploadTest extends TestCase
{
    public function testPrivateFilesStayInsideTheirFolder(): void
    {
        $this->assertNull(\App\Core\Upload::privateFile('/uploads/slips/../../index.php', 'slips'));
        $this->assertNull(\App\Core\Upload::privateFile('/index.php', 'slips'));
        $this->assertNull(\App\Core\Upload::privateFile('', 'slips'));

        $dir = dirname(__DIR__) . '/public/uploads/slips';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'test-' . bin2hex(random_bytes(6)) . '.png';
        file_put_contents("{$dir}/{$name}", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        try {
            $this->assertSame('image/png', \App\Core\Upload::privateFile("/uploads/slips/{$name}", 'slips')['mime']);
        } finally {
            unlink("{$dir}/{$name}");
        }
    }

    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    public function testRejectsNonImageMimeType(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tmpFile, '<?php echo "malicious"; ?>');
        $this->tempFiles[] = $tmpFile;

        $fileArray = [
            'name' => 'shell.php.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $res = Upload::process($fileArray, 'test_uploads');
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('Invalid file format', $res['error']);
    }

    public function testRejectsOversizedFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_size_');
        file_put_contents($tmpFile, 'dummy');
        $this->tempFiles[] = $tmpFile;

        $fileArray = [
            'name' => 'huge.png',
            'type' => 'image/png',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 3 * 1024 * 1024, // 3 MB > 2 MB limit
        ];

        $res = Upload::process($fileArray, 'test_uploads');
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('2 MB', $res['error']);
    }

    public function testAcceptsValidPngAndRenamesToRandom(): void
    {
        // Minimal 1x1 valid PNG binary
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_png_');
        file_put_contents($tmpFile, $pngData);
        $this->tempFiles[] = $tmpFile;

        $fileArray = [
            'name' => 'original_client_name.png',
            'type' => 'image/png',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($pngData),
        ];

        $res = Upload::process($fileArray, 'test_uploads');
        $this->assertTrue($res['ok'], $res['error'] ?? '');
        $this->assertNotEquals('original_client_name.png', $res['filename']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $res['filename']);

        // Clean up uploaded file
        $uploadedFullPath = dirname(__DIR__) . '/public' . $res['path'];
        $this->assertFileExists($uploadedFullPath);
        @unlink($uploadedFullPath);
    }
}

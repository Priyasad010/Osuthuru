<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class PwaTest extends TestCase
{
    private function public(string $path = ''): string
    {
        return dirname(__DIR__) . '/public' . $path;
    }

    public function testManifestAndIconsAreComplete(): void
    {
        $manifest = json_decode(file_get_contents($this->public('/manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        foreach ($manifest['icons'] as $icon) {
            [$width, $height] = getimagesize($this->public($icon['src']));
            $this->assertSame($icon['sizes'], "{$width}x{$height}", $icon['src']);
        }
        $this->assertEqualsCanonicalizing(['192x192', '512x512', '512x512'], array_column($manifest['icons'], 'sizes'));
    }

    public function testEveryLocalFileTheWorkerStoresExists(): void
    {
        $worker = file_get_contents($this->public('/service-worker.js'));
        preg_match_all("#'(/[\w./-]+\.\w+)'#", $worker, $m);

        $this->assertNotEmpty($m[1]);
        foreach (array_unique($m[1]) as $file) {
            $this->assertFileExists($this->public($file));
        }
        $this->assertStringContainsString("credentials: 'omit'", $worker, 'Stored pages are anonymous');
    }
}

<?php
use PHPUnit\Framework\TestCase;

final class SeoOgImageTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/msd-og-test-' . bin2hex(random_bytes(4));
        mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->cacheDir)) return;
        foreach (scandir($this->cacheDir) as $f) {
            if ($f === '.' || $f === '..') continue;
            @unlink($this->cacheDir . '/' . $f);
        }
        @rmdir($this->cacheDir);
    }

    public function testGenerateProducesPngWithMagicBytes(): void
    {
        $path = Seo::generateOgImage('Hello world', '#0CC4B4', $this->cacheDir);
        $this->assertFileExists($path);
        $bytes = file_get_contents($path);
        $this->assertSame("\x89PNG\r\n\x1A\n", substr($bytes, 0, 8));
    }

    public function testGenerateIsCachedByTitleAndBrand(): void
    {
        $a = Seo::generateOgImage('Same', '#000000', $this->cacheDir);
        $b = Seo::generateOgImage('Same', '#000000', $this->cacheDir);
        $this->assertSame($a, $b);
    }

    public function testGenerateDifferentInputsProduceDifferentFiles(): void
    {
        $a = Seo::generateOgImage('Title A', '#0CC4B4', $this->cacheDir);
        $b = Seo::generateOgImage('Title B', '#0CC4B4', $this->cacheDir);
        $c = Seo::generateOgImage('Title A', '#FF0000', $this->cacheDir);
        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
    }

    public function testOutputDimensionsAre1200By630(): void
    {
        $path = Seo::generateOgImage('Sized test', '#0CC4B4', $this->cacheDir);
        $info = getimagesize($path);
        $this->assertSame(1200, $info[0]);
        $this->assertSame(630,  $info[1]);
    }

    public function testOgImageUrlForReturnsRelativeUrl(): void
    {
        $url = Seo::ogImageUrlFor('Hello', '#0CC4B4', $this->cacheDir);
        $this->assertMatchesRegularExpression('#^/og/[0-9a-f]{32}\.png$#', $url);
    }

    public function testInvalidBrandHexFallsBackGracefully(): void
    {
        $path = Seo::generateOgImage('Hello', 'not-a-hex', $this->cacheDir);
        $this->assertFileExists($path);
        $info = getimagesize($path);
        $this->assertSame(1200, $info[0]);
    }
}

<?php
use PHPUnit\Framework\TestCase;

final class ImageHelperTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/msd-image-test-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/data/uploads', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function makePng(string $relPath, int $w = 800, int $h = 600): void
    {
        $abs = $this->root . $relPath;
        @mkdir(dirname($abs), 0777, true);
        $im = imagecreatetruecolor($w, $h);
        imagepng($im, $abs);
        imagedestroy($im);
    }

    private function makeWebp(string $relPath, int $w, int $h): void
    {
        $abs = $this->root . $relPath;
        @mkdir(dirname($abs), 0777, true);
        $im = imagecreatetruecolor($w, $h);
        imagewebp($im, $abs);
        imagedestroy($im);
    }

    public function testRendersPictureWithWebpSourceWhenSiblingExists(): void
    {
        $this->makePng('/data/uploads/hero.jpg', 1200, 900);
        $this->makeWebp('/data/uploads/hero.webp', 1200, 900);
        $this->makeWebp('/data/uploads/hero-thumb.webp', 480, 360);

        $html = ImageHelper::render('/data/uploads/hero.jpg', ['alt' => 'Sunset'], $this->root);
        $this->assertStringContainsString('<picture', $html);
        $this->assertStringContainsString('<source', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('hero-thumb.webp 480w', $html);
        $this->assertStringContainsString('hero.webp', $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('src="/data/uploads/hero.jpg"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('alt="Sunset"', $html);
    }

    public function testRendersBareImgWhenNoWebpSibling(): void
    {
        $this->makePng('/data/uploads/icon.png', 32, 32);

        $html = ImageHelper::render('/data/uploads/icon.png', ['alt' => 'Icon'], $this->root);
        $this->assertStringNotContainsString('<source', $html);
        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('src="/data/uploads/icon.png"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function testAltIsHtmlEscaped(): void
    {
        $this->makePng('/data/uploads/a.png', 10, 10);
        $html = ImageHelper::render('/data/uploads/a.png', ['alt' => 'Tom & "Jerry" <3'], $this->root);
        $this->assertStringContainsString('alt="Tom &amp; &quot;Jerry&quot; &lt;3"', $html);
        $this->assertStringNotContainsString('alt="Tom & "Jerry" <3"', $html);
    }

    public function testClassIsPassedThrough(): void
    {
        $this->makePng('/data/uploads/b.png', 10, 10);
        $html = ImageHelper::render('/data/uploads/b.png', ['alt' => '', 'class' => 'hero-img u-rounded'], $this->root);
        $this->assertStringContainsString('class="hero-img u-rounded"', $html);
    }

    public function testSizesAttributeAppliedToSourceAndImg(): void
    {
        $this->makePng('/data/uploads/c.jpg', 1200, 900);
        $this->makeWebp('/data/uploads/c.webp', 1200, 900);
        $this->makeWebp('/data/uploads/c-thumb.webp', 480, 360);

        $sizes = '(min-width: 1024px) 50vw, 100vw';
        $html = ImageHelper::render('/data/uploads/c.jpg', ['alt' => 'x', 'sizes' => $sizes], $this->root);
        $this->assertStringContainsString('sizes="' . $sizes . '"', $html);
    }

    public function testReturnsEmptyForBlankUrl(): void
    {
        $this->assertSame('', ImageHelper::render('', ['alt' => 'x'], $this->root));
    }

    public function testGracefullyHandlesMissingFile(): void
    {
        $html = ImageHelper::render('/data/uploads/does-not-exist.jpg', ['alt' => 'gone'], $this->root);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('src="/data/uploads/does-not-exist.jpg"', $html);
        $this->assertStringNotContainsString('<picture', $html);
    }

    public function testCmsImageDelegates(): void
    {
        // Smoke check that CMS::image returns the same shape using its
        // default web-root resolution (we just want it to not error out
        // and return a string containing '<img').
        $cms = new CMS();
        $html = $cms->image('/data/uploads/__test-no-such-file.png', ['alt' => 'x']);
        $this->assertIsString($html);
        $this->assertStringContainsString('<img', $html);
    }
}

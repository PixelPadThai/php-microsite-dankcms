<?php
use PHPUnit\Framework\TestCase;

final class MediaTest extends TestCase
{
    private string $root;
    private string $uploads;
    private string $contentPath;
    private string $stringsPath;
    private string $backupDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/msd-media-test-' . bin2hex(random_bytes(4));
        $this->uploads = $this->root . '/uploads';
        mkdir($this->uploads, 0777, true);
        $this->contentPath = $this->root . '/content.json';
        $this->stringsPath = $this->root . '/strings.json';
        $this->backupDir   = $this->root . '/backups';
        mkdir($this->backupDir, 0777, true);
        file_put_contents($this->contentPath, json_encode([
            'settings' => ['logo' => '', 'site_name' => 'Test'],
            'collections' => ['pages' => [
                ['id' => 'home', 'slug' => '/', 'image' => '']
            ]],
        ]));
        file_put_contents($this->stringsPath, '{}');
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

    private function media(): Media
    {
        return new Media($this->uploads, $this->contentPath, $this->backupDir);
    }

    private function syntheticPngFile(string $name): array
    {
        $tmp = $this->root . '/__src_' . $name;
        $im  = imagecreatetruecolor(40, 20);
        imagefilledrectangle($im, 0, 0, 40, 20, imagecolorallocate($im, 30, 200, 180));
        imagepng($im, $tmp);
        imagedestroy($im);
        return [
            'name'     => $name,
            'type'     => 'image/png',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tmp),
        ];
    }

    private function syntheticSvgFile(string $name, string $body): array
    {
        $tmp = $this->root . '/__src_' . $name;
        file_put_contents($tmp, $body);
        return [
            'name'     => $name,
            'type'     => 'image/svg+xml',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tmp),
        ];
    }

    public function testUploadPngWritesOriginalWebpAndThumb(): void
    {
        $r = $this->media()->upload($this->syntheticPngFile('hero.png'));
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertFileExists($this->uploads . '/' . $r['name']);
        $base = pathinfo($r['name'], PATHINFO_FILENAME);
        $this->assertFileExists($this->uploads . '/' . $base . '.webp');
        $this->assertFileExists($this->uploads . '/' . $base . '-thumb.webp');
        $this->assertStringStartsWith('/data/uploads/', $r['url']);
    }

    public function testUploadSlugsAndSuffixesFilename(): void
    {
        $r = $this->media()->upload($this->syntheticPngFile('Big Hero Pic!.png'));
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertMatchesRegularExpression('/^big-hero-pic-[0-9a-f]{8}\.png$/', $r['name']);
    }

    public function testUploadSvgStripsScriptAndOnAttributes(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onclick="alert(1)" width="10" height="10">'
             . '<script>alert("xss")</script>'
             . '<foreignObject><iframe src="javascript:alert(1)"></iframe></foreignObject>'
             . '<a href="javascript:void(0)">x</a>'
             . '<rect width="10" height="10" fill="red"/>'
             . '</svg>';
        $r = $this->media()->upload($this->syntheticSvgFile('icon.svg', $svg));
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $saved = file_get_contents($this->uploads . '/' . $r['name']);
        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringNotContainsString('onclick', $saved);
        $this->assertStringNotContainsString('javascript:', $saved);
        $this->assertStringNotContainsString('<foreignObject', $saved);
        $this->assertStringContainsString('<rect', $saved);
    }

    public function testUploadRejectsNonImageMime(): void
    {
        $tmp = $this->root . '/__bad';
        file_put_contents($tmp, "this is just text");
        $r = $this->media()->upload([
            'name' => 'evil.png', 'type' => 'image/png', 'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp),
        ]);
        $this->assertFalse($r['ok']);
        $this->assertNotNull($r['error']);
    }

    public function testUploadRejectsBadUploadError(): void
    {
        $r = $this->media()->upload([
            'name' => 'x.png', 'type' => 'image/png', 'tmp_name' => '',
            'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0,
        ]);
        $this->assertFalse($r['ok']);
    }

    public function testListReturnsUploadedItems(): void
    {
        $m = $this->media();
        $m->upload($this->syntheticPngFile('a.png'));
        $m->upload($this->syntheticPngFile('b.png'));
        $items = $m->list();
        $this->assertCount(2, $items);
        foreach ($items as $it) {
            $this->assertArrayHasKey('name', $it);
            $this->assertArrayHasKey('size', $it);
            $this->assertArrayHasKey('url', $it);
            $this->assertArrayHasKey('has_webp', $it);
            $this->assertArrayHasKey('used_by_count', $it);
        }
    }

    public function testFindReferencesScansSettingsAndCollections(): void
    {
        $m = $this->media();
        $r = $m->upload($this->syntheticPngFile('hero.png'));
        $url = $r['url'];
        $content = json_decode(file_get_contents($this->contentPath), true);
        $content['settings']['logo'] = $url;
        $content['collections']['pages'][0]['image'] = $url;
        file_put_contents($this->contentPath, json_encode($content));

        $refs = $m->findReferences($r['name']);
        $this->assertCount(2, $refs);
        $paths = array_column($refs, 'path');
        $this->assertContains('settings.logo', $paths);
        $this->assertContains('collections.pages[0].image', $paths);
    }

    public function testDeleteBlocksWhenReferenced(): void
    {
        $m = $this->media();
        $r = $m->upload($this->syntheticPngFile('hero.png'));
        $url = $r['url'];
        $content = json_decode(file_get_contents($this->contentPath), true);
        $content['settings']['logo'] = $url;
        file_put_contents($this->contentPath, json_encode($content));

        $res = $m->delete($r['name']);
        $this->assertFalse($res['ok']);
        $this->assertNotEmpty($res['refs']);
        $this->assertFileExists($this->uploads . '/' . $r['name']);
    }

    public function testForceDeleteNullsReferencesAndRemovesFiles(): void
    {
        $m = $this->media();
        $r = $m->upload($this->syntheticPngFile('hero.png'));
        $url = $r['url'];
        $content = json_decode(file_get_contents($this->contentPath), true);
        $content['settings']['logo'] = $url;
        $content['collections']['pages'][0]['image'] = $url;
        file_put_contents($this->contentPath, json_encode($content));

        $res = $m->delete($r['name'], true);
        $this->assertTrue($res['ok'], json_encode($res));

        $this->assertFileDoesNotExist($this->uploads . '/' . $r['name']);
        $base = pathinfo($r['name'], PATHINFO_FILENAME);
        $this->assertFileDoesNotExist($this->uploads . '/' . $base . '.webp');
        $this->assertFileDoesNotExist($this->uploads . '/' . $base . '-thumb.webp');

        $content = json_decode(file_get_contents($this->contentPath), true);
        $this->assertSame('', $content['settings']['logo']);
        $this->assertSame('', $content['collections']['pages'][0]['image']);
    }

    public function testDeleteRejectsPathTraversal(): void
    {
        $m = $this->media();
        $res = $m->delete('../../../etc/passwd');
        $this->assertFalse($res['ok']);
    }
}

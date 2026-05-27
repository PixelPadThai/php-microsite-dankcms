<?php
use PHPUnit\Framework\TestCase;

final class BackupsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . '/msd-backups-' . uniqid();
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void {
        foreach (glob($this->dir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($this->dir);
    }

    public function testCreateMakesTimestampedCopy(): void {
        $src = $this->dir . '/strings.json';
        file_put_contents($src, '{"a":1}');
        $backupFile = Backups::create($src, $this->dir);
        $this->assertFileExists($backupFile);
        $this->assertMatchesRegularExpression('/strings-\d{4}-\d{2}-\d{2}_\d{6}\.json$/', $backupFile);
        $this->assertSame('{"a":1}', file_get_contents($backupFile));
    }

    public function testRotateKeepsLast30(): void {
        for ($i = 0; $i < 35; $i++) {
            $name = sprintf('%s/strings-2026-01-%02d_120000.json', $this->dir, $i);
            file_put_contents($name, "v$i");
            touch($name, time() - (35 - $i));
        }
        Backups::rotate($this->dir, 'strings', 30);
        $remaining = glob($this->dir . '/strings-*.json') ?: [];
        $this->assertCount(30, $remaining);
    }

    public function testListReturnsNewestFirst(): void {
        $a = $this->dir . '/strings-2026-01-01_120000.json';
        $b = $this->dir . '/strings-2026-01-02_120000.json';
        file_put_contents($a, '1'); touch($a, time() - 100);
        file_put_contents($b, '2'); touch($b, time());
        $list = Backups::list($this->dir, 'strings');
        $this->assertSame('strings-2026-01-02_120000.json', $list[0]['name']);
        $this->assertSame('strings-2026-01-01_120000.json', $list[1]['name']);
    }

    public function testRestoreRejectsMaliciousFilename(): void {
        $target = $this->dir . '/strings.json';
        file_put_contents($target, 'current');
        $this->assertFalse(Backups::restore($this->dir, '../etc/passwd', $target));
        $this->assertFalse(Backups::restore($this->dir, 'evil.json', $target));
        $this->assertSame('current', file_get_contents($target));
    }

    public function testRestoreOverwritesTargetAndKeepsSafetyCopy(): void {
        $target = $this->dir . '/strings.json';
        file_put_contents($target, '{"current":true}');
        $backup = $this->dir . '/strings-2026-01-01_120000.json';
        file_put_contents($backup, '{"restored":true}');
        $ok = Backups::restore($this->dir, basename($backup), $target);
        $this->assertTrue($ok);
        $this->assertSame('{"restored":true}', file_get_contents($target));
        $safetyCopies = glob($this->dir . '/strings-*.json') ?: [];
        $this->assertGreaterThanOrEqual(2, count($safetyCopies));
    }
}

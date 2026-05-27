<?php
final class Backups
{
    public static function create(string $srcPath, string $backupDir): string {
        if (!is_dir($backupDir)) mkdir($backupDir, 0775, true);
        $base = pathinfo($srcPath, PATHINFO_FILENAME);
        $stamp = date('Y-m-d_His');
        $dest = "$backupDir/{$base}-{$stamp}.json";
        copy($srcPath, $dest);
        return $dest;
    }

    public static function rotate(string $backupDir, string $prefix, int $keep): void {
        $files = glob("$backupDir/{$prefix}-*.json") ?: [];
        if (count($files) <= $keep) return;
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
        foreach (array_slice($files, 0, count($files) - $keep) as $f) @unlink($f);
    }

    public static function list(string $backupDir, string $prefix): array {
        $files = glob("$backupDir/{$prefix}-*.json") ?: [];
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        return array_map(fn($f) => ['name' => basename($f), 'size' => filesize($f), 'mtime' => filemtime($f)], $files);
    }

    public static function restore(string $backupDir, string $name, string $targetPath): bool {
        if (!preg_match('/^[a-z]+-\d{4}-\d{2}-\d{2}_\d{6}\.json$/', $name)) return false;
        $path = "$backupDir/$name";
        if (!is_file($path)) return false;
        self::create($targetPath, $backupDir);
        return copy($path, $targetPath);
    }
}

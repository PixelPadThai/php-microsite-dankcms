<?php
/**
 * Media: upload + sanitize + WebP/thumb derivatives + reference-safe delete.
 *
 * Constructed with an absolute uploads dir + the content.json path + the
 * backups dir. All paths returned via the public-facing URL (/data/uploads/...).
 */
final class Media
{
    private const MAX_BYTES   = 8 * 1024 * 1024;
    private const THUMB_EDGE  = 480;
    private const URL_PREFIX  = '/data/uploads/';
    private const ALLOWED_MIME = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml',
    ];
    private const EXT_BY_MIME = [
        'image/png'     => 'png',
        'image/jpeg'    => 'jpg',
        'image/gif'     => 'gif',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];

    public function __construct(
        private string $uploadsDir,
        private string $contentPath,
        private string $backupDir,
    ) {
        if (!is_dir($this->uploadsDir)) {
            @mkdir($this->uploadsDir, 0775, true);
        }
    }

    public function upload(array $file): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'name' => '', 'url' => '', 'error' => 'upload error: ' . $err];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'name' => '', 'url' => '', 'error' => 'file too large or empty'];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if (!is_file($tmp)) {
            return ['ok' => false, 'name' => '', 'url' => '', 'error' => 'tmp file missing'];
        }
        $sniffed = $this->sniffMime($tmp);
        if (!in_array($sniffed, self::ALLOWED_MIME, true)) {
            return ['ok' => false, 'name' => '', 'url' => '', 'error' => 'mime not allowed: ' . $sniffed];
        }

        $ext      = self::EXT_BY_MIME[$sniffed];
        $original = (string)($file['name'] ?? 'upload');
        $base     = pathinfo($original, PATHINFO_FILENAME);
        $slug     = $this->slugify($base);
        if ($slug === '') $slug = 'file';
        $name     = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $this->uploadsDir . '/' . $name;

        if ($sniffed === 'image/svg+xml') {
            $clean = $this->sanitizeSvg((string)file_get_contents($tmp));
            if (file_put_contents($dest, $clean) === false) {
                return ['ok' => false, 'name' => '', 'url' => '', 'error' => 'write failed'];
            }
        } else {
            if (!@copy($tmp, $dest)) {
                return ['ok' => false, 'name' => '', 'url' => '', 'error' => 'copy failed'];
            }
        }

        if (!in_array($sniffed, ['image/svg+xml', 'image/gif', 'image/webp'], true)) {
            $this->generateWebpAndThumb($dest, $sniffed);
        } elseif ($sniffed === 'image/webp') {
            $this->generateThumbFromWebp($dest);
        }

        return [
            'ok'    => true,
            'name'  => $name,
            'url'   => self::URL_PREFIX . $name,
            'error' => null,
        ];
    }

    public function list(): array
    {
        $items = [];
        $files = @scandir($this->uploadsDir) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f[0] === '.') continue;
            $path = $this->uploadsDir . '/' . $f;
            if (!is_file($path)) continue;
            if (str_starts_with($f, '__src_')) continue;
            $base = pathinfo($f, PATHINFO_FILENAME);
            $ext  = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if ($ext === 'webp' && (str_ends_with($base, '-thumb') || $this->siblingOriginalExists($base))) continue;

            $items[] = [
                'name'          => $f,
                'size'          => filesize($path) ?: 0,
                'mtime'         => filemtime($path) ?: 0,
                'mime'          => $this->sniffMime($path),
                'url'           => self::URL_PREFIX . $f,
                'has_webp'      => is_file($this->uploadsDir . '/' . $base . '.webp'),
                'has_thumb'     => is_file($this->uploadsDir . '/' . $base . '-thumb.webp'),
                'used_by_count' => count($this->findReferences($f)),
            ];
        }
        usort($items, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $items;
    }

    public function findReferences(string $name): array
    {
        if (!$this->isSafeName($name)) return [];
        $url = self::URL_PREFIX . $name;
        $content = json_decode(@file_get_contents($this->contentPath), true);
        if (!is_array($content)) return [];
        $hits = [];
        $this->walk($content, '', $url, $hits);
        return $hits;
    }

    public function delete(string $name, bool $force = false): array
    {
        if (!$this->isSafeName($name)) {
            return ['ok' => false, 'refs' => [], 'error' => 'invalid name'];
        }
        $refs = $this->findReferences($name);
        if (!empty($refs) && !$force) {
            return ['ok' => false, 'refs' => $refs, 'error' => 'in use'];
        }
        if (!empty($refs)) {
            $url = self::URL_PREFIX . $name;
            $content = json_decode(@file_get_contents($this->contentPath), true);
            if (!is_array($content)) {
                return ['ok' => false, 'refs' => $refs, 'error' => 'content.json missing'];
            }
            $this->nullRefs($content, $url);
            if (is_dir($this->backupDir)) {
                Backups::create($this->contentPath, $this->backupDir);
                Backups::rotate($this->backupDir, 'content', 30);
            }
            $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            $tmp  = $this->contentPath . '.tmp';
            if (file_put_contents($tmp, $json) === false || !rename($tmp, $this->contentPath)) {
                @unlink($tmp);
                return ['ok' => false, 'refs' => $refs, 'error' => 'content write failed'];
            }
        }

        $base = pathinfo($name, PATHINFO_FILENAME);
        $candidates = [
            $this->uploadsDir . '/' . $name,
            $this->uploadsDir . '/' . $base . '.webp',
            $this->uploadsDir . '/' . $base . '-thumb.webp',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) @unlink($p);
        }

        return ['ok' => true, 'refs' => $refs, 'error' => null];
    }

    private function siblingOriginalExists(string $base): bool
    {
        foreach (['png', 'jpg', 'jpeg', 'gif'] as $ext) {
            if (is_file($this->uploadsDir . '/' . $base . '.' . $ext)) return true;
        }
        return false;
    }

    private function isSafeName(string $name): bool
    {
        return $name !== '' && !str_contains($name, '/') && !str_contains($name, '\\') && !str_contains($name, "\0") && $name[0] !== '.';
    }

    private function sniffMime(string $path): string
    {
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if (!$f) return '';
        $m = @finfo_file($f, $path) ?: '';
        finfo_close($f);
        if ($m === 'image/svg' || $m === 'text/xml' || $m === 'application/xml') {
            $head = @file_get_contents($path, false, null, 0, 512) ?: '';
            if (preg_match('/<svg[\s>]/i', $head)) return 'image/svg+xml';
        }
        return $m;
    }

    private function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }

    private function sanitizeSvg(string $svg): string
    {
        $svg = preg_replace('#<script\b[^>]*>.*?</script\s*>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#<script\b[^>]*/>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject\s*>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#<foreignObject\b[^>]*/>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#\son[a-z]+\s*=\s*"[^"]*"#i', '', $svg) ?? $svg;
        $svg = preg_replace("#\son[a-z]+\s*=\s*'[^']*'#i", '', $svg) ?? $svg;
        $svg = preg_replace('#\son[a-z]+\s*=\s*[^\s>]+#i', '', $svg) ?? $svg;
        $svg = preg_replace('#(href|xlink:href|src)\s*=\s*"\s*javascript:[^"]*"#i', '$1="#"', $svg) ?? $svg;
        $svg = preg_replace("#(href|xlink:href|src)\s*=\s*'\s*javascript:[^']*'#i", '$1="#"', $svg) ?? $svg;
        return $svg;
    }

    private function generateWebpAndThumb(string $path, string $mime): void
    {
        $img = $this->loadGd($path, $mime);
        if (!$img) return;
        $base = pathinfo($path, PATHINFO_FILENAME);
        $dir  = dirname($path);
        @imagewebp($img, $dir . '/' . $base . '.webp', 82);
        $thumb = $this->resizeMax($img, self::THUMB_EDGE);
        if ($thumb) {
            @imagewebp($thumb, $dir . '/' . $base . '-thumb.webp', 78);
            imagedestroy($thumb);
        }
        imagedestroy($img);
    }

    private function generateThumbFromWebp(string $path): void
    {
        if (!function_exists('imagecreatefromwebp')) return;
        $img = @imagecreatefromwebp($path);
        if (!$img) return;
        $base = pathinfo($path, PATHINFO_FILENAME);
        $dir  = dirname($path);
        $thumb = $this->resizeMax($img, self::THUMB_EDGE);
        if ($thumb) {
            @imagewebp($thumb, $dir . '/' . $base . '-thumb.webp', 78);
            imagedestroy($thumb);
        }
        imagedestroy($img);
    }

    private function loadGd(string $path, string $mime)
    {
        return match ($mime) {
            'image/png'  => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default      => false,
        };
    }

    private function resizeMax($src, int $maxEdge)
    {
        $w = imagesx($src); $h = imagesy($src);
        if ($w <= $maxEdge && $h <= $maxEdge) {
            $copy = imagecreatetruecolor($w, $h);
            imagealphablending($copy, false); imagesavealpha($copy, true);
            imagecopy($copy, $src, 0, 0, 0, 0, $w, $h);
            return $copy;
        }
        $ratio = $maxEdge / max($w, $h);
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    private function walk($node, string $path, string $needle, array &$hits): void
    {
        if (is_string($node)) {
            if ($node === $needle) $hits[] = ['path' => ltrim($path, '.'), 'value' => $node];
            return;
        }
        if (!is_array($node)) return;
        $isList = array_is_list($node);
        foreach ($node as $k => $v) {
            $childPath = $isList ? $path . "[$k]" : $path . '.' . $k;
            $this->walk($v, $childPath, $needle, $hits);
        }
    }

    private function nullRefs(array &$node, string $needle): void
    {
        foreach ($node as $k => &$v) {
            if (is_string($v) && $v === $needle) {
                $v = '';
            } elseif (is_array($v)) {
                $this->nullRefs($v, $needle);
            }
        }
    }
}

<?php
/**
 * Seo: generates and caches 1200x630 OG share images.
 *
 * Cache key = md5(title|brandHex). Output path = cacheDir/{hash}.png.
 * Text is rendered with GD's built-in bitmap font scaled 6x via
 * imagecopyresampled so we don't need a TTF dependency.
 */
final class Seo
{
    private const W = 1200;
    private const H = 630;

    public static function generateOgImage(string $title, string $brandHex, string $cacheDir): string
    {
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
        $hash = md5($title . '|' . $brandHex);
        $path = rtrim($cacheDir, '/\\') . '/' . $hash . '.png';
        if (is_file($path)) return $path;

        [$br, $bg, $bb] = self::hexToRgb($brandHex);

        $im = imagecreatetruecolor(self::W, self::H);
        $bgColor    = imagecolorallocate($im, $br, $bg, $bb);
        $whiteSolid = imagecolorallocate($im, 255, 255, 255);
        $whiteSoft  = imagecolorallocate($im, 230, 230, 230);
        imagefilledrectangle($im, 0, 0, self::W, self::H, $bgColor);

        // Top-right accent dot using a soft tint of white over the brand.
        imagefilledellipse($im, self::W - 80, 80, 24, 24, $whiteSoft);

        // Bottom-left tag line with site brand color block.
        imagefilledrectangle($im, 60, self::H - 90, 120, self::H - 60, $whiteSolid);

        $lines = self::wrap($title, 28);
        $lines = array_slice($lines, 0, 4);

        $scale       = 6;
        $lineHeightS = imagefontheight(5);
        $lineHeight  = $lineHeightS * $scale + 12;
        $totalH      = $lineHeight * count($lines);
        $startY      = (int) round((self::H - $totalH) / 2);

        foreach ($lines as $i => $line) {
            self::drawScaledText($im, $line, $scale, $startY + $i * $lineHeight, $whiteSolid);
        }

        imagepng($im, $path, 6);
        imagedestroy($im);
        return $path;
    }

    public static function ogImageUrlFor(string $title, string $brandHex, string $cacheDir): string
    {
        self::generateOgImage($title, $brandHex, $cacheDir);
        $hash = md5($title . '|' . $brandHex);
        return '/og/' . $hash . '.png';
    }

    private static function drawScaledText($im, string $text, int $scale, int $y, int $color): void
    {
        $font = 5;
        $charW = imagefontwidth($font);
        $charH = imagefontheight($font);
        $tw = $charW * strlen($text);
        $th = $charH;

        $tmp = imagecreatetruecolor(max(1, $tw), max(1, $th));
        $bg  = imagecolorallocate($tmp, 0, 0, 0);
        imagecolortransparent($tmp, $bg);
        imagefilledrectangle($tmp, 0, 0, $tw, $th, $bg);
        imagestring($tmp, $font, 0, 0, $text, $color);

        $dstW = $tw * $scale;
        $dstH = $th * $scale;
        $dstX = (int) round((self::W - $dstW) / 2);
        imagecopyresampled($im, $tmp, $dstX, $y, 0, 0, $dstW, $dstH, $tw, $th);
        imagedestroy($tmp);
    }

    private static function wrap(string $text, int $maxChars): array
    {
        $text = trim($text);
        if ($text === '') return [' '];
        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $candidate = $cur === '' ? $w : ($cur . ' ' . $w);
            if (strlen($candidate) > $maxChars && $cur !== '') {
                $lines[] = $cur;
                $cur = $w;
            } else {
                $cur = $candidate;
            }
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines;
    }

    private static function hexToRgb(string $hex): array
    {
        $h = ltrim(trim($hex), '#');
        if (strlen($h) === 3) $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $h)) {
            return [0x0C, 0xC4, 0xB4];
        }
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }
}

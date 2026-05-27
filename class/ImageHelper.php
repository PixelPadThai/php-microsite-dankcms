<?php
/**
 * Renders an HTML <picture>/<img> block for a media URL.
 *
 * Detection rules:
 *  - <basename>.webp present     → emit <source type="image/webp">.
 *  - <basename>-thumb.webp also  → include it in the source srcset at 480w.
 *  - missing or unmeasurable     → degrade to a bare <img>.
 *
 * The function is pure: it only reads the filesystem to check siblings + size.
 */
final class ImageHelper
{
    public static function render(string $url, array $opts = [], string $webRoot = ''): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if ($webRoot === '') $webRoot = dirname(__DIR__);

        $alt   = (string)($opts['alt']   ?? '');
        $class = (string)($opts['class'] ?? '');
        $sizes = (string)($opts['sizes'] ?? '');

        $rel  = ltrim($url, '/');
        $base = $webRoot . '/' . $rel;
        $dir  = dirname($base);
        $name = pathinfo($base, PATHINFO_FILENAME);
        $webp      = $dir . '/' . $name . '.webp';
        $webpThumb = $dir . '/' . $name . '-thumb.webp';

        $urlDir = self::urlDir($url);
        $webpUrl      = $urlDir . $name . '.webp';
        $webpThumbUrl = $urlDir . $name . '-thumb.webp';

        $hasWebp  = is_file($webp);
        $hasThumb = is_file($webpThumb);

        $imgAttrs = sprintf(
            ' src="%s" loading="lazy"%s%s%s',
            self::e($url),
            $alt   !== '' ? ' alt="' . self::e($alt) . '"'   : ' alt=""',
            $class !== '' ? ' class="' . self::e($class) . '"' : '',
            $sizes !== '' && !$hasWebp ? ' sizes="' . self::e($sizes) . '"' : ''
        );

        if (!$hasWebp) {
            return '<img' . $imgAttrs . '>';
        }

        $srcsetParts = [];
        if ($hasThumb) $srcsetParts[] = self::e($webpThumbUrl) . ' 480w';
        $mainW = self::widthOf($webp);
        $srcsetParts[] = self::e($webpUrl) . ' ' . $mainW . 'w';

        $sourceAttrs = sprintf(
            ' type="image/webp" srcset="%s"%s',
            implode(', ', $srcsetParts),
            $sizes !== '' ? ' sizes="' . self::e($sizes) . '"' : ''
        );

        return "<picture><source$sourceAttrs><img$imgAttrs></picture>";
    }

    private static function widthOf(string $path): int
    {
        $info = @getimagesize($path);
        return is_array($info) && isset($info[0]) ? (int)$info[0] : 1200;
    }

    private static function urlDir(string $url): string
    {
        $i = strrpos($url, '/');
        return $i === false ? '' : substr($url, 0, $i + 1);
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

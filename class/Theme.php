<?php
/**
 * Theme: hex → OKLCH conversion and brand-scale CSS generator.
 *
 * Pipeline: sRGB hex → linear sRGB (gamma 2.4 piecewise) → XYZ D65
 * → OKLab (Björn Ottosson 2020) → OKLCH (polar form).
 */
final class Theme
{
    private const FALLBACK_HEX = '#0CC4B4';

    public static function hexToOklch(string $hex): array
    {
        [$r, $g, $b] = self::hexToSrgb($hex);

        $lr = self::srgbToLinear($r);
        $lg = self::srgbToLinear($g);
        $lb = self::srgbToLinear($b);

        $l = 0.4122214708 * $lr + 0.5363325363 * $lg + 0.0514459929 * $lb;
        $m = 0.2119034982 * $lr + 0.6806995451 * $lg + 0.1073969566 * $lb;
        $s = 0.0883024619 * $lr + 0.2817188376 * $lg + 0.6299787005 * $lb;

        $l_ = self::cbrt($l);
        $m_ = self::cbrt($m);
        $s_ = self::cbrt($s);

        $L = 0.2104542553 * $l_ + 0.7936177850 * $m_ - 0.0040720468 * $s_;
        $a = 1.9779984951 * $l_ - 2.4285922050 * $m_ + 0.4505937099 * $s_;
        $b2 = 0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.8086757660 * $s_;

        $C = sqrt($a * $a + $b2 * $b2);
        $H = rad2deg(atan2($b2, $a));
        if ($H < 0) $H += 360.0;

        return ['L' => $L, 'C' => $C, 'H' => $H];
    }

    public static function cssScaleFromHex(string $hex): string
    {
        $oklch = self::tryHexToOklch($hex);
        if ($oklch === null) {
            $hex   = self::FALLBACK_HEX;
            $oklch = self::tryHexToOklch($hex);
        }

        $H = $oklch['H'];
        $baseC = $oklch['C'];

        $stepL = [
            50  => 0.97,
            100 => 0.93,
            200 => 0.86,
            300 => 0.78,
            400 => 0.70,
            500 => 0.62,
            600 => 0.54,
            700 => 0.46,
            800 => 0.36,
            900 => 0.26,
        ];
        $stepCMul = [
            50 => 0.20, 100 => 0.40, 200 => 0.60, 300 => 0.80, 400 => 0.95,
            500 => 1.00, 600 => 0.95, 700 => 0.85, 800 => 0.70, 900 => 0.50,
        ];

        $lines = [];
        $lines[] = "  --color-primary: $hex;";
        foreach ($stepL as $step => $L) {
            $C = $baseC * $stepCMul[$step];
            $lines[] = sprintf(
                "  --color-primary-%d: oklch(%.4f %.4f %.2f);",
                $step,
                $L,
                $C,
                $H
            );
        }

        return ":root {\n" . implode("\n", $lines) . "\n}\n";
    }

    private static function tryHexToOklch(string $hex): ?array
    {
        try {
            return self::hexToOklch($hex);
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function hexToSrgb(string $hex): array
    {
        $h = ltrim(trim($hex), '#');
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $h)) {
            throw new InvalidArgumentException("Invalid hex color: $hex");
        }
        return [
            hexdec(substr($h, 0, 2)) / 255.0,
            hexdec(substr($h, 2, 2)) / 255.0,
            hexdec(substr($h, 4, 2)) / 255.0,
        ];
    }

    private static function srgbToLinear(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    }

    private static function cbrt(float $x): float
    {
        return $x < 0 ? -pow(-$x, 1.0 / 3.0) : pow($x, 1.0 / 3.0);
    }
}

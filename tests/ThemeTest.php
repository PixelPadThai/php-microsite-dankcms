<?php
use PHPUnit\Framework\TestCase;

final class ThemeTest extends TestCase
{
    public function testHexToOklchOnBrandTeal(): void
    {
        $r = Theme::hexToOklch('#0CC4B4');
        $this->assertEqualsWithDelta(0.72, $r['L'], 0.04, 'L');
        $this->assertEqualsWithDelta(0.105, $r['C'], 0.03, 'C');
        $this->assertEqualsWithDelta(190.0, $r['H'], 8.0, 'H (degrees)');
    }

    public function testHexToOklchOnPureWhite(): void
    {
        $r = Theme::hexToOklch('#ffffff');
        $this->assertEqualsWithDelta(1.0, $r['L'], 0.005);
        $this->assertEqualsWithDelta(0.0, $r['C'], 0.005);
    }

    public function testHexToOklchOnPureBlack(): void
    {
        $r = Theme::hexToOklch('#000000');
        $this->assertEqualsWithDelta(0.0, $r['L'], 0.005);
        $this->assertEqualsWithDelta(0.0, $r['C'], 0.005);
    }

    public function testHexToOklchAcceptsShorthand(): void
    {
        $a = Theme::hexToOklch('#fff');
        $b = Theme::hexToOklch('#ffffff');
        $this->assertEqualsWithDelta($a['L'], $b['L'], 0.001);
    }

    public function testHexToOklchAcceptsNoHash(): void
    {
        $a = Theme::hexToOklch('0CC4B4');
        $b = Theme::hexToOklch('#0CC4B4');
        $this->assertEqualsWithDelta($a['L'], $b['L'], 0.001);
        $this->assertEqualsWithDelta($a['C'], $b['C'], 0.001);
        $this->assertEqualsWithDelta($a['H'], $b['H'], 0.001);
    }

    public function testCssScaleEmitsAllTenSteps(): void
    {
        $css = Theme::cssScaleFromHex('#0CC4B4');
        $this->assertStringContainsString(':root', $css);
        $this->assertStringContainsString('--color-primary:', $css);
        foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900] as $step) {
            $this->assertStringContainsString("--color-primary-$step:", $css, "missing step $step");
        }
        $this->assertSame(10, substr_count($css, 'oklch('), 'expected 10 oklch() declarations');
    }

    public function testCssScaleContainsOriginalHexForBaseVariable(): void
    {
        $css = Theme::cssScaleFromHex('#0CC4B4');
        $this->assertStringContainsString('#0CC4B4', $css);
    }

    public function testCssScaleInvalidHexReturnsFallbackScale(): void
    {
        $css = Theme::cssScaleFromHex('not-a-color');
        $this->assertStringContainsString(':root', $css);
        $this->assertSame(10, substr_count($css, 'oklch('), 'fallback must still emit 10 steps');
    }

    public function testCssScaleStepsHaveDescendingLightness(): void
    {
        $css = Theme::cssScaleFromHex('#0CC4B4');
        preg_match_all('/--color-primary-(\d+):\s*oklch\(([0-9.]+)/', $css, $m);
        $byStep = array_combine($m[1], array_map('floatval', $m[2]));
        ksort($byStep, SORT_NUMERIC);
        $prev = null;
        foreach ($byStep as $step => $L) {
            if ($prev !== null) {
                $this->assertLessThan($prev, $L, "L at step $step should be lower than previous");
            }
            $prev = $L;
        }
    }
}

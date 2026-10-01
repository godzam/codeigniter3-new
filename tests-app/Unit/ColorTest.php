<?php

namespace AppTests\Unit;

use App\Theme\Color;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function testNormalizeAcceptsShortAndLongHexInAnyCase(): void
    {
        $this->assertSame('#aabbcc', Color::normalize('#ABC'));
        $this->assertSame('#0d6efd', Color::normalize('0D6EFD'));
        $this->assertSame('#112233', Color::normalize('  #112233 '));
    }

    public function testNormalizeRejectsInvalidInput(): void
    {
        foreach (['', 'red', '#12', '#12345', '#gggggg', '#1234567', null] as $input) {
            $this->assertNull(Color::normalize($input), var_export($input, true));
        }
    }

    public function testToRgbAndBack(): void
    {
        $this->assertSame([13, 110, 253], Color::toRgb('#0d6efd'));
        $this->assertSame('#0d6efd', Color::toHex([13, 110, 253]));
    }

    public function testShadeAndTintMoveTowardBlackAndWhite(): void
    {
        $this->assertSame('#000000', Color::shade('#336699', 1));
        $this->assertSame('#ffffff', Color::tint('#336699', 1));
        $this->assertSame('#336699', Color::shade('#336699', 0));
        $this->assertSame('#1a334d', Color::shade('#336699', 0.5));
    }

    public function testContrastPrefersWhiteWhenItMeetsAA(): void
    {
        // Bootstrap's own blue uses white text; so should we.
        $this->assertSame('#ffffff', Color::contrast('#0d6efd'));
        $this->assertSame('#ffffff', Color::contrast('#198754'));
        $this->assertSame('#ffffff', Color::contrast('#000000'));
    }

    public function testContrastFallsBackToBlackOnLightColors(): void
    {
        $this->assertSame('#000000', Color::contrast('#ffc107'));
        $this->assertSame('#000000', Color::contrast('#fd7e14'));
        $this->assertSame('#000000', Color::contrast('#ffffff'));
    }

    public function testContrastRatioBounds(): void
    {
        $this->assertEqualsWithDelta(21.0, Color::contrastRatio('#000000', '#ffffff'), 0.001);
        $this->assertEqualsWithDelta(1.0, Color::contrastRatio('#777777', '#777777'), 0.001);
    }

    public function testPaletteExposesTheBootstrapVariablesForBothThemes(): void
    {
        $palette = Color::palette('primary', '#0d6efd');

        foreach (['light', 'dark'] as $theme) {
            foreach (['--bs-primary', '--bs-primary-rgb', '--bs-primary-bg-subtle', '--app-primary-hover', '--app-primary-contrast'] as $var) {
                $this->assertArrayHasKey($var, $palette[$theme], "{$theme} {$var}");
            }
        }

        $this->assertSame('#0d6efd', $palette['light']['--bs-primary']);
        $this->assertSame('13, 110, 253', $palette['light']['--bs-primary-rgb']);
    }

    public function testDarkPaletteLightensColorsThatWouldVanishOnADarkBackground(): void
    {
        $palette = Color::palette('primary', '#1a1a66');

        $this->assertNotSame('#1a1a66', $palette['dark']['--bs-primary']);
        $this->assertGreaterThanOrEqual(4.5, Color::contrastRatio($palette['dark']['--bs-primary'], '#212529'));
        $this->assertSame('#1a1a66', $palette['light']['--bs-primary']);
    }
}

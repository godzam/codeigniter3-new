<?php

namespace App\Theme;

/**
 * Small color toolkit used to turn the two colors chosen in Settings
 * (primary, secondary) into the full set of Bootstrap 5.3 CSS variables —
 * hover/active shades, subtle backgrounds, emphasis text, and a readable
 * foreground color — for both the light and the dark theme.
 */
final class Color
{
    /**
     * Normalizes '#abc', 'ABC' or '#AABBCC' to '#aabbcc'; null if invalid.
     */
    public static function normalize($hex)
    {
        $hex = strtolower(ltrim(trim((string) $hex), '#'));

        if (preg_match('/^[0-9a-f]{3}$/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return preg_match('/^[0-9a-f]{6}$/', $hex) ? '#'.$hex : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function toRgb($hex)
    {
        $hex = self::normalize($hex) ?? '#000000';

        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    public static function toHex(array $rgb)
    {
        return sprintf('#%02x%02x%02x', ...array_map(static function ($c) {
            return max(0, min(255, (int) round($c)));
        }, $rgb));
    }

    /**
     * Mixes $hex with $with by $weight (0–1 = how much of $with).
     */
    public static function mix($hex, $with, $weight)
    {
        $a = self::toRgb($hex);
        $b = self::toRgb($with);

        return self::toHex([
            $a[0] + ($b[0] - $a[0]) * $weight,
            $a[1] + ($b[1] - $a[1]) * $weight,
            $a[2] + ($b[2] - $a[2]) * $weight,
        ]);
    }

    public static function shade($hex, $weight)
    {
        return self::mix($hex, '#000000', $weight);
    }

    public static function tint($hex, $weight)
    {
        return self::mix($hex, '#ffffff', $weight);
    }

    /**
     * WCAG relative luminance (0 = black, 1 = white).
     */
    public static function luminance($hex)
    {
        $channels = array_map(static function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::toRgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    public static function contrastRatio($a, $b)
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * '#ffffff' or '#000000' for text on $background. White is preferred
     * (it's what Bootstrap uses on its own blue) as long as it meets the
     * WCAG AA ratio of 4.5:1; black is only chosen when white doesn't and
     * black reads better.
     */
    public static function contrast($background)
    {
        $white = self::contrastRatio($background, '#ffffff');

        if ($white >= 4.5) {
            return '#ffffff';
        }

        return self::contrastRatio($background, '#000000') > $white ? '#000000' : '#ffffff';
    }

    /**
     * Every Bootstrap variable derived from one base color, for the light
     * and the dark theme.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function palette($name, $hex)
    {
        $hex = self::normalize($hex) ?? '#000000';
        $rgb = implode(', ', self::toRgb($hex));

        // In dark mode a dark brand color would vanish on a dark
        // background, so the base itself is lightened until it reads.
        $dark = $hex;
        while (self::contrastRatio($dark, '#212529') < 4.5 && $dark !== '#ffffff') {
            $dark = self::tint($dark, 0.15);
        }
        $darkRgb = implode(', ', self::toRgb($dark));

        return [
            'light' => [
                "--bs-{$name}" => $hex,
                "--bs-{$name}-rgb" => $rgb,
                "--bs-{$name}-text-emphasis" => self::shade($hex, 0.6),
                "--bs-{$name}-bg-subtle" => self::tint($hex, 0.8),
                "--bs-{$name}-border-subtle" => self::tint($hex, 0.6),
                "--app-{$name}-hover" => self::shade($hex, 0.15),
                "--app-{$name}-active" => self::shade($hex, 0.2),
                "--app-{$name}-contrast" => self::contrast($hex),
                "--app-{$name}-hover-contrast" => self::contrast(self::shade($hex, 0.15)),
            ],
            'dark' => [
                "--bs-{$name}" => $dark,
                "--bs-{$name}-rgb" => $darkRgb,
                "--bs-{$name}-text-emphasis" => self::tint($dark, 0.4),
                "--bs-{$name}-bg-subtle" => self::shade($dark, 0.8),
                "--bs-{$name}-border-subtle" => self::shade($dark, 0.6),
                "--app-{$name}-hover" => self::tint($dark, 0.15),
                "--app-{$name}-active" => self::tint($dark, 0.2),
                "--app-{$name}-contrast" => self::contrast($dark),
                "--app-{$name}-hover-contrast" => self::contrast(self::tint($dark, 0.15)),
            ],
        ];
    }
}

<?php

namespace AppTests\Unit;

use App\Page\Html;
use App\Page\Links;
use PHPUnit\Framework\TestCase;

final class PageSafetyTest extends TestCase
{
    // ---- Links ---------------------------------------------------------

    public function testAllowedAddresses(): void
    {
        foreach (['https://example.com', 'http://example.com/a?b=1#c', 'mailto:me@example.com', 'tel:+62123', 'register', 'page/about', 'admin/users?x=1', '#top', '/dashboard'] as $url) {
            $this->assertSame($url, Links::safe($url), $url);
        }
        $this->assertSame('https://example.com', Links::safe('  https://example.com  '), 'trimmed');
    }

    public function testRefusedAddresses(): void
    {
        $bad = [
            '', '   ', 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', "java\tscript:alert(1)", "java\nscript:alert(1)", " javascript:alert(1)",
            'data:text/html,<script>alert(1)</script>', 'vbscript:x', 'file:///etc/passwd', 'blob:https://x', 'ftp://example.com',
            '//evil.example/path', '\\\\evil.example', 'https:///nohost', 'http://', "https://exa mple.com", "https://exa\0mple.com",
            str_repeat('a', 2001),
        ];
        foreach ($bad as $url) {
            $this->assertNull(Links::safe($url), json_encode($url));
        }
    }

    public function testResolveKeepsAbsoluteAndPutsThisSitesPagesUnderTheSiteAddress(): void
    {
        $site = static function ($path) {
            return 'https://app.test/index.php/'.$path;
        };

        $this->assertSame('https://example.com/x', Links::resolve('https://example.com/x', $site));
        $this->assertSame('mailto:a@b.c', Links::resolve('mailto:a@b.c', $site));
        $this->assertSame('https://app.test/index.php/page/about', Links::resolve('page/about', $site));
        $this->assertSame('https://app.test/index.php/register', Links::resolve('/register', $site));
        $this->assertSame('#top', Links::resolve('#top', $site));
        $this->assertNull(Links::resolve('javascript:alert(1)', $site));
    }

    // ---- Html ----------------------------------------------------------

    public function testNormalMarkupSurvives(): void
    {
        $this->assertSame('<p>Hello <strong>big</strong> <em>world</em></p>', Html::sanitize('<p>Hello <strong>big</strong> <em>world</em></p>'));
        $this->assertSame('<ul><li>a</li><li>b</li></ul>', Html::sanitize('<ul><li>a</li><li>b</li></ul>'));
        $this->assertSame('', Html::sanitize('   '));
        $this->assertSame('Tom &amp; Jerry &lt;3', Html::sanitize('Tom &amp; Jerry &lt;3'));
    }

    public function testLinksKeepOnlyASafeHrefAndGetRel(): void
    {
        $this->assertSame('<a href="https://example.com/a?b=1&amp;c=2" rel="noopener noreferrer">x</a>', Html::sanitize('<a href="https://example.com/a?b=1&amp;c=2" target="_blank" onclick="x()" class="y">x</a>'));
        $this->assertSame('<a>x</a>', Html::sanitize('<a href="javascript:alert(1)">x</a>'));
        $this->assertSame('<a>x</a>', Html::sanitize('<a href="&#106;avascript:alert(1)">x</a>'), 'entity-encoded scheme');
        $this->assertSame('<a>x</a>', Html::sanitize("<a href=\"jav&#x09;ascript:alert(1)\">x</a>"), 'tab inside the scheme');
        $this->assertSame('<a>x</a>', Html::sanitize('<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function payloads(): array
    {
        return [
            'script' => ['<script>alert(1)</script>'],
            'script in p' => ['<p>hi<script>alert(1)</script></p>'],
            'img onerror' => ['<img src=x onerror=alert(1)>'],
            'svg onload' => ['<svg onload=alert(1)>'],
            'svg script' => ['<svg><script>alert(1)</script></svg>'],
            'math' => ['<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>'],
            'iframe' => ['<iframe src="javascript:alert(1)"></iframe>'],
            'object' => ['<object data="javascript:alert(1)"></object>'],
            'embed' => ['<embed src="javascript:alert(1)">'],
            'form' => ['<form action="javascript:alert(1)"><input type=submit></form>'],
            'style' => ['<style>@import "javascript:alert(1)"</style>'],
            'span handler' => ['<span onmouseover="alert(1)" style="x">hover</span>'],
            'p handler' => ['<p onclick=alert(1)>x</p>'],
            'broken tag' => ['<scr<script>ipt>alert(1)</scr</script>ipt>'],
            'case' => ['<ScRiPt>alert(1)</sCrIpT>'],
            'comment' => ['<!--<script>alert(1)</script>-->'],
            'noscript' => ['<noscript><p title="</noscript><img src=x onerror=alert(1)>">'],
            'template' => ['<template><script>alert(1)</script></template>'],
            'base' => ['<base href="javascript:alert(1)//">'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=javascript:alert(1)">'],
            'textarea' => ['<textarea><script>alert(1)</script></textarea>'],
            'xmp' => ['<xmp><script>alert(1)</script></xmp>'],
        ];
    }

    public function testNothingRunnableSurvivesAnyPayload(): void
    {
        foreach (self::payloads() as $name => [$payload]) {
            $out = strtolower(Html::sanitize($payload));
            foreach (['<script', '<img', '<svg', '<iframe', '<object', '<embed', '<form', '<style', '<math', '<base', '<meta', 'onerror', 'onload', 'onclick', 'onmouseover', 'javascript:', 'alert(1)<'] as $needle) {
                $this->assertStringNotContainsString($needle, $out, $name.' -> '.$out);
            }
        }
    }

    public function testUnknownElementsKeepTheirText(): void
    {
        $this->assertSame('hi there', Html::sanitize('<div>hi <font color=red>there</font></div>'));
    }
}

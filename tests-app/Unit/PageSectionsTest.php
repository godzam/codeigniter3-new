<?php

namespace AppTests\Unit;

use App\Page\SectionTypes;
use PHPUnit\Framework\TestCase;

final class PageSectionsTest extends TestCase
{
    public function testEveryTypeHasALabelAndItsFields(): void
    {
        foreach (SectionTypes::labels() as $type => $label) {
            $this->assertNotSame('', $label);
            $this->assertTrue(SectionTypes::exists($type), $type);
            $fields = SectionTypes::fields($type);
            $this->assertNotEmpty($fields, $type);
            foreach ($fields as $field) {
                $this->assertNotEmpty($field['name']);
                $this->assertNotEmpty($field['label']);
                $this->assertContains($field['type'], ['text', 'textarea', 'color']);
            }
        }

        $this->assertFalse(SectionTypes::exists('script'), 'an unknown type is unknown');
    }

    public function testNormalizeDropsUnknownBlocksAndTrimsValues(): void
    {
        $sections = SectionTypes::normalize([
            ['type' => 'hero', 'settings' => ['heading' => '  Hello  ', 'nope' => 'x']],
            ['type' => 'evil', 'settings' => ['html' => '<script>bad()</script>']],
            'not an array',
        ]);

        $this->assertCount(1, $sections);
        $this->assertSame('hero', $sections[0]['type']);
        $this->assertSame(['heading' => 'Hello'], $sections[0]['settings'], 'unknown settings are dropped');
    }

    public function testNormalizeAcceptsJsonAndReturnsEmptyForNothing(): void
    {
        $this->assertSame([], SectionTypes::normalize(''));
        $this->assertSame([], SectionTypes::normalize(null));
        $this->assertSame([], SectionTypes::toArray('not json'));

        $fromJson = SectionTypes::toArray('[{"type":"text","settings":{"body":"Hi"}}]');
        $this->assertSame('text', $fromJson[0]['type']);
        $this->assertSame('Hi', $fromJson[0]['settings']['body']);
    }

    public function testEncodeKeepsARoundTrip(): void
    {
        $sections = [['type' => 'cta', 'settings' => ['heading' => 'Join', 'button_label' => 'Sign up']]];
        $json = SectionTypes::encode($sections);

        $this->assertSame($sections, json_decode($json, true));
    }

    public function testAVeryLongValueIsCutSoOnePageStillFitsTheColumn(): void
    {
        $sections = SectionTypes::normalize([
            ['type' => 'text', 'settings' => ['body' => str_repeat('a', SectionTypes::MAX_VALUE + 500)]],
        ]);

        $this->assertSame(SectionTypes::MAX_VALUE, mb_strlen($sections[0]['settings']['body']));
    }

    public function testRenderedValuesAreEscaped(): void
    {
        $html = SectionTypes::render([
            'type' => 'hero',
            'settings' => ['heading' => '<script>alert(1)</script>', 'button_label' => 'Go', 'button_url' => 'javascript:alert(1)'],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('href="javascript:alert(1)"', $html, 'the URL is escaped, not executed');
    }

    public function testButtonsOnlyRenderWithBothLabelAndUrl(): void
    {
        $withBoth = SectionTypes::render(['type' => 'cta', 'settings' => ['heading' => 'Hi', 'button_label' => 'Go', 'button_url' => '/x']]);
        $missingUrl = SectionTypes::render(['type' => 'cta', 'settings' => ['heading' => 'Hi', 'button_label' => 'Go']]);

        $this->assertStringContainsString('class="btn btn-light"', $withBoth);
        $this->assertStringNotContainsString('<a ', $missingUrl);
    }

    public function testFeatureCardsAreParsedLineByLine(): void
    {
        $html = SectionTypes::render([
            'type' => 'features',
            'settings' => ['heading' => 'Why', 'items' => "Fast | Loads quickly\nSimple | One file\n\nSecond | line"],
        ]);

        $this->assertSame(3, substr_count($html, 'card-title'));
        $this->assertStringContainsString('Fast', $html);
        $this->assertStringContainsString('Second', $html);
        $this->assertStringContainsString('line', $html);
    }

    public function testBackgroundColorsMustLookLikeColors(): void
    {
        $ok = SectionTypes::render(['type' => 'hero', 'settings' => ['heading' => 'Hi', 'background' => '#123456']]);
        $bad = SectionTypes::render(['type' => 'hero', 'settings' => ['heading' => 'Hi', 'background' => 'red; position:fixed']]);

        $this->assertStringContainsString('style="background:#123456"', $ok);
        $this->assertStringNotContainsString('position:fixed', $bad);
    }

    public function testTheHtmlBlockIsLeftAloneAndRenderAllJoinsBlocks(): void
    {
        $raw = '<div class="x">custom</div>';
        $html = SectionTypes::render(['type' => 'html', 'settings' => ['html' => $raw]]);
        $this->assertSame($raw, $html);

        $all = SectionTypes::renderAll([
            ['type' => 'text', 'settings' => ['heading' => 'A', 'body' => 'Body']],
            ['type' => 'html', 'settings' => ['html' => $raw]],
        ]);
        $this->assertStringContainsString('<h2', $all);
        $this->assertStringContainsString($raw, $all);
    }

    public function testTextBodiesKeepAllowedMarkupAndIgnoreTheRest(): void
    {
        $html = SectionTypes::render([
            'type' => 'text',
            'settings' => ['body' => "First <a href=\"/x\">link</a>\n\nSecond <img src=x onerror=alert(1)>"],
        ]);

        $this->assertStringContainsString('<a href="/x">link</a>', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('<p>', $html);
    }
}

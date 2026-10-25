<?php

namespace App\Page;

/**
 * The blocks a page is built from (Admin -> Pages), as pure PHP: which types
 * exist, what each one asks for, how a stored layout is cleaned up, and how it
 * is rendered on the public page. Unit-tested in tests-app/Unit/PageSectionsTest.php.
 *
 * A page's `sections` column holds a JSON list:
 *
 *   [{"type": "hero", "settings": {"heading": "Hello", ...}}, ...]
 *
 * Everything except the "html" block type escapes its values, so a layout
 * authored in the admin cannot inject markup into the public page by accident.
 */
final class SectionTypes
{
    public const HERO = 'hero';
    public const TEXT = 'text';
    public const FEATURES = 'features';
    public const CTA = 'cta';
    public const IMAGE = 'image';
    public const HTML = 'html';

    /** Most blocks a page may hold (keeps one page inside a TEXT column). */
    public const MAX_SECTIONS = 40;

    /** Longest single setting value; longer text is cut by normalize(). */
    public const MAX_VALUE = 20000;

    /** Tags a "text" block body may contain; everything else is stripped. */
    private const BODY_TAGS = '<p><br><a><strong><em><b><i><u><ul><ol><li><h2><h3><h4><blockquote><code><pre><span><hr>';

    /**
     * @return array<string, string> type => label for the builder's dropdown
     */
    public static function labels()
    {
        return [
            self::HERO => 'Hero (title + button)',
            self::TEXT => 'Text block',
            self::FEATURES => 'Features (cards)',
            self::CTA => 'Call to action',
            self::IMAGE => 'Image',
            self::HTML => 'Custom HTML',
        ];
    }

    public static function exists($type)
    {
        return isset(self::labels()[$type]);
    }

    /**
     * The settings one block type asks for, in order.
     *
     * Each entry: label, type (text | textarea | color), optional help.
     *
     * @return array<int, array<string, string>>
     */
    public static function fields($type)
    {
        switch ($type) {
            case self::HERO:
                return [
                    ['name' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['name' => 'subheading', 'label' => 'Description', 'type' => 'textarea', 'rows' => 3],
                    ['name' => 'button_label', 'label' => 'Button label', 'type' => 'text', 'help' => 'Leave empty for no button.'],
                    ['name' => 'button_url', 'label' => 'Button link', 'type' => 'text', 'help' => 'e.g. register, page/about or https://example.com'],
                    ['name' => 'background', 'label' => 'Background color', 'type' => 'color'],
                ];

            case self::TEXT:
                return [
                    ['name' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['name' => 'body', 'label' => 'Body', 'type' => 'textarea', 'rows' => 8,
                        'help' => 'One paragraph per line. Links, bold, lists and headings in the HTML are kept.'],
                ];

            case self::FEATURES:
                return [
                    ['name' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['name' => 'items', 'label' => 'Cards', 'type' => 'textarea', 'rows' => 8,
                        'help' => 'One card per line: Title | Short description'],
                ];

            case self::CTA:
                return [
                    ['name' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3],
                    ['name' => 'button_label', 'label' => 'Button label', 'type' => 'text'],
                    ['name' => 'button_url', 'label' => 'Button link', 'type' => 'text'],
                    ['name' => 'background', 'label' => 'Background color', 'type' => 'color'],
                ];

            case self::IMAGE:
                return [
                    ['name' => 'url', 'label' => 'Image URL or path', 'type' => 'text',
                        'help' => 'e.g. uploads/hero.jpg or https://…'],
                    ['name' => 'alt', 'label' => 'Alt text', 'type' => 'text'],
                    ['name' => 'caption', 'label' => 'Caption', 'type' => 'text'],
                ];

            default: // self::HTML
                return [
                    ['name' => 'html', 'label' => 'HTML', 'type' => 'textarea', 'rows' => 10,
                        'help' => 'Rendered as-is on the public page. Only give this to people who write HTML.'],
                ];
        }
    }

    /**
     * Cleans what was posted in the builder: unknown blocks are dropped,
     * values are trimmed strings, and an empty layout comes back empty.
     *
     * @param array<mixed> $sections
     *
     * @return array<int, array{type: string, settings: array<string, string>}>
     */
    public static function normalize($sections)
    {
        if (!is_array($sections)) {
            return [];
        }

        $out = [];
        foreach (array_slice(array_values($sections), 0, self::MAX_SECTIONS) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $type = (string) ($section['type'] ?? '');
            if (!self::exists($type)) {
                continue;
            }

            $known = array_column(self::fields($type), 'name');
            $settings = [];
            foreach ((array) ($section['settings'] ?? []) as $key => $value) {
                if (!in_array((string) $key, $known, true)) {
                    continue;
                }
                $value = is_scalar($value) ? trim((string) $value) : '';
                if ($value !== '') {
                    $settings[(string) $key] = self::cut($value);
                }
            }

            $out[] = ['type' => $type, 'settings' => $settings];
        }

        return $out;
    }

    /**
     * @param array<mixed> $sections
     */
    public static function toArray($sections)
    {
        return self::normalize(is_array($sections) ? $sections : json_decode((string) $sections, true));
    }

    /**
     * @param array<mixed> $sections
     */
    public static function encode($sections)
    {
        return (string) json_encode(self::normalize($sections), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * One block as HTML for the public page.
     *
     * @param array<string, mixed> $section
     */
    public static function render(array $section)
    {
        $type = (string) ($section['type'] ?? '');
        $s = (array) ($section['settings'] ?? []);

        switch ($type) {
            case self::HERO:
                $style = self::background($s['background'] ?? '');

                return '<section class="page-hero py-5"'.($style ? ' style="'.$style.'"' : '').'>'
                    .'<div class="container py-4">'
                    .(!empty($s['heading']) ? '<h1 class="display-5 fw-bold mb-3">'.self::text($s['heading']).'</h1>' : '')
                    .(!empty($s['subheading']) ? '<p class="lead mb-4">'.nl2br(self::text($s['subheading'])).'</p>' : '')
                    .self::button($s)
                    .'</div></section>';

            case self::TEXT:
                return '<section class="py-5"><div class="container">'
                    .(!empty($s['heading']) ? '<h2 class="h3 mb-3">'.self::text($s['heading']).'</h2>' : '')
                    .(!empty($s['body']) ? '<div class="page-body">'.self::body($s['body']).'</div>' : '')
                    .'</div></section>';

            case self::FEATURES:
                $items = self::pairs($s['items'] ?? '');
                $html = '<section class="py-5 bg-body-tertiary"><div class="container">'
                    .(!empty($s['heading']) ? '<h2 class="h3 mb-4">'.self::text($s['heading']).'</h2>' : '');
                if ($items !== []) {
                    $html .= '<div class="row g-3">';
                    foreach ($items as [$title, $text]) {
                        $html .= '<div class="col-12 col-md-6 col-lg-4"><div class="card h-100"><div class="card-body">'
                            .'<h3 class="h6 card-title mb-2">'.self::text($title).'</h3>'
                            .($text !== '' ? '<p class="card-text small text-body-secondary mb-0">'.self::text($text).'</p>' : '')
                            .'</div></div></div>';
                    }
                    $html .= '</div>';
                }
                return $html.'</div></section>';

            case self::CTA:
                $style = self::background($s['background'] ?? '', 'var(--bs-primary)');

                return '<section class="py-5" style="'.rtrim($style, ';').';color:#fff"><div class="container py-2">'
                    .(!empty($s['heading']) ? '<h2 class="h3 mb-2">'.self::text($s['heading']).'</h2>' : '')
                    .(!empty($s['text']) ? '<p class="mb-4 opacity-75">'.self::text($s['text']).'</p>' : '')
                    .self::button($s, 'btn btn-light')
                    .'</div></section>';

            case self::IMAGE:
                if (empty($s['url'])) {
                    return '';
                }
                $img = '<img src="'.self::text($s['url']).'" alt="'.self::text($s['alt'] ?? '').'" class="img-fluid rounded" loading="lazy">';

                return '<section class="py-4"><div class="container">'
                    .(!empty($s['caption']) ? '<figure class="mb-0">'.$img.'<figcaption class="small text-body-secondary mt-2">'.self::text($s['caption']).'</figcaption></figure>' : $img)
                    .'</div></section>';

            default: // self::HTML
                // Deliberate: this block type is raw HTML, written by an admin.
                return (string) ($s['html'] ?? '');
        }
    }

    /**
     * A whole layout as HTML.
     *
     * @param array<mixed> $sections
     */
    public static function renderAll($sections)
    {
        $html = '';
        foreach (self::normalize($sections) as $section) {
            $html .= self::render($section);
        }

        return $html;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private static function text($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** Body text: HTML allow-list, then safe. Paragraphs come from new lines. */
    private static function body($value)
    {
        $html = strip_tags((string) $value, self::BODY_TAGS);
        $html = preg_replace('/\s*\R\s*\R\s*/', "</p>\n<p>", $html) ?? $html;

        return '<p>'.trim($html).'</p>';
    }

    private static function button(array $s, $class = 'btn btn-primary')
    {
        if (empty($s['button_label']) || empty($s['button_url'])) {
            return '';
        }

        return '<a href="'.self::text($s['button_url']).'" class="'.$class.'">'.self::text($s['button_label']).'</a>';
    }

    /** "Title | description" lines as pairs. */
    private static function pairs($text)
    {
        $out = [];
        foreach (preg_split('/\R/', (string) $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $out[] = [$parts[0], $parts[1] ?? ''];
        }

        return $out;
    }

    private static function background($color, $fallback = '')
    {
        $color = (string) $color;
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
            return $fallback !== '' ? 'background:'.$fallback : '';
        }

        return 'background:'.$color;
    }

    private static function cut($value)
    {
        return mb_strlen($value) > self::MAX_VALUE ? mb_substr($value, 0, self::MAX_VALUE) : $value;
    }
}

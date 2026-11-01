<?php

namespace App\Page;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans the small piece of HTML a "Text block" may contain.
 *
 * The text is parsed into a document tree and rebuilt from an allow-list, so
 * nothing the browser could run survives:
 *  - only these elements stay: p br strong em b i u ul ol li h2 h3 h4 blockquote code pre span hr a;
 *  - other elements are removed but their text stays (`<div>hi</div>` becomes `hi`);
 *    script, style, iframe, object, embed, svg, math, form, template and their kind
 *    are removed with everything inside;
 *  - no attribute survives except `href` on links, which must pass Links::safe();
 *    links get rel="noopener noreferrer";
 *  - comments, processing instructions and CDATA are dropped.
 *
 * (strip_tags() with an allow-list keeps every attribute of the allowed tags,
 * so `<a onmouseover=...>` and `<a href="javascript:...">` would pass.)
 */
final class Html
{
    private const KEEP = ['p', 'br', 'strong', 'em', 'b', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'code', 'pre', 'span', 'hr', 'a'];
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math', 'form', 'template', 'noscript', 'textarea', 'select', 'option', 'button', 'input', 'link', 'meta', 'base', 'title', 'head', 'audio', 'video', 'canvas', 'dialog', 'portal', 'xmp', 'plaintext', 'noembed', 'noframes'];

    /**
     * @param callable(string): ?string|null $href maps a link's address to the one to print, or null to drop it
     */
    public static function sanitize($html, $href = null)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body><div id="root">'.$html.'</div></body>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('root');
        if (!$root instanceof DOMElement) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        $href = $href ?: [Links::class, 'safe'];
        self::clean($root, $href);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * @param callable(string): ?string $href
     */
    private static function clean(DOMNode $parent, $href)
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (!$node instanceof DOMElement) {
                $parent->removeChild($node); // comments, processing instructions, CDATA...

                continue;
            }

            $name = strtolower($node->nodeName);

            if (in_array($name, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            self::clean($node, $href);

            if (!in_array($name, self::KEEP, true)) {
                // Unknown element: keep what is inside it, lose the element.
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $wanted = null;
            if ($name === 'a' && $node->hasAttribute('href')) {
                $wanted = $href(html_entity_decode($node->getAttribute('href'), ENT_QUOTES, 'UTF-8'));
            }

            foreach (iterator_to_array($node->attributes) as $attribute) {
                $node->removeAttribute($attribute->nodeName);
            }

            if ($name === 'a' && is_string($wanted) && $wanted !== '') {
                $node->setAttribute('href', $wanted);
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Utilities\Documents;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Rich text written in the document editor (bold, italic, underline, strike, headings, lists, quotes, alignment,
 * links), cleaned before it is stored or printed: only those tags survive, every attribute is dropped except a safe
 * `text-align` and `href` (http/https/mailto). Scripts, styles, images and event handlers never reach the PDF.
 */
final class RichText
{
    private const TAGS = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'a', 'span', 'div', 'hr', 'sub', 'sup'];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'img', 'svg', 'math', 'form', 'input', 'textarea', 'button', 'select', 'link', 'meta'];

    private const ALIGNMENTS = ['left', 'right', 'center', 'justify'];

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('root');
        if ($root === null) {
            return e(strip_tags($html));
        }
        self::clean($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    /** Text only (search, previews, notifications). */
    public static function toPlain(string $html): string
    {
        $text = (string) preg_replace('/<(br|\/p|\/h[1-4]|\/li|\/div)[^>]*>/i', "\n", $html);

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $node->removeChild($child);
                }

                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }
            self::clean($child);
            if (! in_array($tag, self::TAGS, true)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }
            self::keepSafeAttributes($child, $tag);
        }
    }

    private static function keepSafeAttributes(DOMElement $element, string $tag): void
    {
        $align = self::alignment($element->getAttribute('style'));
        $href = $element->getAttribute('href');
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $element->removeAttribute($attribute->nodeName);
        }
        if ($align !== null) {
            $element->setAttribute('style', 'text-align: '.$align);
        }
        if ($tag === 'a' && preg_match('#^(https?://|mailto:)#i', $href) === 1) {
            $element->setAttribute('href', $href);
        }
    }

    private static function alignment(string $style): ?string
    {
        return preg_match('/text-align\s*:\s*([a-z]+)/i', $style, $m) === 1 && in_array(strtolower($m[1]), self::ALIGNMENTS, true)
            ? strtolower($m[1])
            : null;
    }
}

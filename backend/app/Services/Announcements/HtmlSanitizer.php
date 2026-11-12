<?php

namespace App\Services\Announcements;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Allow-list HTML sanitiser for announcement bodies (no third-party dependency).
 *
 * Kept: p, br, strong, b, em, i, u, ul, ol, li, h3, h4, blockquote and links. Every attribute is
 * dropped except a link's href, which must be http(s) or mailto; links get rel="noopener noreferrer"
 * and target="_blank". script, style, iframe, object, embed, svg, math, template, form… are removed
 * with their content; any other unknown element is unwrapped (its text is kept, escaped). The output
 * is re-serialised by DOMDocument, so malformed or nested markup cannot smuggle a tag through.
 *
 * Bodies are sanitised before they are stored, and only the stored value is ever rendered raw.
 */
class HtmlSanitizer
{
    public const MAX_LENGTH = 50000;

    /**
     * @var list<string>
     */
    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h3', 'h4', 'blockquote', 'a'];

    /**
     * Elements removed together with everything inside them.
     *
     * @var list<string>
     */
    private const DROPPED = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math', 'template',
        'form', 'input', 'button', 'select', 'textarea', 'noscript', 'link', 'meta', 'base', 'title', 'head', 'img',
        'video', 'audio', 'source', 'canvas', 'xml',
    ];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $html = mb_substr($html, 0, self::MAX_LENGTH);

        // Plain text (no markup) becomes paragraphs, line breaks kept.
        if ($html === strip_tags($html)) {
            $html = collect(preg_split('/\R{2,}/u', $html) ?: [])
                ->map(fn (string $paragraph): string => '<p>'.nl2br(e(html_entity_decode(trim($paragraph), ENT_QUOTES | ENT_HTML5, 'UTF-8')), false).'</p>')
                ->implode('');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML declaration makes libxml read the fragment as UTF-8 (Arabic text stays intact).
        $document->loadHTML('<?xml encoding="UTF-8"?><div id="sanitizer-root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;

        foreach ($document->getElementsByTagName('div') as $div) {
            if ($div->getAttribute('id') === 'sanitizer-root') {
                $root = $div;

                break;
            }
        }

        if ($root === null) {
            return '';
        }

        $this->cleanChildren($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Plain-text version (mobile app, notifications, search).
     */
    public function toText(?string $html): string
    {
        $html = (string) $html;
        $html = preg_replace('/<(br|\/p|\/li|\/h3|\/h4|\/blockquote)[^>]*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n\s*\n+/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function cleanChildren(DOMNode $node): void
    {
        // Copy first: the list changes while nodes are removed or unwrapped.
        $children = iterator_to_array($node->childNodes);

        foreach ($children as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // Comments, processing instructions, CDATA.
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::DROPPED, true)) {
                $node->removeChild($child);

                continue;
            }

            $this->cleanChildren($child);

            if (! in_array($tag, self::ALLOWED, true)) {
                $this->unwrap($child);

                continue;
            }

            $this->cleanAttributes($child, $tag);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $href = $tag === 'a' ? trim($element->getAttribute('href')) : null;

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->nodeName);
        }

        if ($tag !== 'a') {
            return;
        }

        if ($href !== null && $this->isSafeUrl($href)) {
            $element->setAttribute('href', $href);
            $element->setAttribute('rel', 'noopener noreferrer');
            $element->setAttribute('target', '_blank');

            return;
        }

        $this->unwrap($element);
    }

    private function isSafeUrl(string $url): bool
    {
        // Control characters and whitespace are used to disguise "javascript:".
        $normalised = strtolower(preg_replace('/[\x00-\x20\x7F]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return (bool) preg_match('#^(https?://|mailto:)#', $normalised) && filter_var(preg_replace('/^mailto:/i', 'mailto://', $url), FILTER_VALIDATE_URL) !== false;
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}

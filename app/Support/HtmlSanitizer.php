<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitizer for rich-text email content written in the Quill editor.
 *
 * Keeps only safe formatting tags, drops dangerous elements together with their
 * content (scripts, iframes, forms…), unwraps any other unknown tag, and removes
 * every attribute except a validated `href` on links and a small set of inline
 * styles (text color, highlight, alignment, indent and the checklist marker).
 * Quill's alignment/indent classes are turned into inline styles so they still
 * render in email clients, which ignore external stylesheets.
 */
class HtmlSanitizer
{
    /**
     * Tags that are kept, mapped to the inline style properties each may carry.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TAGS = [
        'p' => ['text-align', 'padding-left'],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'h1' => ['text-align', 'padding-left'],
        'h2' => ['text-align', 'padding-left'],
        'h3' => ['text-align', 'padding-left'],
        'ul' => ['list-style-type', 'padding-left'],
        'ol' => ['padding-left'],
        'li' => ['text-align', 'padding-left'],
        'blockquote' => ['text-align', 'padding-left'],
        'pre' => [],
        'code' => [],
        'a' => [],
        'span' => ['color', 'background-color'],
    ];

    /**
     * Legacy or equivalent tags that are renamed to an allowed tag.
     *
     * @var array<string, string>
     */
    private const RENAMED_TAGS = [
        'b' => 'strong',
        'i' => 'em',
        'strike' => 's',
        'del' => 's',
        'div' => 'p',
    ];

    /**
     * Tags removed together with everything inside them.
     *
     * @var list<string>
     */
    private const DROPPED_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'option', 'textarea', 'svg', 'math',
        'template', 'noscript', 'head', 'title', 'meta', 'link', 'base', 'video', 'audio',
        'canvas', 'img', 'picture', 'source',
    ];

    /** @var list<string> */
    private const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="tdi-sanitizer-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('tdi-sanitizer-root');

        if (! $root) {
            return '';
        }

        $this->cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        // Copy first: the live NodeList shifts while nodes are removed or unwrapped.
        foreach (iterator_to_array($parent->childNodes) as $child) {
            $this->cleanNode($child);
        }
    }

    private function cleanNode(DOMNode $node): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return;
        }

        if (! $node instanceof DOMElement) {
            // Comments, processing instructions, CDATA, etc.
            $node->parentNode?->removeChild($node);

            return;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::DROPPED_TAGS, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (isset(self::RENAMED_TAGS[$tag])) {
            $node = $this->rename($node, self::RENAMED_TAGS[$tag]);
            $tag = self::RENAMED_TAGS[$tag];
        }

        $this->cleanChildren($node);

        if (! array_key_exists($tag, self::ALLOWED_TAGS)) {
            $this->unwrap($node);

            return;
        }

        $this->cleanAttributes($node, $tag);
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $href = $element->getAttribute('href');
        $styles = $this->allowedStyles($element, self::ALLOWED_TAGS[$tag]);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->nodeName);
        }

        if ($styles !== []) {
            $element->setAttribute('style', implode('; ', array_map(
                fn (string $property, string $value): string => "{$property}: {$value}",
                array_keys($styles),
                $styles,
            )));
        }

        if ($tag === 'a') {
            if ($this->isSafeUrl($href)) {
                $element->setAttribute('href', trim($href));
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer');
            } else {
                $this->unwrap($element);
            }
        }
    }

    /**
     * Collect the inline styles this tag may keep, including those derived from Quill's classes.
     *
     * @param  list<string>  $allowedProperties
     * @return array<string, string>
     */
    private function allowedStyles(DOMElement $element, array $allowedProperties): array
    {
        if ($allowedProperties === []) {
            return [];
        }

        $styles = [];

        foreach (explode(';', $element->getAttribute('style')) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);

            if (in_array($property, $allowedProperties, true) && $this->isSafeStyleValue($property, $value)) {
                $styles[$property] = strtolower($value);
            }
        }

        $classes = preg_split('/\s+/', $element->getAttribute('class')) ?: [];

        foreach ($classes as $class) {
            if (in_array('text-align', $allowedProperties, true) && preg_match('/^ql-align-(left|center|right|justify)$/', $class, $match)) {
                $styles['text-align'] = $match[1];
            }

            if (in_array('padding-left', $allowedProperties, true) && preg_match('/^ql-indent-([1-8])$/', $class, $match)) {
                $styles['padding-left'] = ((int) $match[1] * 3).'em';
            }
        }

        return $styles;
    }

    private function isSafeStyleValue(string $property, string $value): bool
    {
        return match ($property) {
            'color', 'background-color' => (bool) preg_match(
                '/^(#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}%?\s*,\s*\d{1,3}%?\s*,\s*\d{1,3}%?\s*(,\s*(0|1|0?\.\d+)\s*)?\)|[a-z]{3,20})$/i',
                $value,
            ),
            'text-align' => in_array(strtolower($value), ['left', 'center', 'right', 'justify'], true),
            'padding-left' => (bool) preg_match('/^\d{1,2}(\.\d{1,2})?(em|px)$/i', $value),
            'list-style-type' => strtolower($value) === 'none',
            default => false,
        };
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), self::ALLOWED_LINK_SCHEMES, true);
    }

    private function rename(DOMElement $element, string $tag): DOMElement
    {
        $renamed = $element->ownerDocument->createElement($tag);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $renamed->setAttribute($attribute->nodeName, $attribute->nodeValue ?? '');
        }

        while ($element->firstChild) {
            $renamed->appendChild($element->firstChild);
        }

        $element->parentNode?->replaceChild($renamed, $element);

        return $renamed;
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}

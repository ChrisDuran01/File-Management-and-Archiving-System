<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Strict allow-list HTML sanitizer for user-submitted rich text (announcement
 * bodies) that gets rendered unescaped on the public student dashboard - the
 * rich text editor only *encourages* safe markup, it's not a security
 * boundary by itself, since a request can always be crafted by hand to skip
 * it entirely. This is the actual boundary.
 *
 * No external dependency (a proper library like HTMLPurifier would be
 * preferable, but installing one needs Packagist access, which wasn't
 * reliable when this was built) - walks the DOM and rebuilds it keeping only
 * an explicit set of formatting tags/attributes. Everything else is either
 * unwrapped (tag dropped, text/children kept) or, for <script>/<style>,
 * removed entirely including its content.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'a', 'blockquote', 'h1', 'h2', 'h3',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href'],
    ];

    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto'];

    public function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument();

        // Wrap in a container so DOMDocument doesn't need a full <html>
        // <body> structure, and force UTF-8 via the XML prolog (otherwise
        // DOMDocument guesses the encoding from a <meta> tag that isn't
        // there and can mangle multi-byte text).
        $loaded = @$dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="__root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if (! $loaded) {
            return '';
        }

        $root = $dom->getElementById('__root');

        if (! $root) {
            return '';
        }

        $this->sanitizeChildren($dom, $root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    private function sanitizeChildren(DOMDocument $dom, DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            // Actively dangerous or pointless here - drop the whole
            // subtree, not just the tag, so the script/style text itself
            // never ends up as visible page content either.
            if (in_array($tag, ['script', 'style'], true)) {
                $node->removeChild($child);
                continue;
            }

            // Recurse first so nested disallowed tags are cleaned
            // regardless of whether this tag itself survives.
            $this->sanitizeChildren($dom, $child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap: hoist the (already-sanitized) children up to
                // replace this tag, instead of dropping the content too.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $this->stripDisallowedAttributes($child, $tag);
        }
    }

    private function stripDisallowedAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes ?? []) as $attr) {
            if (! in_array($attr->name, $allowed, true)) {
                $element->removeAttribute($attr->name);
            }
        }

        if ($tag !== 'a' || ! $element->hasAttribute('href')) {
            return;
        }

        $href = trim($element->getAttribute('href'));
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        // Only a recognized safe scheme keeps its link - this is what
        // blocks javascript:, data:, vbscript:, etc., and anything
        // malformed enough that parse_url can't identify a scheme at all.
        if (in_array($scheme, self::ALLOWED_URL_SCHEMES, true)) {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
            $element->setAttribute('target', '_blank');
        } else {
            $element->removeAttribute('href');
        }
    }
}

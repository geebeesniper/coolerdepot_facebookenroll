<?php
namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlNoteSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'blockquote', 'a', 'h3', 'h4'
    ];

    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'target', 'rel'],
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string)$html);

        if ($html === '') {
            return '';
        }

        libxml_use_internal_errors(true);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!doctype html><html><body><div id="cdsp-note-root">'
            . $html
            . '</div></body></html>';

        $doc->loadHTML(
            mb_convert_encoding($wrapped, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $root = $doc->getElementById('cdsp-note-root');

        if (!$root) {
            libxml_clear_errors();
            return '';
        }

        self::sanitizeChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        libxml_clear_errors();

        return trim($out);
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($child);
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            /** @var DOMElement $child */
            $tag = strtolower($child->tagName);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Keep textual/allowed child content but remove the disallowed wrapper.
                self::sanitizeChildren($child);

                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }

                $parent->removeChild($child);
                continue;
            }

            $allowedAttrs = self::ALLOWED_ATTRS[$tag] ?? [];
            $attrsToRemove = [];

            foreach ($child->attributes as $attr) {
                $name = strtolower($attr->name);

                if (!in_array($name, $allowedAttrs, true)) {
                    $attrsToRemove[] = $name;
                    continue;
                }

                if ($tag === 'a' && $name === 'href') {
                    $href = trim($attr->value);

                    if (!preg_match('~^(https?://|mailto:|/|#)~i', $href)) {
                        $attrsToRemove[] = $name;
                    }
                }
            }

            foreach ($attrsToRemove as $name) {
                $child->removeAttribute($name);
            }

            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer');
                if ($child->hasAttribute('target')) {
                    $child->setAttribute('target', '_blank');
                }
            }

            self::sanitizeChildren($child);
        }
    }
}

<?php

namespace App\Cms\Media;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Makes an uploaded SVG safe to serve.
 *
 * SVG is an image format that can carry script. Opened directly from the
 * uploads folder it runs with this site's cookies, which makes an unchecked
 * SVG upload a stored XSS. This strips everything that can execute or reach
 * outside the file, and rewrites the file in place.
 */
class SvgSanitizer
{
    /** Elements removed outright, with everything inside them. */
    private const REMOVE_ELEMENTS = [
        'script', 'foreignobject', 'iframe', 'embed', 'object', 'handler',
        'listener', 'set', 'animate', 'animatemotion', 'animatetransform',
        'animatecolor', 'discard',
    ];

    public function cleanFile(string $path): void
    {
        $contents = (string) file_get_contents($path);

        file_put_contents($path, $this->clean($contents));
    }

    /**
     * @throws UploadRejected when the file cannot be made safe.
     */
    public function clean(string $svg): string
    {
        // Entity declarations enable XML bombs and external entity reads, and
        // no ordinary icon or illustration needs them.
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $svg)) {
            throw new UploadRejected('This SVG uses document type declarations, which are not allowed.');
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);

        // LIBXML_NONET: never fetch anything over the network while parsing.
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();

        if (! $loaded || ! $document->documentElement
            || strtolower($document->documentElement->localName) !== 'svg') {
            throw new UploadRejected('This does not look like a valid SVG image.');
        }

        $xpath = new DOMXPath($document);

        foreach (iterator_to_array($xpath->query('//*')) as $element) {
            /** @var DOMElement $element */
            if (in_array(strtolower($element->localName), self::REMOVE_ELEMENTS, true)) {
                $element->parentNode?->removeChild($element);

                continue;
            }

            $this->cleanAttributes($element);

            // Inline stylesheets can import remote CSS or reference script.
            if (strtolower($element->localName) === 'style') {
                $element->textContent = $this->cleanCss($element->textContent);
            }
        }

        // Processing instructions other than the XML declaration have no use
        // in an image and can carry markup.
        foreach (iterator_to_array($xpath->query('//processing-instruction()')) as $instruction) {
            $instruction->parentNode?->removeChild($instruction);
        }

        return $document->saveXML($document->documentElement);
    }

    private function cleanAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = trim((string) $attribute->nodeValue);

            // Every event handler.
            if (str_starts_with($name, 'on')) {
                $element->removeAttributeNode($attribute);

                continue;
            }

            // Links may only point inside the file, or at an embedded image.
            if ($name === 'href' || str_ends_with($name, ':href') || $name === 'src') {
                $normalised = strtolower(preg_replace('/\s+/', '', $value));

                $internal = str_starts_with($normalised, '#');
                $embedded = (bool) preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#', $normalised);

                if (! $internal && ! $embedded) {
                    $element->removeAttributeNode($attribute);
                }

                continue;
            }

            if ($name === 'style') {
                $element->setAttribute('style', $this->cleanCss($value));
            }
        }
    }

    private function cleanCss(string $css): string
    {
        $css = preg_replace('/@import[^;]*;?/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);

        // url() may only reference something inside the file.
        return preg_replace_callback('/url\s*\(([^)]*)\)/i', function ($match) {
            $target = trim($match[1], " \t\n\r\"'");

            return str_starts_with($target, '#') ? $match[0] : 'none';
        }, $css);
    }
}

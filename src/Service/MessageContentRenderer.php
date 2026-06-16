<?php

namespace App\Service;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class MessageContentRenderer
{
    public function __construct(private readonly HtmlSanitizerInterface $message)
    {
    }

    public function render(string $content): string
    {
        $sanitizedContent = trim($this->message->sanitize($content));
        if ($sanitizedContent === '') {
            return '';
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            sprintf('<div>%s</div>', $sanitizedContent),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $wrapper = $document->documentElement;
        if (!$wrapper instanceof \DOMElement) {
            return $sanitizedContent;
        }

        $this->linkifyNode($wrapper, $document);

        return $this->innerHtml($wrapper);
    }

    private function linkifyNode(\DOMNode $node, \DOMDocument $document): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                $this->replaceTextNodeWithLinks($child, $document);
                continue;
            }

            if ($child instanceof \DOMElement && $child->tagName !== 'a') {
                $this->linkifyNode($child, $document);
            }
        }
    }

    private function replaceTextNodeWithLinks(\DOMText $textNode, \DOMDocument $document): void
    {
        $text = $textNode->wholeText;
        if (!preg_match('/((?:https?:\/\/|www\.)[^\s<]+)/iu', $text)) {
            return;
        }

        $fragment = $document->createDocumentFragment();
        $offset = 0;
        preg_match_all('/((?:https?:\/\/|www\.)[^\s<]+)/iu', $text, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$rawMatch, $position]) {
            $trailingPunctuation = '';
            $urlText = $rawMatch;

            while ($urlText !== '' && preg_match('/[.,!?;:)\]]$/', $urlText) === 1) {
                $trailingPunctuation = substr($urlText, -1) . $trailingPunctuation;
                $urlText = substr($urlText, 0, -1);
            }

            if ($position > $offset) {
                $fragment->appendChild($document->createTextNode(substr($text, $offset, $position - $offset)));
            }

            $href = str_starts_with(strtolower($urlText), 'www.') ? 'https://' . $urlText : $urlText;

            $anchor = $document->createElement('a');
            $anchor->setAttribute('href', $href);
            $anchor->setAttribute('target', '_blank');
            $anchor->setAttribute('rel', 'noopener noreferrer');
            $anchor->appendChild($document->createTextNode($urlText));
            $fragment->appendChild($anchor);

            if ($trailingPunctuation !== '') {
                $fragment->appendChild($document->createTextNode($trailingPunctuation));
            }

            $offset = $position + strlen($rawMatch);
        }

        if ($offset < strlen($text)) {
            $fragment->appendChild($document->createTextNode(substr($text, $offset)));
        }

        $textNode->parentNode?->replaceChild($fragment, $textNode);
    }

    private function innerHtml(\DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $childNode) {
            $html .= $element->ownerDocument?->saveHTML($childNode) ?? '';
        }

        return $html;
    }
}

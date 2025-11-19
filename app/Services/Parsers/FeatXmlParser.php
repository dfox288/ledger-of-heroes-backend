<?php

namespace App\Services\Parsers;

use App\Services\Parsers\Concerns\ParsesSourceCitations;
use SimpleXMLElement;

class FeatXmlParser
{
    use ParsesSourceCitations;

    /**
     * Parse feats from XML string.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $xml): array
    {
        $compendium = simplexml_load_string($xml);

        if ($compendium === false || ! isset($compendium->feat)) {
            return [];
        }

        $feats = [];

        foreach ($compendium->feat as $element) {
            $feats[] = $this->parseFeat($element);
        }

        return $feats;
    }

    /**
     * Parse a single feat element.
     *
     * @return array<string, mixed>
     */
    private function parseFeat(SimpleXMLElement $element): array
    {
        // Get raw text
        $text = (string) $element->text;

        // Extract source citations from text
        $sources = $this->parseSourceCitations($text);

        // Remove source citations from description
        $description = $this->stripSourceCitations($text);

        return [
            'name' => (string) $element->name,
            'prerequisites' => isset($element->prerequisite) ? (string) $element->prerequisite : null,
            'description' => trim($description),
            'sources' => $sources,
        ];
    }

    /**
     * Remove source citations from text.
     */
    private function stripSourceCitations(string $text): string
    {
        // Remove everything after "Source:" (including the Source: line)
        $cleaned = preg_replace('/\n*Source:\s*.+$/ims', '', $text);

        return trim($cleaned ?? $text);
    }
}

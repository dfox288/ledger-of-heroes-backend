<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

/**
 * Parser for source XML files.
 *
 * Source files have a simple structure with one source per file:
 * - name, abbreviation (code), url, author, artist
 * - publisher, website, category, pubdate, description
 * - collection (list of related documents - informational only)
 */
class SourceXmlParser
{
    /**
     * Parse a source from XML string.
     *
     * Unlike other parsers that return arrays of entities,
     * this returns a single-element array since each file
     * contains only one source.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $xml): array
    {
        // Use tryFromString to gracefully handle invalid XML
        $element = XmlLoader::tryFromString($xml);

        if ($element === null || $element->getName() !== 'source') {
            return [];
        }

        return [$this->parseSource($element)];
    }

    /**
     * Parse a single source element.
     *
     * @return array<string, mixed>
     */
    private function parseSource(SimpleXMLElement $element): array
    {
        // Extract publication year from pubdate (format: 2014-08-19)
        $pubdate = (string) $element->pubdate;
        $publicationYear = null;

        if (! empty($pubdate)) {
            $parts = explode('-', $pubdate);
            if (count($parts) >= 1 && is_numeric($parts[0])) {
                $publicationYear = (int) $parts[0];
            }
        }

        return [
            'name' => trim((string) $element->name),
            'code' => trim((string) $element->abbreviation),
            'url' => $this->nullIfEmpty((string) $element->url),
            'author' => $this->nullIfEmpty((string) $element->author),
            'artist' => $this->nullIfEmpty((string) $element->artist),
            'publisher' => $this->nullIfEmpty((string) $element->publisher) ?? $this->getDefaultPublisher(),
            'website' => $this->nullIfEmpty((string) $element->website),
            'category' => $this->nullIfEmpty((string) $element->category),
            'publication_year' => $publicationYear,
            'description' => $this->nullIfEmpty((string) $element->description),
        ];
    }

    /**
     * Return null for empty strings.
     */
    private function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Get the default publisher from config, with fallback for unit tests.
     */
    private function getDefaultPublisher(): string
    {
        if (function_exists('config') && app()->bound('config')) {
            return config('import.default_publisher', 'Wizards of the Coast');
        }

        return 'Wizards of the Coast';
    }
}

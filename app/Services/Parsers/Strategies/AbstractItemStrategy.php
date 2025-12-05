<?php

namespace App\Services\Parsers\Strategies;

use App\Services\Concerns\TracksMetricsAndWarnings;
use App\Services\Parsers\Concerns\LookupsGameEntities;

abstract class AbstractItemStrategy implements ItemTypeStrategy
{
    use LookupsGameEntities;
    use TracksMetricsAndWarnings;

    /**
     * Default implementation: no modifier enhancements.
     */
    public function enhanceModifiers(array $modifiers, array $baseData, \SimpleXMLElement $xml): array
    {
        return $modifiers;
    }

    /**
     * Default implementation: no ability enhancements.
     */
    public function enhanceAbilities(array $abilities, array $baseData, \SimpleXMLElement $xml): array
    {
        return $abilities;
    }

    /**
     * Default implementation: no relationship enhancements.
     */
    public function enhanceRelationships(array $baseData, \SimpleXMLElement $xml): array
    {
        return [];
    }

    /**
     * Extract metadata collected during parsing.
     */
    public function extractMetadata(): array
    {
        return [
            'warnings' => $this->warnings,
            'metrics' => $this->metrics,
        ];
    }
}

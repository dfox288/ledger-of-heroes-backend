<?php

namespace App\Services\Parsers\Strategies;

use App\Services\Parsers\Concerns\FindsInDescription;
use SimpleXMLElement;

/**
 * Strategy for parsing legendary and artifact items.
 *
 * Extracts sentience, alignment, and personality traits.
 */
class LegendaryStrategy extends AbstractItemStrategy
{
    use FindsInDescription;

    /**
     * Applies to legendary and artifact items.
     */
    public function appliesTo(array $baseData, SimpleXMLElement $xml): bool
    {
        $rarity = $baseData['rarity'] ?? '';

        return in_array(strtolower($rarity), ['legendary', 'artifact']);
    }

    /**
     * Enhance modifiers with legendary item metadata.
     *
     * Tracks sentience, alignment, and special properties.
     */
    public function enhanceModifiers(array $modifiers, array $baseData, SimpleXMLElement $xml): array
    {
        $description = $baseData['description'] ?? '';
        $detail = $baseData['detail'] ?? '';

        // Check for sentience
        if ($this->isSentient($description)) {
            $this->setMetric('is_sentient', true);
            $this->incrementMetric('sentient_items');

            // Extract alignment if mentioned
            $alignment = $this->extractAlignment($description, $detail);
            if ($alignment) {
                $this->setMetric('alignment', $alignment);
            }

            // Extract personality traits
            $personality = $this->extractPersonalityTraits($description);
            if (! empty($personality)) {
                $this->setMetric('personality_traits', $personality);
            }
        }

        // Check for artifact-specific features
        if (strtolower($baseData['rarity'] ?? '') === 'artifact') {
            $this->incrementMetric('artifacts');

            // Artifacts often have unique destruction methods
            if (str_contains(strtolower($description), 'destroy')) {
                $this->setMetric('has_destruction_method', true);
            }
        } else {
            $this->incrementMetric('legendary_items');
        }

        return $modifiers;
    }

    /**
     * Check if the item is sentient.
     */
    private function isSentient(string $description): bool
    {
        $sentientIndicators = [
            'sentient',
            'intelligence score',
            'wisdom score',
            'charisma score',
            'telepathy',
            'speaks',
            'communicates',
        ];

        return $this->hasAnyKeyword($description, $sentientIndicators);
    }

    /**
     * Extract alignment from description or detail field.
     *
     * Common alignments: lawful good, neutral evil, chaotic neutral, etc.
     */
    private function extractAlignment(string $description, string $detail): ?string
    {
        $alignments = [
            'lawful good', 'lawful neutral', 'lawful evil',
            'neutral good', 'true neutral', 'neutral evil',
            'chaotic good', 'chaotic neutral', 'chaotic evil',
            'unaligned',
        ];

        return $this->findFirstKeyword($description.' '.$detail, $alignments);
    }

    /**
     * Extract personality traits mentioned in description.
     */
    private function extractPersonalityTraits(string $description): array
    {
        $descriptors = [
            'arrogant', 'kind', 'cruel', 'benevolent', 'malevolent',
            'proud', 'humble', 'greedy', 'generous', 'wrathful',
            'patient', 'impulsive', 'cunning', 'straightforward',
        ];

        return $this->findAllKeywords($description, $descriptors);
    }
}

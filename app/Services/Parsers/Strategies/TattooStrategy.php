<?php

namespace App\Services\Parsers\Strategies;

use SimpleXMLElement;

/**
 * Strategy for parsing magic tattoos (wondrous items).
 *
 * Extracts body location and activation methods for tattoos.
 */
class TattooStrategy extends AbstractItemStrategy
{
    /**
     * Applies to wondrous items with "tattoo" in the name.
     */
    public function appliesTo(array $baseData, SimpleXMLElement $xml): bool
    {
        $typeCode = $baseData['type_code'] ?? '';
        $name = $baseData['name'] ?? '';

        return $typeCode === 'W' && str_contains(strtolower($name), 'tattoo');
    }

    /**
     * Enhance modifiers with tattoo-specific metadata.
     *
     * Tracks body location and activation methods.
     */
    public function enhanceModifiers(array $modifiers, array $baseData, SimpleXMLElement $xml): array
    {
        $description = $baseData['description'] ?? '';
        $name = $baseData['name'] ?? '';

        // Extract body location if mentioned
        $location = $this->extractBodyLocation($description);
        if ($location) {
            $this->setMetric('body_location', $location);
        }

        // Check for activation methods
        $activationMethods = $this->extractActivationMethods($description);
        $this->setMetric('activation_methods', $activationMethods);

        // Track tattoo type from name
        $tattooType = $this->extractTattooType($name);
        if ($tattooType) {
            $this->setMetric('tattoo_type', $tattooType);
            $this->incrementMetric("type_{$tattooType}");
        }

        return $modifiers;
    }

    /**
     * Extract body location from description.
     *
     * Common locations: arm, chest, back, head, hand, etc.
     */
    private function extractBodyLocation(string $description): ?string
    {
        $locations = ['arm', 'chest', 'back', 'head', 'hand', 'leg', 'torso', 'shoulder', 'neck'];

        foreach ($locations as $location) {
            if (str_contains(strtolower($description), $location)) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Extract activation methods from description.
     *
     * Common methods: action, bonus action, reaction, passive
     */
    private function extractActivationMethods(string $description): array
    {
        $methods = [];
        $descLower = strtolower($description);

        if (preg_match('/\b(?:use|using|take|as)\s+(?:an?\s+)?action\b/i', $description)) {
            $methods[] = 'action';
        }

        if (str_contains($descLower, 'bonus action')) {
            $methods[] = 'bonus_action';
        }

        if (str_contains($descLower, 'reaction')) {
            $methods[] = 'reaction';
        }

        // If no explicit activation mentioned, it might be passive
        if (empty($methods) && (str_contains($descLower, 'while') || str_contains($descLower, 'whenever'))) {
            $methods[] = 'passive';
        }

        return $methods;
    }

    /**
     * Extract tattoo type from name.
     *
     * Examples: Absorbing Tattoo, Illuminator's Tattoo, Masquerade Tattoo
     */
    private function extractTattooType(string $name): ?string
    {
        // Extract the prefix before "Tattoo"
        if (preg_match('/^(.*?)\s+Tattoo$/i', $name, $matches)) {
            $type = trim($matches[1]);

            return strtolower(str_replace(' ', '_', $type));
        }

        return null;
    }
}

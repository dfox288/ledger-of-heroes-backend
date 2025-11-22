<?php

namespace App\Services\Parsers\Strategies;

use App\Models\Spell;
use SimpleXMLElement;

/**
 * Strategy for parsing charged items (staves, wands, rods).
 *
 * Extracts spell references and charge costs from item descriptions.
 */
class ChargedItemStrategy extends AbstractItemStrategy
{
    /**
     * Applies to magic items with charge mechanics (staves, wands, rods, wondrous items).
     * Also applies to items that mention spells with charge costs, even if not explicitly magic.
     */
    public function appliesTo(array $baseData, SimpleXMLElement $xml): bool
    {
        $typeCode = $baseData['type_code'] ?? '';
        $isMagic = $baseData['is_magic'] ?? false;
        $hasCharges = ! empty($baseData['charges_max']);
        $description = $baseData['description'] ?? '';

        // Check if description mentions spell casting with charges
        // Matches: "cast SPELL (X charge)" or "cast SPELL (X charge per spell level, up to..."
        $mentionsSpellCharges = preg_match('/cast\s+[a-z\s\']+\s*\(\d+\s+charge/i', $description);

        // Apply to:
        // 1. Magic staves/wands/rods
        // 2. Any magic item with charges
        // 3. Any item that mentions casting spells with charge costs
        return ($isMagic && in_array($typeCode, ['ST', 'WD', 'RD']))
            || ($isMagic && $hasCharges)
            || $mentionsSpellCharges;
    }

    /**
     * Extract spell references and their charge costs from the description.
     */
    public function enhanceRelationships(array $baseData, SimpleXMLElement $xml): array
    {
        $description = $baseData['description'] ?? '';

        if (empty($description)) {
            return [];
        }

        $spells = $this->extractSpells($description);

        if (! empty($spells)) {
            $this->setMetric('spell_references_found', count($spells));
        }

        return ['spell_references' => $spells];
    }

    /**
     * Extract spell names and charge costs from description text.
     *
     * Handles patterns like:
     * - "cure wounds (1 charge)"
     * - "fireball (3 charges)"
     * - "cure wounds (1 charge per spell level, up to 4th)"
     * - "cast cure wounds (1 charge)"
     *
     * @return array<int, array{name: string, spell_id: ?int, charges_cost_min: ?int, charges_cost_max: ?int, charges_cost_formula: ?string}>
     */
    private function extractSpells(string $description): array
    {
        $spells = [];

        // Pattern strategy: Look for common spell-casting patterns followed by spell name and charges
        // Pattern 1: "cast SPELL_NAME (X charges)"
        // Pattern 2: "following spells: SPELL_NAME (X charges)"
        // Use a more precise pattern that stops at punctuation or "and/or"
        $pattern = '/(?:cast|following spells[^:]*:|or)\s+([a-z][a-z\s\']*?)\s*\((\d+)\s+charges?(?:\s+per\s+spell\s+level,?\s+up\s+to\s+(\d+)(?:st|nd|rd|th))?\)/i';

        preg_match_all($pattern, $description, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $spellName = $this->normalizeSpellName($match[1]);
            $chargesMin = (int) $match[2];
            $chargesMax = isset($match[3]) ? (int) $match[3] : $chargesMin;
            $formula = isset($match[3]) ? trim($match[2].' per spell level') : null;

            // Try to find the spell in the database
            $spell = $this->findSpell($spellName);

            if ($spell) {
                $this->incrementMetric('spells_matched');
            } else {
                $this->incrementMetric('spells_not_found');
                $this->addWarning("Spell not found in database: {$spellName}");
            }

            $spells[] = [
                'name' => $spellName,
                'spell_id' => $spell?->id,
                'charges_cost_min' => $chargesMin,
                'charges_cost_max' => $chargesMax,
                'charges_cost_formula' => $formula,
            ];
        }

        return $spells;
    }

    /**
     * Normalize spell name to title case for database matching.
     */
    private function normalizeSpellName(string $name): string
    {
        $name = trim($name);

        // Title case the spell name
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Find spell by name (case-insensitive).
     */
    private function findSpell(string $name): ?Spell
    {
        return Spell::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }
}

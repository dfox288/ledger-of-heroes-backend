<?php

declare(strict_types=1);

namespace App\Services\ClassSubclassAudit;

use Illuminate\Support\Facades\Storage;

/**
 * Generates console and JSON reports from subclass inventory data.
 */
class InventoryReportGenerator
{
    /**
     * Generate console output lines for the inventory.
     *
     * @return array<string>
     */
    public function generateConsoleOutput(array $inventory, bool $detailed = false): array
    {
        $lines = [];
        $lines[] = 'Class/Subclass Inventory Report';
        $lines[] = '===============================';
        $lines[] = '';
        $lines[] = "Generated: {$inventory['generated_at']}";
        $lines[] = '';

        foreach ($inventory['classes'] as $classSlug => $classData) {
            $lines[] = $this->formatBaseClass($classSlug, $classData);

            foreach ($classData['subclasses'] as $subSlug => $subData) {
                $lines = array_merge($lines, $this->formatSubclass($subData, $detailed));
            }

            $lines[] = '';
        }

        $lines[] = 'Summary';
        $lines[] = '-------';
        $lines[] = "Base classes: {$inventory['summary']['base_classes']}";
        $lines[] = "Subclasses: {$inventory['summary']['subclasses']}";
        $lines[] = "Issues flagged: {$inventory['summary']['issues_flagged']}";

        return $lines;
    }

    /**
     * Format a base class header line.
     */
    private function formatBaseClass(string $slug, array $data): string
    {
        $caster = $data['is_spellcaster'] ? ' [spellcaster]' : '';
        $subLevel = $data['subclass_level'] ? " (subclass at L{$data['subclass_level']})" : '';

        return "{$data['name']} ({$data['subclass_count']} subclasses){$subLevel}{$caster}";
    }

    /**
     * Format a subclass with tree-style output.
     *
     * @return array<string>
     */
    private function formatSubclass(array $data, bool $detailed): array
    {
        $lines = [];
        $prefix = '├── ';
        $childPrefix = '│   ├── ';
        $lastChildPrefix = '│   └── ';

        $lines[] = "{$prefix}{$data['name']}";

        // Features
        $featureLevels = implode(',', $data['features']['levels']);
        $featureCount = $data['features']['count'];
        $lines[] = "{$childPrefix}Features: {$featureCount}".($featureLevels ? " (at levels {$featureLevels})" : '');

        if ($detailed && $data['features']['count'] > 0) {
            foreach ($data['features']['items'] as $item) {
                $optional = $item['is_optional'] ? ' [optional]' : '';
                $lines[] = "│   │   • L{$item['level']}: {$item['name']}{$optional}";
            }
        }

        // Proficiencies
        $profCount = $data['proficiencies']['count'];
        $featureProfCount = count($data['proficiencies']['from_features']);
        $profSummary = $profCount > 0 ? implode(', ', array_keys($data['proficiencies']['by_type'])) : 'none';
        $lines[] = "{$childPrefix}Proficiencies: {$profCount} direct, {$featureProfCount} from features";

        if ($detailed && ($profCount > 0 || $featureProfCount > 0)) {
            foreach ($data['proficiencies']['items'] as $item) {
                $lines[] = "│   │   • {$item['name']} ({$item['type']})";
            }
            foreach ($data['proficiencies']['from_features'] as $item) {
                $lines[] = "│   │   • {$item['name']} ({$item['type']}) via {$item['from_feature']}";
            }
        }

        // Bonus Spells
        $spellCount = $data['bonus_spells']['count'];
        $alwaysPrepared = $data['bonus_spells']['always_prepared'] ? ' [always prepared]' : '';
        $spellWarning = '';

        // Flag if spellcaster subclass has no bonus spells (potential issue)
        if ($spellCount === 0 && ! empty($data['bonus_spells']['features_with_spells'])) {
            $spellWarning = ' ⚠️ Feature exists but no spells linked';
        }

        $lines[] = "{$childPrefix}Bonus Spells: {$spellCount}{$alwaysPrepared}{$spellWarning}";

        if ($detailed && $spellCount > 0) {
            foreach ($data['bonus_spells']['by_level'] as $level => $spells) {
                $spellNames = implode(', ', array_column($spells, 'name'));
                $lines[] = "│   │   • L{$level}: {$spellNames}";
            }
        }

        // Counters
        $counterNames = array_keys($data['counters']);
        if (empty($counterNames)) {
            $lines[] = "{$lastChildPrefix}Counters: none";
        } else {
            $counterSummary = [];
            foreach ($data['counters'] as $name => $levels) {
                $min = min($levels);
                $max = max($levels);
                $counterSummary[] = "{$name} ({$min}→{$max})";
            }
            $lines[] = "{$lastChildPrefix}Counters: ".implode(', ', $counterSummary);
        }

        return $lines;
    }

    /**
     * Save inventory as JSON file.
     *
     * @return string Path to saved file
     */
    public function saveJson(array $inventory): string
    {
        $filename = 'class-subclass-inventory-'.now()->format('Y-m-d-His').'.json';
        $path = "reports/{$filename}";

        Storage::put($path, json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Storage::path($path);
    }

    /**
     * Generate issues summary for quick triage.
     *
     * @return array<array{subclass: string, issue: string, severity: string}>
     */
    public function findIssues(array $inventory): array
    {
        $issues = [];

        foreach ($inventory['classes'] as $classSlug => $classData) {
            foreach ($classData['subclasses'] as $subSlug => $subData) {
                // No features at all
                if ($subData['features']['count'] === 0) {
                    $issues[] = [
                        'subclass' => $subSlug,
                        'issue' => 'No features found',
                        'severity' => 'high',
                    ];
                }

                // Check for spell-granting features with no linked spells
                foreach ($subData['features']['items'] as $feature) {
                    if ($this->isSpellGrantingFeature($feature['name']) && $subData['bonus_spells']['count'] === 0) {
                        $issues[] = [
                            'subclass' => $subSlug,
                            'issue' => "Feature '{$feature['name']}' should grant spells but none linked",
                            'severity' => 'high',
                        ];
                        break;
                    }
                }

                // Validate spell progression for subclasses that should have always-prepared spells
                $spellIssues = $this->validateSpellProgression($subSlug, $subData, $classSlug);
                $issues = array_merge($issues, $spellIssues);
            }
        }

        return $issues;
    }

    /**
     * Validate spell progression for subclasses with always-prepared spells.
     *
     * D&D 5e pattern for domain/oath/circle/artificer spells:
     * - Tiers: 3, 5, 9, 13, 17 (class levels where spells are granted)
     * - Each tier grants 2 spells
     * - Spell levels correlate to tier: L3=1st, L5=2nd, L9=3rd, L13=4th, L17=5th
     *
     * @return array<array{subclass: string, issue: string, severity: string}>
     */
    private function validateSpellProgression(string $subSlug, array $subData, string $classSlug): array
    {
        $issues = [];

        // Only validate subclasses that should have always-prepared spells
        if (! $this->shouldHaveBonusSpells($subSlug, $subData)) {
            return $issues;
        }

        // If no spells at all, that's already caught by other checks
        if ($subData['bonus_spells']['count'] === 0) {
            $issues[] = [
                'subclass' => $subSlug,
                'issue' => 'Expected bonus spells but none found',
                'severity' => 'high',
            ];

            return $issues;
        }

        // Expected tier structure
        $expectedTiers = $this->getExpectedSpellTiers($classSlug);
        $actualSpells = $subData['bonus_spells']['by_level'];

        // Check each expected tier
        foreach ($expectedTiers as $tier => $expected) {
            $actualAtTier = $actualSpells[$tier] ?? [];
            $actualCount = count($actualAtTier);

            // Missing spells at this tier
            if ($actualCount < $expected['count']) {
                $issues[] = [
                    'subclass' => $subSlug,
                    'issue' => "L{$tier}: Expected {$expected['count']} spells, found {$actualCount}",
                    'severity' => 'high',
                ];
            }

            // Extra spells at this tier (unusual but flag it)
            if ($actualCount > $expected['count']) {
                $issues[] = [
                    'subclass' => $subSlug,
                    'issue' => "L{$tier}: Expected {$expected['count']} spells, found {$actualCount} (extra)",
                    'severity' => 'medium',
                ];
            }

            // Validate spell levels match the tier
            foreach ($actualAtTier as $spell) {
                $expectedMaxLevel = $expected['max_spell_level'];
                if ($spell['spell_level'] > $expectedMaxLevel && ! $spell['is_cantrip']) {
                    $issues[] = [
                        'subclass' => $subSlug,
                        'issue' => "L{$tier}: {$spell['name']} is level {$spell['spell_level']}, max expected {$expectedMaxLevel}",
                        'severity' => 'medium',
                    ];
                }
            }
        }

        // Check for spells at unexpected tiers
        foreach (array_keys($actualSpells) as $tier) {
            if (! isset($expectedTiers[$tier])) {
                $count = count($actualSpells[$tier]);
                $issues[] = [
                    'subclass' => $subSlug,
                    'issue' => "Unexpected tier L{$tier} with {$count} spells",
                    'severity' => 'medium',
                ];
            }
        }

        return $issues;
    }

    /**
     * Get expected spell tiers for a class.
     *
     * @return array<int, array{count: int, max_spell_level: int}>
     */
    private function getExpectedSpellTiers(string $classSlug): array
    {
        // Artificer has different tiers (3, 5, 9, 13, 17) but at half-caster progression
        if (str_contains($classSlug, 'artificer')) {
            return [
                3 => ['count' => 2, 'max_spell_level' => 1],
                5 => ['count' => 2, 'max_spell_level' => 2],
                9 => ['count' => 2, 'max_spell_level' => 3],
                13 => ['count' => 2, 'max_spell_level' => 4],
                17 => ['count' => 2, 'max_spell_level' => 5],
            ];
        }

        // Paladin (half-caster, gets spells at 3, 5, 9, 13, 17)
        if (str_contains($classSlug, 'paladin')) {
            return [
                3 => ['count' => 2, 'max_spell_level' => 1],
                5 => ['count' => 2, 'max_spell_level' => 2],
                9 => ['count' => 2, 'max_spell_level' => 3],
                13 => ['count' => 2, 'max_spell_level' => 4],
                17 => ['count' => 2, 'max_spell_level' => 5],
            ];
        }

        // Cleric domains get spells at 1, 3, 5, 7, 9
        if (str_contains($classSlug, 'cleric')) {
            return [
                1 => ['count' => 2, 'max_spell_level' => 1],
                3 => ['count' => 2, 'max_spell_level' => 2],
                5 => ['count' => 2, 'max_spell_level' => 3],
                7 => ['count' => 2, 'max_spell_level' => 4],
                9 => ['count' => 2, 'max_spell_level' => 5],
            ];
        }

        // Druid circles (Land, Spores, Wildfire) get spells at 2, 3, 5, 7, 9
        // Circle of the Land starts at 2 (for terrain), others at 3
        if (str_contains($classSlug, 'druid')) {
            return [
                3 => ['count' => 2, 'max_spell_level' => 2],
                5 => ['count' => 2, 'max_spell_level' => 3],
                7 => ['count' => 2, 'max_spell_level' => 4],
                9 => ['count' => 2, 'max_spell_level' => 5],
            ];
        }

        // Default: full caster progression
        return [
            1 => ['count' => 2, 'max_spell_level' => 1],
            3 => ['count' => 2, 'max_spell_level' => 2],
            5 => ['count' => 2, 'max_spell_level' => 3],
            7 => ['count' => 2, 'max_spell_level' => 4],
            9 => ['count' => 2, 'max_spell_level' => 5],
        ];
    }

    /**
     * Check if a feature name indicates it grants spells.
     *
     * Patterns that grant spells:
     * - "Domain Spells", "Circle Spells", "Oath Spells"
     * - "Alchemist Spells", "Battle Smith Spells" (Artificer)
     * - "Expanded Spell List" (Warlock)
     * - "Psionic Spells" (Aberrant Mind Sorcerer)
     *
     * Patterns that do NOT grant spells (false positives):
     * - "Spellcasting" (enables casting, doesn't grant bonus spells)
     * - "Spell Resistance", "Sculpt Spells", "Share Spells" (about spells, not granting)
     * - "Awakened Spellbook" (enhances spellbook, doesn't grant spells)
     */
    private function isSpellGrantingFeature(string $featureName): bool
    {
        // Patterns that indicate spell-granting features
        $grantingPatterns = [
            '/\bDomain Spells\b/i',
            '/\bCircle Spells\b/i',
            '/\bOath Spells\b/i',
            '/\bPsionic Spells\b/i',
            '/\bExpanded Spell List\b/i',
            '/\b(Alchemist|Armorer|Artillerist|Battle Smith) Spells\b/i',
        ];

        foreach ($grantingPatterns as $pattern) {
            if (preg_match($pattern, $featureName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a subclass should have bonus spells based on D&D rules.
     *
     * These subclass types typically grant bonus spells:
     * - Paladin Oaths (Oath Spells at 3,5,9,13,17)
     * - Druid Circles that grant Circle Spells (Land, Spores, Wildfire)
     * - Cleric Domains (Domain Spells) - but these work correctly
     *
     * Note: Not all subclasses of spellcaster classes get bonus spells.
     * Wizard schools, Sorcerer origins, etc. typically don't.
     */
    private function shouldHaveBonusSpells(string $subclassSlug, array $subData): bool
    {
        // Paladin Oaths should have Oath Spells
        if (str_contains($subclassSlug, 'paladin-oath')) {
            return true;
        }

        // Specific Druid circles that should have Circle Spells
        $druidsWithSpells = [
            'druid-circle-of-the-land',
            'druid-circle-of-spores',
            'druid-circle-of-wildfire',
        ];
        foreach ($druidsWithSpells as $pattern) {
            if (str_contains($subclassSlug, $pattern)) {
                return true;
            }
        }

        // Artificer subclasses should have subclass spells
        if (str_contains($subclassSlug, 'artificer-')) {
            return true;
        }

        return false;
    }
}

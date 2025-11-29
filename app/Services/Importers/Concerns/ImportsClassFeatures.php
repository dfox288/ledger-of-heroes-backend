<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\ClassFeatureSpecialTag;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;

/**
 * Trait for importing class features, feature modifiers, and related data.
 */
trait ImportsClassFeatures
{
    /**
     * Import class features.
     */
    protected function importFeatures(CharacterClass $class, array $features): void
    {
        // First pass: Create/update all features without parent links
        $createdFeatures = [];
        foreach ($features as $featureData) {
            // Detect multiclass-only features by name pattern
            $isMulticlassOnly = $this->isMulticlassOnlyFeature($featureData['name']);

            // Use updateOrCreate to prevent duplicates on re-import
            // Unique key: class_id + level + feature_name + sort_order
            $feature = ClassFeature::updateOrCreate(
                [
                    'class_id' => $class->id,
                    'level' => $featureData['level'],
                    'feature_name' => $featureData['name'],
                    'sort_order' => $featureData['sort_order'],
                ],
                [
                    'is_optional' => $featureData['is_optional'],
                    'is_multiclass_only' => $isMulticlassOnly,
                    'description' => $featureData['description'],
                ]
            );

            // Key by level:name to handle same feature name at different levels
            // e.g., "Bear (Path of the Totem Warrior)" exists at both L3 and L14
            $featureKey = $featureData['level'].':'.$featureData['name'];
            $createdFeatures[$featureKey] = $feature;

            // Import special tags (fighting styles, unarmored defense, etc.)
            if (! empty($featureData['special_tags'])) {
                foreach ($featureData['special_tags'] as $tag) {
                    ClassFeatureSpecialTag::create([
                        'class_feature_id' => $feature->id,
                        'tag' => $tag,
                    ]);
                }
            }

            // Import feature modifiers (speed bonuses, AC bonuses, ability score bonuses, etc.)
            if (! empty($featureData['modifiers'])) {
                $this->importFeatureModifiers($class, $featureData['modifiers'], $featureData['level']);
            }

            // Create Ability Score Improvement modifier if this level grants ASI
            // Use XML attribute instead of name parsing for more reliable detection
            if (! empty($featureData['grants_asi'])) {
                // Use updateOrCreate to prevent duplicates on re-import
                // Unique key: reference_type + reference_id + modifier_category + level
                Modifier::updateOrCreate(
                    [
                        'reference_type' => get_class($class),
                        'reference_id' => $class->id,
                        'modifier_category' => 'ability_score',
                        'level' => $featureData['level'],
                    ],
                    [
                        'value' => '+2',
                        'ability_score_id' => null, // Player chooses
                        'is_choice' => true,
                        'choice_count' => 2, // Standard ASI allows 2 increases
                        'condition' => 'Choose one ability score to increase by 2, or two ability scores to increase by 1 each',
                    ]
                );
            }

            // Detect Bonus Proficiencies and create proficiency records
            if (stripos($featureData['name'], 'Bonus Proficiencies') !== false) {
                $this->importBonusProficiencies($class, $featureData);
            }

            // Import random tables from <roll> XML elements
            if (! empty($featureData['rolls'])) {
                $this->importFeatureRolls($feature, $featureData['rolls']);
            }

            // Import random tables from pipe-delimited tables in description text
            // This handles BOTH dice-based random tables AND reference tables (dice_type = null)
            $this->importRandomTablesFromText($feature, $featureData['description'], clearExisting: false);
        }

        // Second pass: Link child features to their parents
        // Features with "Parent: Option" naming pattern link to their parent
        $this->linkParentFeatures($class, $createdFeatures);
    }

    /**
     * Link child features to their parent features based on naming conventions.
     *
     * Handles three patterns:
     * 1. Colon-based: "Fighting Style: Archery" → parent "Fighting Style"
     * 2. Champion L10: "Fighting Style: Archery (Champion)" → parent "Additional Fighting Style (Champion)"
     * 3. Totem options: "Bear (Path of the Totem Warrior)" → parent "Totem Spirit (Path of the Totem Warrior)"
     *
     * Only links optional features to prevent false positives.
     */
    protected function linkParentFeatures(CharacterClass $class, array $createdFeatures): void
    {
        foreach ($createdFeatures as $featureKey => $feature) {
            // Skip non-optional features
            if (! $feature->is_optional) {
                continue;
            }

            // Extract feature name from key (format: "level:name")
            // e.g., "3:Bear (Path of the Totem Warrior)" → "Bear (Path of the Totem Warrior)"
            $name = substr($featureKey, strpos($featureKey, ':') + 1);

            // Pattern 1 & 2: Colon-based features (feature name contains colon)
            if (str_contains($name, ':')) {
                $this->linkColonBasedFeature($class, $feature, $name);

                continue;
            }

            // Pattern 3: Totem Warrior options (no colon, parenthetical subclass marker)
            $this->linkTotemFeature($class, $feature, $name);
        }
    }

    /**
     * Link colon-based features to their parents.
     *
     * Handles:
     * - "Fighting Style: Archery" → parent "Fighting Style"
     * - "Fighting Style: Archery (Champion)" → parent "Additional Fighting Style (Champion)"
     */
    private function linkColonBasedFeature(CharacterClass $class, ClassFeature $feature, string $name): void
    {
        // Extract parent name (everything before the colon)
        $parentName = trim(explode(':', $name)[0]);

        // Check for suffix pattern: "Fighting Style: X (Champion)"
        $suffix = null;
        if (preg_match('/\(([^)]+)\)$/', $name, $suffixMatch)) {
            $suffix = $suffixMatch[1];
        }

        // Try exact parent first: "Fighting Style"
        if ($this->tryLinkToParent($class, $feature, $parentName)) {
            return;
        }

        // Try "Additional {Parent} ({Suffix})" variant for Champion-style features
        // e.g., "Additional Fighting Style (Champion)" for Champion L10 fighting styles
        if ($suffix) {
            $altParentName = "Additional {$parentName} ({$suffix})";
            $this->tryLinkToParent($class, $feature, $altParentName);
        }
    }

    /**
     * Link Totem Warrior options to their parent features.
     *
     * Handles:
     * - Level 3: "Bear (Path of the Totem Warrior)" → "Totem Spirit (Path of the Totem Warrior)"
     * - Level 6: "Aspect of the Bear (Path of the Totem Warrior)" → "Aspect of the Beast (Path of the Totem Warrior)"
     * - Level 14: "Bear (Path of the Totem Warrior)" → "Totemic Attunement (Path of the Totem Warrior)"
     */
    private function linkTotemFeature(CharacterClass $class, ClassFeature $feature, string $name): void
    {
        // Pattern: "OptionName (SubclassMarker)"
        // e.g., "Bear (Path of the Totem Warrior)" or "Aspect of the Bear (Path of the Totem Warrior)"
        if (! preg_match('/^(.+)\s*\((.+)\)$/', $name, $matches)) {
            return;
        }

        $optionName = trim($matches[1]);
        $subclassMarker = trim($matches[2]);

        // Level 14: Same animal names (Bear, Eagle, Wolf) but parent is "Totemic Attunement"
        // Check this FIRST because L3 and L14 have same option names
        if (in_array($optionName, ['Bear', 'Eagle', 'Wolf']) && $feature->level === 14) {
            $parentName = "Totemic Attunement ({$subclassMarker})";
            $this->tryLinkToParent($class, $feature, $parentName);

            return;
        }

        // Define parent mappings for Totem Warrior features (L3 and L6)
        $totemParentMappings = [
            // Level 3 options
            'Bear' => 'Totem Spirit',
            'Eagle' => 'Totem Spirit',
            'Wolf' => 'Totem Spirit',
            // Level 6 options
            'Aspect of the Bear' => 'Aspect of the Beast',
            'Aspect of the Eagle' => 'Aspect of the Beast',
            'Aspect of the Wolf' => 'Aspect of the Beast',
        ];

        // Check if this is a known totem option (L3 or L6)
        if (isset($totemParentMappings[$optionName])) {
            $parentBase = $totemParentMappings[$optionName];
            $parentName = "{$parentBase} ({$subclassMarker})";
            $this->tryLinkToParent($class, $feature, $parentName);
        }
    }

    /**
     * Attempt to link a feature to a parent by name.
     *
     * @return bool True if parent was found and linked
     */
    private function tryLinkToParent(CharacterClass $class, ClassFeature $feature, string $parentName): bool
    {
        $parent = ClassFeature::where('class_id', $class->id)
            ->where('level', $feature->level)
            ->where('feature_name', $parentName)
            ->first();

        if ($parent && $parent->id !== $feature->id) {
            $feature->update(['parent_feature_id' => $parent->id]);

            return true;
        }

        return false;
    }

    /**
     * Import feature modifiers (speed, AC, ability score bonuses, etc.).
     * Saves modifiers to entity_modifiers table linked to the character class.
     */
    protected function importFeatureModifiers(CharacterClass $class, array $modifiers, int $level): void
    {
        foreach ($modifiers as $modifierData) {
            // Build unique keys for updateOrCreate
            $uniqueKeys = [
                'reference_type' => get_class($class),
                'reference_id' => $class->id,
                'modifier_category' => $modifierData['modifier_category'],
                'level' => $level,
            ];

            // Build values to set/update
            $values = [
                'value' => $modifierData['value'],
            ];

            // Add ability_code if present (for ability_score category)
            if (isset($modifierData['ability_code'])) {
                $abilityScore = \App\Models\AbilityScore::where('code', strtoupper($modifierData['ability_code']))->first();
                if ($abilityScore) {
                    $uniqueKeys['ability_score_id'] = $abilityScore->id;
                    $values['ability_score_id'] = $abilityScore->id;
                } else {
                    $uniqueKeys['ability_score_id'] = null;
                    $values['ability_score_id'] = null;
                }
            } else {
                $uniqueKeys['ability_score_id'] = null;
                $values['ability_score_id'] = null;
            }

            // Use updateOrCreate to prevent duplicates on re-import
            Modifier::updateOrCreate($uniqueKeys, $values);
        }
    }

    /**
     * Import bonus proficiencies from feature description text.
     */
    protected function importBonusProficiencies(CharacterClass $class, array $featureData): void
    {
        $text = $featureData['description'];
        $level = $featureData['level'];

        // Check if it's a choice-based proficiency
        if (preg_match('/proficiency with (\w+) skills? of your choice/i', $text, $matches)) {
            // Extract number (e.g., "three skills" -> 3)
            $quantityWords = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5];
            $quantityWord = strtolower($matches[1]);
            $quantity = $quantityWords[$quantityWord] ?? 1;

            // Use updateOrCreate to prevent duplicates on re-import
            // Unique key: reference_type + reference_id + proficiency_type + level + is_choice
            Proficiency::updateOrCreate(
                [
                    'reference_type' => get_class($class),
                    'reference_id' => $class->id,
                    'proficiency_type' => 'skill',
                    'proficiency_name' => null, // Player chooses
                    'level' => $level,
                    'is_choice' => true,
                ],
                [
                    'grants' => true,
                    'quantity' => $quantity,
                ]
            );

            return;
        }

        // Parse fixed proficiencies from text
        // Patterns: "proficiency with X, Y, and Z" or "proficiency with X and Y"
        if (preg_match('/proficiency with (.+?)(?:\.|$)/i', $text, $matches)) {
            $proficienciesText = $matches[1];

            // Split by commas and "and"
            $proficiencies = preg_split('/,\s*(?:and\s+)?|\s+and\s+/', $proficienciesText);

            foreach ($proficiencies as $profName) {
                $profName = trim($profName);
                if (empty($profName)) {
                    continue;
                }

                // Determine proficiency type based on keywords
                $profType = $this->determineProficiencyType($profName);

                // Use updateOrCreate to prevent duplicates on re-import
                // Unique key: reference_type + reference_id + proficiency_type + proficiency_name + level
                Proficiency::updateOrCreate(
                    [
                        'reference_type' => get_class($class),
                        'reference_id' => $class->id,
                        'proficiency_type' => $profType,
                        'proficiency_name' => $profName,
                        'level' => $level,
                    ],
                    [
                        'grants' => true,
                        'is_choice' => false,
                    ]
                );
            }
        }
    }

    /**
     * Determine proficiency type from proficiency name.
     */
    protected function determineProficiencyType(string $name): string
    {
        $lowerName = strtolower($name);

        if (str_contains($lowerName, 'armor')) {
            return 'armor';
        }
        if (str_contains($lowerName, 'weapon') || str_contains($lowerName, 'martial')) {
            return 'weapon';
        }
        if (str_contains($lowerName, 'kit') || str_contains($lowerName, 'tools')) {
            return 'tool';
        }
        if (str_contains($lowerName, 'shield')) {
            return 'armor'; // Shields are armor category
        }

        // Default to tool for unknown types
        return 'tool';
    }

    /**
     * Import random tables from <roll> XML elements.
     *
     * Groups rolls by description to create tables with level-based entries.
     *
     * Example: Sneak Attack has 10 rolls with description "Extra Damage"
     *          → Creates 1 table with 10 entries (one per level)
     *
     * @param  array  $rolls  Array of ['description' => string, 'formula' => string, 'level' => int|null]
     */
    protected function importFeatureRolls(ClassFeature $feature, array $rolls): void
    {
        // Group rolls by description (table name)
        // Example: All "Extra Damage" rolls → one table
        $groupedRolls = collect($rolls)->groupBy('description');

        foreach ($groupedRolls as $tableName => $rollGroup) {
            // Extract dice type from first roll formula
            // Examples: "1d6" → "d6", "2d12" → "d12"
            $firstFormula = $rollGroup->first()['formula'];
            $diceType = $this->extractDiceType($firstFormula);

            $table = RandomTable::create([
                'reference_type' => ClassFeature::class,
                'reference_id' => $feature->id,
                'table_name' => $tableName,
                'dice_type' => $diceType,
                'description' => null,
            ]);

            foreach ($rollGroup as $index => $roll) {
                RandomTableEntry::create([
                    'random_table_id' => $table->id,
                    'roll_min' => $roll['level'] ?? 1, // Use level as roll value, or 1 if no level
                    'roll_max' => $roll['level'] ?? 1,
                    'result_text' => $roll['formula'], // "1d6", "2d6", etc.
                    'level' => $roll['level'] ?? null, // Character level when this roll becomes available
                    'sort_order' => $index,
                ]);
            }
        }
    }

    /**
     * Extract dice type from formula (e.g., "1d6" → "d6", "2d12" → "d12").
     *
     * @param  string  $formula  Dice formula like "1d6", "2d12+5"
     * @return string|null Dice type like "d6", "d12" or null if not found
     */
    protected function extractDiceType(string $formula): ?string
    {
        if (preg_match('/\d*d\d+/', $formula, $matches)) {
            // Remove leading number: "2d6" → "d6"
            return preg_replace('/^\d+/', '', $matches[0]);
        }

        return null;
    }

    /**
     * Determine if a feature is only relevant when multiclassing into this class.
     *
     * Matches patterns:
     * - "Multiclass {ClassName}" (e.g., "Multiclass Wizard", "Multiclass Cleric")
     * - "Multiclass Features" (generic multiclass requirements)
     */
    protected function isMulticlassOnlyFeature(string $featureName): bool
    {
        return str_starts_with($featureName, 'Multiclass ');
    }
}

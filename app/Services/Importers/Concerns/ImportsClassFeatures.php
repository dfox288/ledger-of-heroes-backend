<?php

namespace App\Services\Importers\Concerns;

use App\Enums\DataTableType;
use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\ClassFeatureSpecialTag;
use App\Models\EntityChoice;
use App\Models\EntityDataTable;
use App\Models\EntityDataTableEntry;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Services\Parsers\Traits\ParsesChoices;

/**
 * Trait for importing class features, feature modifiers, and related data.
 */
trait ImportsClassFeatures
{
    use ImportsSubclassSpells;
    use ParsesChoices;

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
                    'resets_on' => $featureData['resets_on'] ?? null,
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

            // Create Ability Score Improvement choice if this level grants ASI
            // Use XML attribute instead of name parsing for more reliable detection
            if (! empty($featureData['grants_asi'])) {
                // Clear existing ASI choice for this class/level before creating new one
                EntityChoice::where('reference_type', get_class($class))
                    ->where('reference_id', $class->id)
                    ->where('choice_type', 'ability_score')
                    ->where('choice_group', 'asi_level_'.$featureData['level'])
                    ->delete();

                // Create ASI choice in entity_choices table
                $this->createAbilityScoreChoice(
                    referenceType: get_class($class),
                    referenceId: $class->id,
                    choiceGroup: 'asi_level_'.$featureData['level'],
                    quantity: 2, // Standard ASI allows 2 increases (+1 each) or 1 (+2)
                    constraint: 'different',
                    levelGranted: $featureData['level'],
                    constraints: [
                        'value' => '+2',
                        'description' => 'Choose one ability score to increase by 2, or two ability scores to increase by 1 each',
                    ]
                );
            }

            // Detect Bonus Proficiency/Proficiencies and create proficiency records
            // Match both singular (Life Domain, Nature Domain) and plural (War Domain, Tempest Domain)
            if (stripos($featureData['name'], 'Bonus Proficienc') !== false) {
                $this->importBonusProficiencies($class, $featureData);
            }

            // Parse proficiencies from feature description text (e.g., "You gain proficiency with X")
            // Links to ClassFeature, not CharacterClass - important for subclass features like Hexblade
            $this->importFeatureProficiencies($feature, $featureData['description']);

            // Detect bonus cantrip grants from feature description
            // Pattern: "you gain the X cantrip" (e.g., Light Domain grants light cantrip)
            $this->importBonusCantrips($feature, $featureData['description']);

            // Detect skill choices from feature description
            // Pattern: "proficiency in one of the following skills of your choice: X, Y, or Z"
            $this->importFeatureSkillChoices($feature, $featureData['description']);

            // Detect bonus cantrip/spell choices from feature description
            // Pattern: "one druid cantrip of your choice", "one wizard cantrip of your choice"
            $this->importBonusSpellChoices($feature, $featureData['description']);

            // Import random tables from <roll> XML elements
            if (! empty($featureData['rolls'])) {
                $this->importFeatureRolls($feature, $featureData['rolls']);
            }

            // Import data tables from pipe-delimited tables in description text
            // This handles BOTH dice-based random tables AND reference tables (dice_type = null)
            $this->importDataTablesFromText($feature, $featureData['description'], clearExisting: false);

            // Import subclass spell tables (domain spells, circle spells, expanded spells)
            if ($this->hasSubclassSpellTable($featureData['description'])) {
                $this->importSubclassSpells($feature, $featureData['description']);
            }
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

            // Clear existing skill choice for this class/level
            EntityChoice::where('reference_type', get_class($class))
                ->where('reference_id', $class->id)
                ->where('choice_type', 'proficiency')
                ->where('choice_group', 'bonus_skill_level_'.$level)
                ->delete();

            // Create skill choice in entity_choices table
            $this->createProficiencyChoice(
                referenceType: get_class($class),
                referenceId: $class->id,
                choiceGroup: 'bonus_skill_level_'.$level,
                proficiencyType: 'skill',
                quantity: $quantity,
                levelGranted: $level
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
     * Import proficiencies from feature description text.
     *
     * Parses patterns like "You gain proficiency with X, Y, and Z" from feature descriptions
     * and links them to the ClassFeature (not CharacterClass). This is important for
     * subclass features like Hexblade's "Hex Warrior" which grants medium armor, shields,
     * and martial weapons.
     */
    protected function importFeatureProficiencies(ClassFeature $feature, string $description): void
    {
        // Pattern: "You gain proficiency with X, Y, and Z"
        // Must start with "You gain" to avoid matching prerequisite text like "requires proficiency with"
        if (! preg_match('/You gain proficiency with (.+?)(?:\.|$)/i', $description, $matches)) {
            return;
        }

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
            // Link to ClassFeature, not CharacterClass
            Proficiency::updateOrCreate(
                [
                    'reference_type' => ClassFeature::class,
                    'reference_id' => $feature->id,
                    'proficiency_type' => $profType,
                    'proficiency_name' => $profName,
                ],
                [
                    'grants' => true,
                ]
            );
        }
    }

    /**
     * Import bonus cantrips from feature description text.
     *
     * Detects patterns like:
     * - "you gain the light cantrip"
     * - "you learn the spare the dying cantrip"
     * - "you learn the sacred flame and light cantrips" (multiple)
     *
     * Creates entity_spells record linking the feature to each granted cantrip.
     */
    protected function importBonusCantrips(ClassFeature $feature, string $text): void
    {
        // Pattern: "you (gain|learn) the X cantrip(s)" - capture spell name(s)
        // Handles both singular and plural, and "gain" or "learn"
        if (! preg_match('/you (?:gain|learn) the ([a-z][a-z\s]+?) cantrips?/i', $text, $matches)) {
            return;
        }

        $spellNamesText = trim($matches[1]);

        // Parse multiple cantrips: "sacred flame and light" -> ["sacred flame", "light"]
        // Split by " and " to handle "X and Y" patterns
        $spellNames = preg_split('/\s+and\s+/', $spellNamesText);
        $spellNames = array_map('trim', $spellNames);
        $spellNames = array_filter($spellNames);

        foreach ($spellNames as $spellName) {
            // Look up the spell by name (case-insensitive)
            $spell = \App\Models\Spell::whereRaw('LOWER(name) = ?', [strtolower($spellName)])->first();

            if (! $spell) {
                // Spell not found - skip silently (spell may not be imported yet)
                continue;
            }

            // Use updateOrCreate to prevent duplicates on re-import
            \App\Models\EntitySpell::updateOrCreate(
                [
                    'reference_type' => ClassFeature::class,
                    'reference_id' => $feature->id,
                    'spell_id' => $spell->id,
                ],
                [
                    'is_cantrip' => true,
                    'level_requirement' => null,
                ]
            );
        }
    }

    /**
     * Import skill choices from feature description text.
     *
     * Detects pattern: "proficiency in one of the following skills of your choice: X, Y, or Z"
     *
     * Creates EntityChoice records for each skill option in the same choice_group.
     * Each skill is stored as a separate choice option within the same choice_group.
     */
    protected function importFeatureSkillChoices(ClassFeature $feature, string $text): void
    {
        // Pattern: "proficiency in one of the following skills of your choice: X, Y, or Z"
        // Also handles: "proficiency in one of the following skills ... : X, Y, or Z"
        if (! preg_match('/proficiency in one of the following skills[^:]*:\s*([^.]+)/i', $text, $matches)) {
            return;
        }

        $skillListText = trim($matches[1]);

        // Parse skill names: "Animal Handling, Nature, or Survival"
        // Split by comma and "or", then clean up
        $skillNames = preg_split('/,\s*(?:or\s+)?|\s+or\s+/', $skillListText);
        $skillNames = array_map('trim', $skillNames);
        $skillNames = array_filter($skillNames);

        if (empty($skillNames)) {
            return;
        }

        // Clear existing skill choices for this feature before importing
        EntityChoice::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('choice_type', 'proficiency')
            ->where('choice_group', 'feature_skill_choice')
            ->delete();

        $optionNumber = 1;

        foreach ($skillNames as $skillName) {
            // Look up the skill to get its slug
            $skill = \App\Models\Skill::whereRaw('LOWER(name) = ?', [strtolower($skillName)])->first();
            $skillSlug = $skill?->slug ?? strtolower(str_replace(' ', '-', $skillName));

            $this->createRestrictedProficiencyChoice(
                referenceType: ClassFeature::class,
                referenceId: $feature->id,
                choiceGroup: 'feature_skill_choice',
                proficiencyType: 'skill',
                targetType: 'skill',
                targetSlug: $skillSlug,
                choiceOption: $optionNumber,
                quantity: 1,
                levelGranted: $feature->level
            );

            $optionNumber++;
        }
    }

    /**
     * Import bonus spell/cantrip choices from feature description text.
     *
     * Detects patterns like:
     * - "you learn one druid cantrip of your choice"
     * - "you know one wizard cantrip of your choice"
     * - "one cleric spell of your choice"
     *
     * Creates EntityChoice record with spell_list_slug pointing
     * to the class whose spell list to choose from.
     */
    protected function importBonusSpellChoices(ClassFeature $feature, string $text): void
    {
        // Pattern: "(you learn|you know|learn) one {class} (cantrip|spell) of your choice"
        // Also handles: "one {class} cantrip of your choice" without prefix
        if (! preg_match('/(?:you\s+(?:learn|know|gain)\s+)?one\s+(\w+)\s+(cantrip|spell)\s+of\s+your\s+choice/i', $text, $matches)) {
            return;
        }

        $className = ucfirst(strtolower($matches[1]));
        $spellType = strtolower($matches[2]); // 'cantrip' or 'spell'
        $isCantrip = $spellType === 'cantrip';

        // Find the class by name (base class only, not subclass)
        $spellListClass = CharacterClass::where('name', $className)
            ->whereNull('parent_class_id')
            ->first();

        if (! $spellListClass) {
            // Class not found - skip silently
            return;
        }

        // Clear existing spell choice for this feature before creating new one
        EntityChoice::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('choice_type', 'spell')
            ->where('choice_group', 'feature_spell_choice')
            ->delete();

        // Create spell choice in entity_choices table
        $this->createSpellChoice(
            referenceType: ClassFeature::class,
            referenceId: $feature->id,
            choiceGroup: 'feature_spell_choice',
            quantity: 1,
            maxLevel: $isCantrip ? 0 : null, // 0 for cantrips, null for spells
            classSlug: $spellListClass->slug,
            schoolSlug: null,
            levelGranted: $feature->level
        );
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

            $table = EntityDataTable::create([
                'reference_type' => ClassFeature::class,
                'reference_id' => $feature->id,
                'table_name' => $tableName,
                'dice_type' => $diceType,
                'table_type' => DataTableType::DAMAGE,
                'description' => null,
            ]);

            foreach ($rollGroup as $index => $roll) {
                EntityDataTableEntry::create([
                    'entity_data_table_id' => $table->id,
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

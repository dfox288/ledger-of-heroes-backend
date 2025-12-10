<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\Modifier;
use App\Models\Race;
use App\Models\Size;
use App\Services\Importers\Concerns\ImportsConditions;
use App\Services\Importers\Concerns\ImportsEntitySpells;
use App\Services\Importers\Concerns\ImportsLanguages;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Importers\Concerns\ImportsSenses;
use App\Services\Importers\Strategies\Race\BaseRaceStrategy;
use App\Services\Importers\Strategies\Race\RacialVariantStrategy;
use App\Services\Importers\Strategies\Race\SubraceStrategy;
use App\Services\Parsers\RaceXmlParser;
use Illuminate\Support\Facades\Log;

class RaceImporter extends BaseImporter
{
    use ImportsConditions;
    use ImportsEntitySpells;
    use ImportsLanguages;
    use ImportsModifiers;
    use ImportsSenses;

    private array $createdBaseRaces = [];

    /**
     * Minimum ability score points for a race to be considered "complete".
     * A complete race has optional subraces (subrace_required = false).
     * D&D 5e standard is typically +2/+1 = 3 points for most races.
     */
    private const COMPLETE_RACE_ABILITY_THRESHOLD = 3;

    protected function importEntity(array $raceData): Race
    {
        // Apply all applicable strategies
        $strategies = [
            new BaseRaceStrategy,
            new SubraceStrategy,
            new RacialVariantStrategy,
        ];

        foreach ($strategies as $strategy) {
            if ($strategy->appliesTo($raceData)) {
                $raceData = $strategy->enhance($raceData);
                $this->logStrategyApplication($strategy, $raceData);
            }
        }

        // If a base race was newly created by SubraceStrategy, populate it with base race data
        if (! empty($raceData['_base_race_needs_population']) && ! empty($raceData['_base_race'])) {
            $this->populateBaseRace($raceData['_base_race'], $raceData['_base_race_data']);
        }

        // Lookup size by code
        $size = Size::where('code', $raceData['size_code'])->firstOrFail();

        // If slug not set by strategy, generate from name
        if (! isset($raceData['slug'])) {
            $raceData['slug'] = $this->generateSlug($raceData['name']);
        }

        // Generate full_slug with source prefix
        $sources = $raceData['sources'] ?? [];
        $fullSlug = $this->generateFullSlug($raceData['slug'], $sources);

        // Extract alternate movement speeds from ALL trait sources
        // For subraces, strategies may have replaced 'traits' with 'subrace_traits' (empty for some)
        // but the speeds typically come from base_traits (e.g., Aarakocra's Flight trait)
        $allTraitsForExtraction = array_merge(
            $raceData['base_traits'] ?? [],
            $raceData['subrace_traits'] ?? [],
            $raceData['traits'] ?? []
        );
        $extractedSpeeds = $this->extractSpeedsFromTraits($allTraitsForExtraction, $raceData['speed']);

        // Determine subrace_required value:
        // - Subraces (has parent_race_id): always false (no nested subraces in D&D 5e)
        // - Base races with 3+ ability points: false (complete race, subraces optional)
        // - Base races with < 3 ability points: true (incomplete, subrace required)
        $isSubrace = ! empty($raceData['parent_race_id']);
        $totalAbilityPoints = $this->calculateTotalAbilityPoints(
            $raceData['ability_bonuses'] ?? [],
            $raceData['ability_choices'] ?? []
        );
        $hasCompleteAbilityScores = $totalAbilityPoints >= self::COMPLETE_RACE_ABILITY_THRESHOLD;
        $subraceRequired = ! $isSubrace && ! $hasCompleteAbilityScores;

        // Create or update race using slug as unique key
        $race = Race::updateOrCreate(
            ['slug' => $raceData['slug']],
            [
                'name' => $raceData['name'],
                'full_slug' => $fullSlug,
                'parent_race_id' => $raceData['parent_race_id'] ?? null,
                'size_id' => $size->id,
                'has_size_choice' => $raceData['has_size_choice'] ?? false,
                'speed' => $raceData['speed'],
                'fly_speed' => $extractedSpeeds['fly_speed'],
                'swim_speed' => $extractedSpeeds['swim_speed'],
                'climb_speed' => $extractedSpeeds['climb_speed'],
                'subrace_required' => $subraceRequired,
            ]
        );

        // Import relationships using existing traits
        $this->importEntitySources($race, $raceData['sources'] ?? []);

        // Import traits (clear old ones first)
        $createdTraits = $this->importEntityTraits($race, $raceData['traits'] ?? []);

        // Import embedded tables in trait descriptions
        foreach ($createdTraits as $index => $trait) {
            $traitData = $raceData['traits'][$index] ?? null;
            if ($traitData) {
                $this->importTraitTables($trait, $traitData['description']);
            }
        }

        // Import all modifiers (ability bonuses, choices, resistances, trait modifiers, and skill advantages)
        $this->importAllModifiers(
            $race,
            $raceData['ability_bonuses'] ?? [],
            $raceData['ability_choices'] ?? [],
            $raceData['resistances'] ?? [],
            $raceData['modifiers'] ?? [],
            $raceData['skill_advantage_modifiers'] ?? []
        );

        // For subraces, don't duplicate proficiencies that belong to base race
        // Only import if this is not a subrace, or if we have subrace-specific proficiencies
        $proficiencies = $raceData['proficiencies'] ?? [];
        if (empty($raceData['parent_race_id'])) {
            // Base race or standalone race - import all proficiencies
            $this->importEntityProficiencies($race, $proficiencies);
        }
        // Note: Subraces inherit proficiencies from parent via inherited_data in API

        // Import languages if present (for base races only, subraces inherit)
        if (empty($raceData['parent_race_id'])) {
            $this->importEntityLanguages($race, $raceData['languages'] ?? []);
        }

        // Import conditions (immunities, advantages, resistances)
        // For subraces, only import subrace-specific conditions
        if (empty($raceData['parent_race_id'])) {
            $this->importEntityConditions($race, $raceData['conditions'] ?? []);
        }

        // Import spells
        if (isset($raceData['spellcasting'])) {
            $this->importSpells($race, $raceData['spellcasting']);
        }

        // Import data tables from trait rolls (also links traits to tables)
        $this->importDataTablesFromRolls($createdTraits, $raceData['traits'] ?? []);

        // Extract and import senses from traits (Darkvision, Superior Darkvision)
        // For subraces, only extract from subspecies traits to avoid duplication
        $sensesData = $this->extractSensesFromTraits($raceData['traits'] ?? []);
        $this->importEntitySenses($race, $sensesData);

        // Refresh to load all relationships created during import
        $race->refresh();

        return $race;
    }

    /**
     * Populate a newly created base race with data from its first subrace.
     *
     * When a subrace is imported and its base race doesn't exist, we create
     * a stub base race. This method populates that stub with the shared
     * base race traits, modifiers, proficiencies, etc.
     */
    private function populateBaseRace(Race $baseRace, array $baseRaceData): void
    {
        // Import traits (species, description, general traits)
        $createdTraits = $this->importEntityTraits($baseRace, $baseRaceData['traits'] ?? []);

        // Import embedded tables in trait descriptions
        foreach ($createdTraits as $index => $trait) {
            $traitData = $baseRaceData['traits'][$index] ?? null;
            if ($traitData) {
                $this->importTraitTables($trait, $traitData['description'] ?? '');
            }
        }

        // Import modifiers (ability bonuses, resistances, skill advantages)
        $this->importAllModifiers(
            $baseRace,
            $baseRaceData['ability_bonuses'] ?? [],
            [], // No ability choices for base races typically
            $baseRaceData['resistances'] ?? [],
            [], // No trait modifiers for base races
            $baseRaceData['skill_advantage_modifiers'] ?? []
        );

        // Import proficiencies
        $this->importEntityProficiencies($baseRace, $baseRaceData['proficiencies'] ?? []);

        // Import sources
        $this->importEntitySources($baseRace, $baseRaceData['sources'] ?? []);

        // Import languages
        $this->importEntityLanguages($baseRace, $baseRaceData['languages'] ?? []);

        // Import conditions
        $this->importEntityConditions($baseRace, $baseRaceData['conditions'] ?? []);

        // Extract and import senses from base traits
        $sensesData = $this->extractSensesFromTraits($baseRaceData['traits'] ?? []);
        $this->importEntitySenses($baseRace, $sensesData);

        // Extract and update alternate movement speeds from base traits
        $extractedSpeeds = $this->extractSpeedsFromTraits($baseRaceData['traits'] ?? [], $baseRace->speed);
        if ($extractedSpeeds['fly_speed'] !== null || $extractedSpeeds['swim_speed'] !== null || $extractedSpeeds['climb_speed'] !== null) {
            $baseRace->update([
                'fly_speed' => $extractedSpeeds['fly_speed'],
                'swim_speed' => $extractedSpeeds['swim_speed'],
                'climb_speed' => $extractedSpeeds['climb_speed'],
            ]);
        }

        Log::channel('import-strategy')->info('Populated base race', [
            'base_race' => $baseRace->name,
            'traits_count' => count($createdTraits),
            'proficiencies_count' => count($baseRaceData['proficiencies'] ?? []),
            'speeds' => $extractedSpeeds,
        ]);
    }

    /**
     * Log strategy application to import-strategy channel.
     */
    private function logStrategyApplication($strategy, array $data): void
    {
        Log::channel('import-strategy')->info('Strategy applied', [
            'race' => $data['name'],
            'strategy' => class_basename($strategy),
            'warnings' => $strategy->getWarnings(),
            'metrics' => $strategy->getMetrics(),
        ]);

        // Reset strategy for next entity
        $strategy->reset();
    }

    /**
     * Import all modifiers at once (ability bonuses, choices, resistances, trait modifiers, and skill advantages).
     * This ensures we don't clear modifiers between multiple imports.
     */
    private function importAllModifiers(
        Race $race,
        array $bonusesData,
        array $choicesData,
        array $resistancesData,
        array $traitModifiersData,
        array $skillAdvantageModifiers = []
    ): void {
        $modifiersData = [];

        // Transform fixed ability bonuses into modifier format
        foreach ($bonusesData as $bonusData) {
            // Map ability code to ability_score_id
            $abilityCode = strtoupper($bonusData['ability']);
            $abilityScore = AbilityScore::where('code', $abilityCode)->first();

            if (! $abilityScore) {
                continue; // Skip if ability score not found
            }

            $modifiersData[] = [
                'category' => 'ability_score',
                'value' => $bonusData['value'],
                'ability_score_id' => $abilityScore->id,
                'is_choice' => false,
            ];
        }

        // Transform choice-based bonuses into modifier format
        foreach ($choicesData as $choiceData) {
            $modifiersData[] = [
                'category' => 'ability_score',
                'value' => "+{$choiceData['value']}",
                'ability_score_id' => null, // NULL for choices
                'is_choice' => true,
                'choice_count' => $choiceData['choice_count'],
                'choice_constraint' => $choiceData['choice_constraint'],
            ];
        }

        // Transform damage resistances into modifier format
        foreach ($resistancesData as $resistanceData) {
            // Look up damage type by name (case-insensitive for SQLite compatibility)
            $damageType = \App\Models\DamageType::whereRaw('LOWER(name) = ?', [strtolower($resistanceData['damage_type'])])->first();

            if ($damageType) {
                $modifiersData[] = [
                    'category' => 'damage_resistance',
                    'value' => 'resistance',
                    'damage_type_id' => $damageType->id,
                ];
            }
        }

        // Add trait modifiers (HP bonuses, speed bonuses, etc.) directly
        // These come from <modifier> elements within <trait> elements
        foreach ($traitModifiersData as $traitModifier) {
            // Rename modifier_category to category for consistency with importEntityModifiers trait
            $modifier = [
                'category' => $traitModifier['modifier_category'],
                'value' => $traitModifier['value'],
            ];

            // Add ability_code if present (for ability_score category)
            if (isset($traitModifier['ability_code'])) {
                $abilityScore = AbilityScore::where('code', strtoupper($traitModifier['ability_code']))->first();
                if ($abilityScore) {
                    $modifier['ability_score_id'] = $abilityScore->id;
                }
            }

            $modifiersData[] = $modifier;
        }

        // Add skill advantage modifiers (from parseSkillAdvantagesFromTraits)
        foreach ($skillAdvantageModifiers as $skillAdvantage) {
            $modifiersData[] = [
                'category' => $skillAdvantage['modifier_category'],
                'skill_name' => $skillAdvantage['skill_name'],
                'value' => $skillAdvantage['value'],
                'condition' => $skillAdvantage['condition'] ?? null,
            ];
        }

        // Use trait to import all modifiers at once
        $this->importEntityModifiers($race, $modifiersData);
    }

    public function getParser(): object
    {
        return new RaceXmlParser;
    }

    /**
     * Override importFromFile to handle base race counting.
     * RaceImporter needs to count base races that are auto-created for subraces.
     */
    public function importFromFile(string $filePath): int
    {
        // Use parent's file validation (throws FileNotFoundException)
        if (! file_exists($filePath)) {
            throw new \App\Exceptions\Import\FileNotFoundException($filePath);
        }

        $xmlContent = file_get_contents($filePath);
        $parser = $this->getParser();
        $races = $parser->parse($xmlContent);

        // Reset tracking for base races
        $this->createdBaseRaces = [];

        $count = 0;
        foreach ($races as $raceData) {
            // If this is a subrace, check if we need to count the base race
            if (! empty($raceData['base_race_name'])) {
                $baseRaceName = $raceData['base_race_name'];

                // Only count the base race once per import
                if (! isset($this->createdBaseRaces[$baseRaceName])) {
                    $this->createdBaseRaces[$baseRaceName] = true;
                    $count++; // Count base race creation
                }
            }

            $this->import($raceData);
            $count++;
        }

        return $count;
    }

    private function importDataTablesFromRolls(array $createdTraits, array $traitsData): void
    {
        foreach ($traitsData as $index => $traitData) {
            if (empty($traitData['rolls'])) {
                continue;
            }

            // Use the trait we created earlier (by index)
            $trait = $createdTraits[$index] ?? null;

            if (! $trait) {
                continue;
            }

            // Clear existing data tables for this trait
            $trait->dataTables()->delete();

            // Group rolls by description to detect level-scaling progressions
            $rollsByDescription = [];
            foreach ($traitData['rolls'] as $roll) {
                if (empty($roll['description']) || empty($roll['formula'])) {
                    continue;
                }
                $rollsByDescription[$roll['description']][] = $roll;
            }

            $firstDataTableId = null;

            foreach ($rollsByDescription as $description => $rolls) {
                // Check if any roll in this group has a level attribute
                $hasLevelScaling = collect($rolls)->contains(fn ($r) => $r['level'] !== null);

                if ($hasLevelScaling && count($rolls) > 1) {
                    // Level-scaling progression: create ONE table with entries per level
                    $dataTable = \App\Models\EntityDataTable::create([
                        'reference_type' => \App\Models\CharacterTrait::class,
                        'reference_id' => $trait->id,
                        'table_name' => $description,
                        'dice_type' => null, // Dice stored in entries for progressions
                        'table_type' => \App\Enums\DataTableType::PROGRESSION,
                        'description' => "From trait: {$traitData['name']}",
                    ]);

                    // Create entries for each level tier
                    foreach ($rolls as $sortOrder => $roll) {
                        \App\Models\EntityDataTableEntry::create([
                            'entity_data_table_id' => $dataTable->id,
                            'level' => $roll['level'],
                            'result_text' => $roll['formula'],
                            'sort_order' => $sortOrder,
                        ]);
                    }
                } else {
                    // Non-leveled roll(s): create individual table(s) as before
                    foreach ($rolls as $roll) {
                        $dataTable = \App\Models\EntityDataTable::create([
                            'reference_type' => \App\Models\CharacterTrait::class,
                            'reference_id' => $trait->id,
                            'table_name' => $description,
                            'dice_type' => $roll['formula'],
                            'table_type' => \App\Enums\DataTableType::RANDOM,
                            'description' => "From trait: {$traitData['name']}",
                        ]);
                    }
                }

                // Track first data table for trait linkage
                if ($firstDataTableId === null) {
                    $firstDataTableId = $dataTable->id;
                }
            }

            // Update the trait to link to the first data table
            if ($firstDataTableId !== null) {
                $trait->update(['entity_data_table_id' => $firstDataTableId]);
            }
        }
    }

    /**
     * Import race spells.
     */
    private function importSpells(Race $race, array $spellcastingData): void
    {
        if (empty($spellcastingData['spells'])) {
            return;
        }

        // Get ability score
        $abilityScore = null;
        if (! empty($spellcastingData['ability'])) {
            $code = strtoupper(substr($spellcastingData['ability'], 0, 3));
            $abilityScore = \App\Models\AbilityScore::where('code', $code)->first();
        }

        // Transform parsed spells into format expected by ImportsEntitySpells trait
        $spellsData = array_map(function ($spellData) use ($abilityScore) {
            // Handle spell choices (e.g., High Elf cantrip from wizard list)
            if (! empty($spellData['is_choice'])) {
                return [
                    'is_choice' => true,
                    'choice_count' => $spellData['choice_count'] ?? 1,
                    'choice_group' => 'racial_cantrip',
                    'max_level' => $spellData['max_level'] ?? 0,
                    'class_name' => $spellData['class_name'] ?? null,
                    'is_ritual_only' => $spellData['is_ritual_only'] ?? false,
                ];
            }

            // Handle fixed spells (e.g., Tiefling's Thaumaturgy)
            return [
                'spell_name' => $spellData['spell_name'],
                'pivot_data' => [
                    'ability_score_id' => $abilityScore?->id,
                    'level_requirement' => $spellData['level_requirement'],
                    'usage_limit' => $spellData['usage_limit'],
                    'is_cantrip' => $spellData['is_cantrip'],
                ],
            ];
        }, $spellcastingData['spells']);

        // Delegate to the generalized trait method
        $this->importEntitySpells($race, $spellsData);
    }

    /**
     * Extract sense data from race traits.
     *
     * Looks for traits named "Darkvision" or "Superior Darkvision" and extracts
     * the range from the description text (e.g., "within 60 feet" or "within 120 feet").
     *
     * @param  array  $traits  Array of trait data from parser
     * @return array Array of sense data compatible with importEntitySenses()
     */
    private function extractSensesFromTraits(array $traits): array
    {
        $senses = [];

        foreach ($traits as $trait) {
            $name = $trait['name'] ?? '';
            $description = $trait['description'] ?? '';

            // Check for Darkvision or Superior Darkvision
            if (stripos($name, 'darkvision') !== false) {
                // Extract range from description: "within 60 feet" or "within 120 feet"
                $range = 60; // Default to 60 feet

                if (preg_match('/within\s+(\d+)\s+feet/i', $description, $matches)) {
                    $range = (int) $matches[1];
                }

                $senses[] = [
                    'type' => 'darkvision',
                    'range' => $range,
                    'is_limited' => false,
                    'notes' => null,
                ];
            }
        }

        return $senses;
    }

    /**
     * Extract alternate movement speeds from race traits.
     *
     * Looks for traits like "Flight", "Swim Speed", or "Cat's Claws" and extracts
     * the speed value from the description text. Handles both explicit speed values
     * (e.g., "flying speed of 50 feet") and relative values (e.g., "flying speed
     * equal to your walking speed").
     *
     * @param  array  $traits  Array of trait data from parser
     * @param  int  $walkingSpeed  The race's base walking speed (used for "equal to walking speed" patterns)
     * @return array Array with 'fly_speed', 'swim_speed', and 'climb_speed' keys (nullable)
     */
    private function extractSpeedsFromTraits(array $traits, int $walkingSpeed): array
    {
        $speeds = [
            'fly_speed' => null,
            'swim_speed' => null,
            'climb_speed' => null,
        ];

        foreach ($traits as $trait) {
            $name = $trait['name'] ?? '';
            $description = $trait['description'] ?? '';

            // Check for Flight trait (including Winged variants)
            if (preg_match('/flight|flying|winged/i', $name)) {
                if (preg_match('/flying speed of (\d+) feet/i', $description, $matches)) {
                    $speeds['fly_speed'] = (int) $matches[1];
                } elseif (preg_match('/flying speed equal to your walking speed/i', $description)) {
                    $speeds['fly_speed'] = $walkingSpeed;
                }
            }

            // Check for Swim Speed in any trait description (not just traits named "swim")
            // This catches patterns like "Variant: Aquatic" trait for Aquatic Elf Ancestry
            if ($speeds['swim_speed'] === null) {
                if (preg_match('/swimming speed of (\d+) feet/i', $description, $matches)) {
                    $speeds['swim_speed'] = (int) $matches[1];
                } elseif (preg_match('/swimming speed equal to your walking speed/i', $description)) {
                    $speeds['swim_speed'] = $walkingSpeed;
                }
            }

            // Check for Climb Speed in any trait description
            if ($speeds['climb_speed'] === null) {
                if (preg_match('/climbing speed of (\d+) feet/i', $description, $matches)) {
                    $speeds['climb_speed'] = (int) $matches[1];
                } elseif (preg_match('/climbing speed equal to your walking speed/i', $description)) {
                    $speeds['climb_speed'] = $walkingSpeed;
                }
            }
        }

        return $speeds;
    }

    /**
     * Calculate total ability score points from fixed bonuses and choices.
     *
     * D&D 5e races typically grant ability bonuses in one of these patterns:
     * - Fixed bonuses: +2 to one ability, +1 to another (3 points total)
     * - All bonuses: +1 to all six abilities (6 points total, e.g., Human)
     * - Mixed: Fixed bonuses + choice-based bonuses (e.g., Half-Elf: Cha +2 + choose 2 for +1)
     *
     * A race with 3+ total points is considered "complete" and doesn't require
     * a subrace to be playable.
     *
     * @param  array  $bonuses  Fixed ability bonuses [{ability: 'Str', value: 2}, ...]
     * @param  array  $choices  Choice-based bonuses [{choice_count: 2, value: 1}, ...]
     * @return int Total ability score points
     */
    private function calculateTotalAbilityPoints(array $bonuses, array $choices): int
    {
        $total = 0;

        // Sum fixed ability bonuses
        foreach ($bonuses as $bonus) {
            $total += abs((int) ($bonus['value'] ?? 0));
        }

        // Sum choice-based ability bonuses (choice_count * value)
        foreach ($choices as $choice) {
            $choiceCount = (int) ($choice['choice_count'] ?? 1);
            $value = abs((int) ($choice['value'] ?? 0));
            $total += $choiceCount * $value;
        }

        return $total;
    }
}

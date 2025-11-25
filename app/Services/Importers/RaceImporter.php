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

    private array $createdBaseRaces = [];

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

        // Lookup size by code
        $size = Size::where('code', $raceData['size_code'])->firstOrFail();

        // If slug not set by strategy, generate from name
        if (! isset($raceData['slug'])) {
            $raceData['slug'] = $this->generateSlug($raceData['name']);
        }

        // Create or update race using slug as unique key
        $race = Race::updateOrCreate(
            ['slug' => $raceData['slug']],
            [
                'name' => $raceData['name'],
                'parent_race_id' => $raceData['parent_race_id'] ?? null,
                'size_id' => $size->id,
                'speed' => $raceData['speed'],
                'description' => $raceData['description'] ?? '',
            ]
        );

        // Import relationships using existing traits
        $this->importEntitySources($race, $raceData['sources'] ?? []);

        // Import traits (clear old ones first)
        $createdTraits = $this->importEntityTraits($race, $raceData['traits'] ?? []);

        // Import embedded tables in trait descriptions
        foreach ($createdTraits as $index => $trait) {
            $traitData = $raceData['traits'][$index];
            $this->importTraitTables($trait, $traitData['description']);
        }

        // Import all modifiers (ability bonuses, choices, resistances, and trait modifiers)
        $this->importAllModifiers(
            $race,
            $raceData['ability_bonuses'] ?? [],
            $raceData['ability_choices'] ?? [],
            $raceData['resistances'] ?? [],
            $raceData['modifiers'] ?? []
        );

        // Import proficiencies if present
        $this->importEntityProficiencies($race, $raceData['proficiencies'] ?? []);

        // Import languages if present
        $this->importEntityLanguages($race, $raceData['languages'] ?? []);

        // Import conditions (immunities, advantages, resistances)
        $this->importEntityConditions($race, $raceData['conditions'] ?? []);

        // Import spells
        if (isset($raceData['spellcasting'])) {
            $this->importSpells($race, $raceData['spellcasting']);
        }

        // Import random tables from trait rolls (also links traits to tables)
        $this->importRandomTablesFromRolls($createdTraits, $raceData['traits'] ?? []);

        // Refresh to load all relationships created during import
        $race->refresh();

        return $race;
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
     * Import all modifiers at once (ability bonuses, choices, resistances, and trait modifiers).
     * This ensures we don't clear modifiers between multiple imports.
     */
    private function importAllModifiers(
        Race $race,
        array $bonusesData,
        array $choicesData,
        array $resistancesData,
        array $traitModifiersData
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
            // Look up damage type by name
            $damageType = \App\Models\DamageType::where('name', $resistanceData['damage_type'])->first();

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

        // Use trait to import all modifiers at once
        $this->importEntityModifiers($race, $modifiersData);
    }

    protected function getParser(): object
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

    private function importRandomTablesFromRolls(array $createdTraits, array $traitsData): void
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

            // Clear existing random tables for this trait
            $trait->randomTables()->delete();

            foreach ($traitData['rolls'] as $roll) {
                if (empty($roll['description']) || empty($roll['formula'])) {
                    continue;
                }

                // Create a random table for this roll, referencing the TRAIT
                $randomTable = \App\Models\RandomTable::create([
                    'reference_type' => \App\Models\CharacterTrait::class,
                    'reference_id' => $trait->id,
                    'table_name' => $roll['description'],
                    'dice_type' => $roll['formula'],
                    'description' => "From trait: {$traitData['name']}",
                ]);

                // Update the trait to link back to this random table
                $trait->update(['random_table_id' => $randomTable->id]);

                // Note: Random table entries are embedded in the trait text as formatted tables
                // and will need to be parsed separately if needed in the future
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
}

<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\Race;
use App\Models\Size;
use App\Models\Skill;
use App\Models\Source;
use App\Services\Parsers\RaceXmlParser;

class RaceImporter
{
    private array $createdBaseRaces = [];

    public function import(array $raceData): Race
    {
        // Lookup size by code
        $size = Size::where('code', $raceData['size_code'])->firstOrFail();

        // Lookup source by code
        $source = Source::where('code', $raceData['source_code'])->firstOrFail();

        // If this is a subrace, ensure base race exists first
        $parentRaceId = null;
        if (!empty($raceData['base_race_name'])) {
            $baseRace = $this->getOrCreateBaseRace(
                $raceData['base_race_name'],
                $raceData['size_code'],
                $raceData['speed'],
                $raceData['source_code'],
                $raceData['source_pages']
            );
            $parentRaceId = $baseRace->id;
        }

        // Create or update race
        $race = Race::updateOrCreate(
            [
                'name' => $raceData['name'],
                'parent_race_id' => $parentRaceId,
            ],
            [
                'size_id' => $size->id,
                'speed' => $raceData['speed'],
                'source_id' => $source->id,
                'source_pages' => $raceData['source_pages'],
            ]
        );

        // Import traits (clear old ones first)
        $this->importTraits($race, $raceData['traits'] ?? []);

        // Import ability bonuses as modifiers
        $this->importAbilityBonuses($race, $raceData['ability_bonuses'] ?? []);

        // Import proficiencies if present
        if (isset($raceData['proficiencies'])) {
            $this->importProficiencies($race, $raceData['proficiencies']);
        }

        // Import random tables from trait rolls (also links traits to tables)
        $this->importRandomTablesFromTraits($race, $raceData['traits'] ?? []);

        return $race;
    }

    private function importTraits(Race $race, array $traitsData): void
    {
        // Clear existing traits for this race
        $race->traits()->delete();

        foreach ($traitsData as $traitData) {
            \App\Models\CharacterTrait::create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
                'name' => $traitData['name'],
                'category' => $traitData['category'],
                'description' => $traitData['description'],
                'sort_order' => $traitData['sort_order'],
            ]);
        }
    }

    private function importAbilityBonuses(Race $race, array $bonusesData): void
    {
        // Clear existing ability score modifiers for this race
        $race->modifiers()->where('modifier_category', 'ability_score')->delete();

        foreach ($bonusesData as $bonusData) {
            // Map ability code to ability_score_id
            $abilityCode = strtoupper($bonusData['ability']);
            $abilityScore = AbilityScore::where('code', $abilityCode)->first();

            if (!$abilityScore) {
                continue; // Skip if ability score not found
            }

            Modifier::create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
                'modifier_category' => 'ability_score',
                'ability_score_id' => $abilityScore->id,
                'value' => $bonusData['value'],
            ]);
        }
    }

    public function importFromFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $xmlContent = file_get_contents($filePath);
        $parser = new RaceXmlParser();
        $races = $parser->parse($xmlContent);

        // Reset tracking for base races
        $this->createdBaseRaces = [];

        $count = 0;
        foreach ($races as $raceData) {
            // If this is a subrace, check if we need to count the base race
            if (!empty($raceData['base_race_name'])) {
                $baseRaceName = $raceData['base_race_name'];

                // Only count the base race once per import
                if (!isset($this->createdBaseRaces[$baseRaceName])) {
                    $this->createdBaseRaces[$baseRaceName] = true;
                    $count++; // Count base race creation
                }
            }

            $this->import($raceData);
            $count++;
        }

        return $count;
    }

    private function getOrCreateBaseRace(
        string $baseRaceName,
        string $sizeCode,
        int $speed,
        string $sourceCode,
        string $sourcePages
    ): Race {
        // Check if base race already exists
        $existing = Race::where('name', $baseRaceName)
                        ->whereNull('parent_race_id')
                        ->first();

        if ($existing) {
            return $existing;
        }

        // Create base race with minimal data
        $size = Size::where('code', $sizeCode)->firstOrFail();
        $source = Source::where('code', $sourceCode)->firstOrFail();

        return Race::create([
            'name' => $baseRaceName,
            'size_id' => $size->id,
            'speed' => $speed,
            'source_id' => $source->id,
            'source_pages' => $sourcePages,
            'parent_race_id' => null,
        ]);
    }

    private function importProficiencies(Race $race, array $proficienciesData): void
    {
        // Clear existing proficiencies for this race
        $race->proficiencies()->delete();

        foreach ($proficienciesData as $profData) {
            $proficiency = [
                'reference_type' => Race::class,
                'reference_id' => $race->id,
                'proficiency_type' => $profData['type'],
            ];

            // Handle different proficiency types
            if ($profData['type'] === 'skill') {
                // Look up skill by name
                $skill = Skill::where('name', $profData['name'])->first();
                if ($skill) {
                    $proficiency['skill_id'] = $skill->id;
                } else {
                    // If skill not found, store as proficiency_name
                    $proficiency['proficiency_name'] = $profData['name'];
                }
            } elseif ($profData['type'] === 'weapon' || $profData['type'] === 'armor') {
                // Store as proficiency_name (items not imported yet)
                $proficiency['proficiency_name'] = $profData['name'];
            }

            Proficiency::create($proficiency);
        }
    }

    private function importRandomTablesFromTraits(Race $race, array $traitsData): void
    {
        // Clear existing random tables for this race
        $race->randomTables()->delete();

        foreach ($traitsData as $traitData) {
            if (empty($traitData['rolls'])) {
                continue;
            }

            foreach ($traitData['rolls'] as $roll) {
                if (empty($roll['description']) || empty($roll['formula'])) {
                    continue;
                }

                // Create a random table for this roll
                $randomTable = \App\Models\RandomTable::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'table_name' => $roll['description'],
                    'dice_type' => $roll['formula'],
                    'description' => "From trait: {$traitData['name']}",
                ]);

                // IMPORTANT: Link the trait to this random table
                $trait = $race->traits()
                    ->where('name', $traitData['name'])
                    ->where('sort_order', $traitData['sort_order'])
                    ->first();

                if ($trait) {
                    $trait->update(['random_table_id' => $randomTable->id]);
                }
            }
        }
    }
}

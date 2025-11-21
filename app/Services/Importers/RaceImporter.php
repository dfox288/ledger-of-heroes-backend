<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\Modifier;
use App\Models\Race;
use App\Models\Size;
use App\Models\Source;
use App\Services\Importers\Concerns\ImportsConditions;
use App\Services\Importers\Concerns\ImportsLanguages;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Parsers\RaceXmlParser;
use Illuminate\Support\Str;

class RaceImporter extends BaseImporter
{
    use ImportsConditions;
    use ImportsLanguages;
    use ImportsModifiers;

    private array $createdBaseRaces = [];

    protected function importEntity(array $raceData): Race
    {
        // Lookup size by code
        $size = Size::where('code', $raceData['size_code'])->firstOrFail();

        // If this is a subrace, ensure base race exists first
        $parentRaceId = null;
        if (! empty($raceData['base_race_name'])) {
            $baseRace = $this->getOrCreateBaseRace(
                $raceData['base_race_name'],
                $raceData['size_code'],
                $raceData['speed'],
                $raceData['sources']
            );
            $parentRaceId = $baseRace->id;
        }

        // Generate slug for race
        $slug = $this->generateSlugForRace($raceData['name'], $raceData['base_race_name'] ?? null);

        // Create or update race using slug as unique key
        $race = Race::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $raceData['name'],
                'parent_race_id' => $parentRaceId,
                'size_id' => $size->id,
                'speed' => $raceData['speed'],
            ]
        );

        // Import sources - clear old sources and create new ones
        if (isset($raceData['sources']) && is_array($raceData['sources'])) {
            $this->importEntitySources($race, $raceData['sources']);
        }

        // Import traits (clear old ones first)
        $createdTraits = $this->importEntityTraits($race, $raceData['traits'] ?? []);

        // Import embedded tables in trait descriptions
        foreach ($createdTraits as $index => $trait) {
            $traitData = $raceData['traits'][$index];
            $this->importTraitTables($trait, $traitData['description']);
        }

        // Import all modifiers (ability bonuses, choices, and resistances)
        $this->importAllModifiers(
            $race,
            $raceData['ability_bonuses'] ?? [],
            $raceData['ability_choices'] ?? [],
            $raceData['resistances'] ?? []
        );

        // Import proficiencies if present
        if (isset($raceData['proficiencies'])) {
            $this->importEntityProficiencies($race, $raceData['proficiencies']);
        }

        // Import languages if present
        if (isset($raceData['languages'])) {
            $this->importEntityLanguages($race, $raceData['languages']);
        }

        // Import conditions (immunities, advantages, resistances)
        if (isset($raceData['conditions'])) {
            $this->importEntityConditions($race, $raceData['conditions']);
        }

        // Import spells
        if (isset($raceData['spellcasting'])) {
            $this->importSpells($race, $raceData['spellcasting']);
        }

        // Import random tables from trait rolls (also links traits to tables)
        $this->importRandomTablesFromRolls($createdTraits, $raceData['traits'] ?? []);

        return $race;
    }

    /**
     * Import all modifiers at once (ability bonuses, choices, and resistances).
     * This ensures we don't clear modifiers between multiple imports.
     */
    private function importAllModifiers(
        Race $race,
        array $bonusesData,
        array $choicesData,
        array $resistancesData
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

    private function getOrCreateBaseRace(
        string $baseRaceName,
        string $sizeCode,
        int $speed,
        array $sources
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

        $baseRace = Race::create([
            'slug' => $this->generateSlug($baseRaceName),
            'name' => $baseRaceName,
            'size_id' => $size->id,
            'speed' => $speed,
            'parent_race_id' => null,
        ]);

        // Create source associations for base race
        $this->importEntitySources($baseRace, $sources);

        return $baseRace;
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
     * Generate a slug for a race, handling parent/subrace relationships.
     *
     * @param  string  $raceName  Full race name (e.g., "Dwarf (Hill)")
     * @param  string|null  $baseRaceName  Base race name if this is a subrace
     * @return string Generated slug (e.g., "dwarf-hill")
     */
    private function generateSlugForRace(string $raceName, ?string $baseRaceName): string
    {
        // If this is a base race (no base_race_name), just slug the name
        if (empty($baseRaceName)) {
            return $this->generateSlug($raceName);
        }

        // For subraces, extract the subrace portion
        // Format: "Dwarf (Hill)" or "Elf, High"

        // Try parentheses format first: "Dwarf (Hill)"
        if (preg_match('/^(.+?)\s*\((.+)\)$/', $raceName, $matches)) {
            $baseRaceName = trim($matches[1]);
            $subraceName = trim($matches[2]);

            return $this->generateSlug($subraceName, $this->generateSlug($baseRaceName));
        }

        // Try comma format: "Dwarf, Hill"
        if (str_contains($raceName, ',')) {
            [$baseRaceName, $subraceName] = array_map('trim', explode(',', $raceName, 2));

            return $this->generateSlug($subraceName, $this->generateSlug($baseRaceName));
        }

        // Fallback: just slug the full name
        return $this->generateSlug($raceName);
    }

    /**
     * Import race spells.
     */
    private function importSpells(Race $race, array $spellcastingData): void
    {
        if (empty($spellcastingData['spells'])) {
            return;
        }

        // Clear existing
        \Illuminate\Support\Facades\DB::table('entity_spells')
            ->where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->delete();

        // Get ability score
        $abilityScore = null;
        if (! empty($spellcastingData['ability'])) {
            $code = strtoupper(substr($spellcastingData['ability'], 0, 3));
            $abilityScore = \App\Models\AbilityScore::where('code', $code)->first();
        }

        foreach ($spellcastingData['spells'] as $spellData) {
            // Look up spell by name (try slug match)
            $spellSlug = Str::slug($spellData['spell_name']);
            $spell = \App\Models\Spell::where('slug', $spellSlug)->first();

            if ($spell) {
                \App\Models\EntitySpell::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'spell_id' => $spell->id,
                    'ability_score_id' => $abilityScore?->id,
                    'level_requirement' => $spellData['level_requirement'],
                    'usage_limit' => $spellData['usage_limit'],
                    'is_cantrip' => $spellData['is_cantrip'],
                ]);
            }
        }
    }
}

<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\EntityLanguage;
use App\Models\EntitySource;
use App\Models\Language;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\Race;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\Size;
use App\Models\Skill;
use App\Models\Source;
use App\Services\Importers\Concerns\ImportsProficiencies;
use App\Services\Importers\Concerns\ImportsSources;
use App\Services\Importers\Concerns\ImportsTraits;
use App\Services\Parsers\ItemTableDetector;
use App\Services\Parsers\ItemTableParser;
use App\Services\Parsers\RaceXmlParser;
use Illuminate\Support\Str;

class RaceImporter
{
    use ImportsProficiencies, ImportsSources, ImportsTraits;

    private array $createdBaseRaces = [];

    public function import(array $raceData): Race
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
        $slug = $this->generateRaceSlug($raceData['name'], $raceData['base_race_name'] ?? null);

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

        // Import ability bonuses as modifiers (including choices)
        $this->importAbilityBonuses($race, $raceData['ability_bonuses'] ?? [], $raceData['ability_choices'] ?? []);

        // Import proficiencies if present
        if (isset($raceData['proficiencies'])) {
            $this->importEntityProficiencies($race, $raceData['proficiencies']);
        }

        // Import languages if present
        if (isset($raceData['languages'])) {
            $this->importLanguages($race, $raceData['languages']);
        }

        // Import conditions (immunities, advantages, resistances)
        if (isset($raceData['conditions'])) {
            $this->importConditions($race, $raceData['conditions']);
        }

        // Import damage resistances
        if (isset($raceData['resistances'])) {
            $this->importResistances($race, $raceData['resistances']);
        }

        // Import spells
        if (isset($raceData['spellcasting'])) {
            $this->importSpells($race, $raceData['spellcasting']);
        }

        // Import random tables from trait rolls (also links traits to tables)
        $this->importRandomTablesFromTraits($createdTraits, $raceData['traits'] ?? []);

        return $race;
    }

    /**
     * Import sources for a race.
     * Creates entity_sources junction records for each source.
     */
    private function importSources(Race $race, array $sources): void
    {
        // Clear existing sources
        $race->sources()->delete();

        // Create new source associations
        foreach ($sources as $sourceData) {
            $source = Source::where('code', $sourceData['code'])->first();

            if ($source) {
                EntitySource::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'source_id' => $source->id,
                    'pages' => $sourceData['pages'] ?? null,
                ]);
            }
        }
    }

    private function importTraits(Race $race, array $traitsData): void
    {
        // Clear existing traits for this race
        $race->traits()->delete();

        foreach ($traitsData as $traitData) {
            $trait = \App\Models\CharacterTrait::create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
                'name' => $traitData['name'],
                'category' => $traitData['category'],
                'description' => $traitData['description'],
                'sort_order' => $traitData['sort_order'],
            ]);

            // Check for embedded tables in trait description
            $this->importTraitTables($trait, $traitData['description']);
        }
    }

    private function importTraitTables(\App\Models\CharacterTrait $trait, string $description): void
    {
        // Detect tables in trait description
        $detector = new ItemTableDetector;
        $tables = $detector->detectTables($description);

        if (empty($tables)) {
            return;
        }

        foreach ($tables as $tableData) {
            $parser = new ItemTableParser;
            $parsed = $parser->parse($tableData['text'], $tableData['dice_type'] ?? null);

            if (empty($parsed['rows'])) {
                continue; // Skip tables with no valid rows
            }

            $table = RandomTable::create([
                'reference_type' => \App\Models\CharacterTrait::class,
                'reference_id' => $trait->id,
                'table_name' => $parsed['table_name'],
                'dice_type' => $parsed['dice_type'],
            ]);

            foreach ($parsed['rows'] as $index => $row) {
                RandomTableEntry::create([
                    'random_table_id' => $table->id,
                    'roll_min' => $row['roll_min'],
                    'roll_max' => $row['roll_max'],
                    'result_text' => $row['result_text'],
                    'sort_order' => $index,
                ]);
            }
        }
    }

    private function importAbilityBonuses(Race $race, array $bonusesData, array $choicesData = []): void
    {
        // Clear existing ability score modifiers for this race
        $race->modifiers()->where('modifier_category', 'ability_score')->delete();

        // Import fixed bonuses (existing logic)
        foreach ($bonusesData as $bonusData) {
            // Map ability code to ability_score_id
            $abilityCode = strtoupper($bonusData['ability']);
            $abilityScore = AbilityScore::where('code', $abilityCode)->first();

            if (! $abilityScore) {
                continue; // Skip if ability score not found
            }

            Modifier::create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
                'modifier_category' => 'ability_score',
                'ability_score_id' => $abilityScore->id,
                'value' => $bonusData['value'],
                'is_choice' => false,
            ]);
        }

        // Import choice-based bonuses (NEW)
        foreach ($choicesData as $choiceData) {
            Modifier::create([
                'reference_type' => Race::class,
                'reference_id' => $race->id,
                'modifier_category' => 'ability_score',
                'ability_score_id' => null, // NULL for choices
                'value' => "+{$choiceData['value']}",
                'is_choice' => true,
                'choice_count' => $choiceData['choice_count'],
                'choice_constraint' => $choiceData['choice_constraint'],
            ]);
        }
    }

    public function importFromFile(string $filePath): int
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $xmlContent = file_get_contents($filePath);
        $parser = new RaceXmlParser;
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
            'slug' => Str::slug($baseRaceName),
            'name' => $baseRaceName,
            'size_id' => $size->id,
            'speed' => $speed,
            'parent_race_id' => null,
        ]);

        // Create source associations for base race
        $this->importSources($baseRace, $sources);

        return $baseRace;
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
                'proficiency_name' => $profData['name'], // Always store name as fallback
                'proficiency_type_id' => $profData['proficiency_type_id'] ?? null, // From parser
                'grants' => $profData['grants'] ?? true, // Races grant proficiency
            ];

            // Handle different proficiency types
            if ($profData['type'] === 'skill') {
                // Look up skill by name
                $skill = Skill::where('name', $profData['name'])->first();
                if ($skill) {
                    $proficiency['skill_id'] = $skill->id;
                }
            }

            Proficiency::create($proficiency);
        }
    }

    private function importLanguages(Race $race, array $languagesData): void
    {
        // Clear existing languages for this race
        $race->languages()->delete();

        foreach ($languagesData as $langData) {
            $isChoice = $langData['is_choice'] ?? false;

            // For choice slots, language_id is null
            if ($isChoice) {
                EntityLanguage::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'language_id' => null,
                    'is_choice' => true,
                ]);
            } else {
                // Look up language by slug for fixed languages
                $language = Language::where('slug', $langData['slug'])->first();

                if ($language) {
                    EntityLanguage::create([
                        'reference_type' => Race::class,
                        'reference_id' => $race->id,
                        'language_id' => $language->id,
                        'is_choice' => false,
                    ]);
                }
            }
        }
    }

    private function importRandomTablesFromTraits(array $createdTraits, array $traitsData): void
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
    private function generateRaceSlug(string $raceName, ?string $baseRaceName): string
    {
        // If this is a base race (no base_race_name), just slug the name
        if (empty($baseRaceName)) {
            return Str::slug($raceName);
        }

        // For subraces, extract the subrace portion
        // Format: "Dwarf (Hill)" or "Elf, High"

        // Try parentheses format first: "Dwarf (Hill)"
        if (preg_match('/^(.+?)\s*\((.+)\)$/', $raceName, $matches)) {
            $baseRaceName = trim($matches[1]);
            $subraceName = trim($matches[2]);

            return Str::slug($baseRaceName).'-'.Str::slug($subraceName);
        }

        // Try comma format: "Dwarf, Hill"
        if (str_contains($raceName, ',')) {
            [$baseRaceName, $subraceName] = array_map('trim', explode(',', $raceName, 2));

            return Str::slug($baseRaceName).'-'.Str::slug($subraceName);
        }

        // Fallback: just slug the full name
        return Str::slug($raceName);
    }

    /**
     * Import conditions (immunities, advantages, resistances).
     */
    private function importConditions(Race $race, array $conditionsData): void
    {
        // Clear existing
        \Illuminate\Support\Facades\DB::table('entity_conditions')
            ->where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->delete();

        foreach ($conditionsData as $conditionData) {
            // Look up condition by name (try slug match)
            $conditionSlug = Str::slug($conditionData['condition_name']);
            $condition = \App\Models\Condition::where('slug', $conditionSlug)->first();

            if ($condition) {
                \App\Models\EntityCondition::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'condition_id' => $condition->id,
                    'effect_type' => $conditionData['effect_type'],
                ]);
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

    /**
     * Import damage resistances as modifiers.
     */
    private function importResistances(Race $race, array $resistancesData): void
    {
        // Clear existing resistance modifiers
        $race->modifiers()->where('modifier_category', 'damage_resistance')->delete();

        foreach ($resistancesData as $resistanceData) {
            // Look up damage type by name
            $damageType = \App\Models\DamageType::where('name', $resistanceData['damage_type'])->first();

            if ($damageType) {
                Modifier::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'modifier_category' => 'damage_resistance',
                    'damage_type_id' => $damageType->id,
                    'value' => 'resistance',
                ]);
            }
        }
    }
}

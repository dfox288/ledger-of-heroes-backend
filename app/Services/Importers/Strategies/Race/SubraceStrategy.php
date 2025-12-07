<?php

namespace App\Services\Importers\Strategies\Race;

use App\Models\Race;
use App\Models\Size;
use App\Services\Importers\Concerns\GeneratesSlugs;
use App\Services\Importers\Strategies\AbstractImportStrategy;
use Illuminate\Support\Str;

class SubraceStrategy extends AbstractImportStrategy
{
    use GeneratesSlugs;

    /**
     * Subraces have a base_race_name but are not variants.
     */
    public function appliesTo(array $data): bool
    {
        return ! empty($data['base_race_name']) && empty($data['variant_of']);
    }

    /**
     * Enhance subrace data with parent resolution and compound slug.
     */
    public function enhance(array $data): array
    {
        $baseRaceName = $data['base_race_name'];

        // Find or create base race
        $baseRace = Race::where('name', $baseRaceName)->first();
        $isNewlyCreated = false;

        if (! $baseRace) {
            $baseRace = $this->createStubBaseRace($baseRaceName, $data);
            $isNewlyCreated = true;
            $this->incrementMetric('base_races_created');
        } else {
            $this->incrementMetric('base_races_resolved');
        }

        // Set parent_race_id
        $data['parent_race_id'] = $baseRace->id;

        // If base race was newly created, mark it for population with base traits
        // and pass the base race data for the importer to use
        if ($isNewlyCreated) {
            $data['_base_race_needs_population'] = true;
            $data['_base_race'] = $baseRace;

            // Pass base race data extracted from subrace (traits, modifiers, proficiencies)
            $data['_base_race_data'] = [
                'traits' => $data['base_traits'] ?? [],
                'ability_bonuses' => $this->extractBaseAbilityBonuses($data['ability_bonuses'] ?? []),
                'proficiencies' => $data['proficiencies'] ?? [],
                'resistances' => $data['resistances'] ?? [],
                'sources' => $data['sources'] ?? [],
                'languages' => $data['languages'] ?? [],
                'conditions' => $data['conditions'] ?? [],
            ];
        }

        // For subraces, use only subspecies traits (subrace-specific)
        // Base traits are inherited from parent via inherited_data in API
        if (isset($data['subrace_traits'])) {
            $data['traits'] = $data['subrace_traits'];
        }

        // For subraces, use only subrace-specific ability bonuses
        if (isset($data['ability_bonuses'])) {
            $data['ability_bonuses'] = $this->extractSubraceAbilityBonuses($data['ability_bonuses']);
        }

        // For subraces, clear shared data that belongs to base race
        // (resistances, proficiencies, languages, conditions are inherited)
        $data['resistances'] = [];
        $data['proficiencies'] = [];
        $data['languages'] = [];
        $data['conditions'] = [];

        // Extract subrace portion from name for slug generation
        $subraceName = $this->extractSubraceName($data['name'], $baseRaceName);

        // Generate compound slug using parent's ACTUAL slug (not re-slugified name)
        $data['slug'] = $baseRace->slug.'-'.Str::slug($subraceName);

        // Track metric
        $this->incrementMetric('subraces_processed');

        return $data;
    }

    /**
     * Extract the subrace portion from the full name.
     * e.g., "Dwarf (Hill)" -> "Hill", "Dwarf, Hill" -> "Hill"
     */
    private function extractSubraceName(string $fullName, string $baseName): string
    {
        // Try parentheses format first: "Dwarf (Hill)"
        if (preg_match('/\(([^)]+)\)/', $fullName, $matches)) {
            return trim($matches[1]);
        }

        // Try comma format: "Dwarf, Hill"
        if (str_contains($fullName, ',')) {
            [, $subraceName] = array_map('trim', explode(',', $fullName, 2));

            return $subraceName;
        }

        // Fallback: use full name
        return $fullName;
    }

    /**
     * Minimum ability score points for a race to be considered "complete".
     * A complete race has optional subraces (subrace_required = false).
     */
    private const COMPLETE_RACE_ABILITY_THRESHOLD = 3;

    /**
     * Create a minimal stub base race when referenced by subrace.
     */
    private function createStubBaseRace(string $name, array $subraceData): Race
    {
        if (empty($subraceData['size_code'])) {
            $this->addWarning("Cannot create stub base race '{$name}': subrace missing size_code");

            return Race::factory()->make(['id' => 0]); // Return invalid stub
        }

        $size = Size::where('code', $subraceData['size_code'])->first();
        $slug = Str::slug($name);

        // Generate full_slug with source prefix
        // Use sources from subrace data, fallback to PHB if not provided or empty
        $sources = ! empty($subraceData['sources'])
            ? $subraceData['sources']
            : [['code' => 'PHB', 'pages' => '']];
        $fullSlug = $this->generateFullSlug($slug, $sources);

        // Calculate base race ability points to determine if subraces are required
        // Base race only gets the first ability bonus (e.g., Dwarf gets Con +2)
        // Also include any ability choices that may be present on the base race
        $baseAbilityBonuses = $this->extractBaseAbilityBonuses($subraceData['ability_bonuses'] ?? []);
        $abilityChoices = $subraceData['ability_choices'] ?? [];
        $totalAbilityPoints = $this->calculateTotalAbilityPoints($baseAbilityBonuses, $abilityChoices);
        $subraceRequired = $totalAbilityPoints < self::COMPLETE_RACE_ABILITY_THRESHOLD;

        return Race::create([
            'name' => $name,
            'slug' => $slug,
            'full_slug' => $fullSlug,
            'size_id' => $size->id,
            'speed' => $subraceData['speed'] ?? 30,
            'description' => "Base race for {$name} subraces.",
            'subrace_required' => $subraceRequired,
        ]);
    }

    /**
     * Calculate total ability score points from fixed bonuses and choices.
     *
     * @param  array  $bonuses  Fixed ability bonuses [{ability: 'Str', value: 2}, ...]
     * @param  array  $choices  Choice-based bonuses [{choice_count: 2, value: 1}, ...]
     * @return int Total ability score points
     */
    private function calculateTotalAbilityPoints(array $bonuses, array $choices = []): int
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

    /**
     * Extract base race ability bonuses (typically the first/primary bonus).
     *
     * D&D pattern: Base race gets primary bonus (e.g., Dwarf Con +2),
     * subrace gets secondary bonus (e.g., Hill Dwarf Wis +1).
     *
     * @param  array  $bonuses  All ability bonuses from XML
     * @return array Base race bonuses (first bonus only)
     */
    private function extractBaseAbilityBonuses(array $bonuses): array
    {
        // If there's only one bonus, it belongs to base race
        if (count($bonuses) <= 1) {
            return $bonuses;
        }

        // First bonus goes to base race (e.g., "Con +2" for Dwarf)
        return [array_shift($bonuses)];
    }

    /**
     * Extract subrace-specific ability bonuses (secondary bonuses).
     *
     * @param  array  $bonuses  All ability bonuses from XML
     * @return array Subrace-specific bonuses (all but first)
     */
    private function extractSubraceAbilityBonuses(array $bonuses): array
    {
        // If there's only one bonus, subrace has none (it's on base race)
        if (count($bonuses) <= 1) {
            return [];
        }

        // Skip first bonus (belongs to base race), return rest
        array_shift($bonuses);

        return $bonuses;
    }
}

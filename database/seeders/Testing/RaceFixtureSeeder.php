<?php

namespace Database\Seeders\Testing;

use App\Models\AbilityScore;
use App\Models\CharacterTrait;
use App\Models\EntitySource;
use App\Models\Modifier;
use App\Models\Race;
use App\Models\Size;
use App\Models\Source;

class RaceFixtureSeeder extends FixtureSeeder
{
    protected function fixturePath(): string
    {
        return 'tests/fixtures/entities/races.json';
    }

    protected function model(): string
    {
        return Race::class;
    }

    /**
     * Minimum ability score points for a race to be considered "complete".
     * A complete race has optional subraces (subrace_required = false).
     */
    private const COMPLETE_RACE_ABILITY_THRESHOLD = 3;

    protected function createFromFixture(array $item): void
    {
        // Resolve size by code
        $size = Size::where('code', $item['size'])->first();

        // Resolve parent race by slug (if exists)
        $parentRace = null;
        if (! empty($item['parent_race_slug'])) {
            $parentRace = Race::where('slug', $item['parent_race_slug'])->first();
        }

        // Calculate total ability points to determine if subrace is required
        // Subraces (has parent) never require nested subraces
        // Base races with 3+ ability points have optional subraces
        $isSubrace = ! empty($parentRace);
        $totalAbilityPoints = $this->calculateTotalAbilityPoints(
            $item['ability_bonuses'] ?? [],
            $item['ability_choices'] ?? []
        );
        $hasCompleteAbilityScores = $totalAbilityPoints >= self::COMPLETE_RACE_ABILITY_THRESHOLD;
        $subraceRequired = ! $isSubrace && ! $hasCompleteAbilityScores;

        // Generate source-prefixed slug
        $slug = $item['slug'];
        if (! empty($item['source'])) {
            $sourceCode = strtolower($item['source']);
            $slug = $sourceCode.':'.$item['slug'];
        }

        // Create race
        $race = Race::create([
            'name' => $item['name'],
            'slug' => $slug,
            'size_id' => $size?->id,
            'speed' => $item['speed'],
            'parent_race_id' => $parentRace?->id,
            'subrace_required' => $subraceRequired,
        ]);

        // Handle ability bonuses (create Modifiers)
        if (! empty($item['ability_bonuses'])) {
            foreach ($item['ability_bonuses'] as $bonus) {
                $abilityScore = null;
                if (! empty($bonus['ability'])) {
                    $abilityScore = AbilityScore::where('code', $bonus['ability'])->first();
                }

                Modifier::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'modifier_category' => 'ability_score',
                    'ability_score_id' => $abilityScore?->id,
                    'value' => $bonus['bonus'],
                    'is_choice' => $bonus['is_choice'] ?? false,
                ]);
            }
        }

        // Handle traits (create CharacterTraits)
        if (! empty($item['traits'])) {
            foreach ($item['traits'] as $trait) {
                CharacterTrait::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'name' => $trait['name'],
                    'category' => $trait['category'],
                    'description' => $trait['description'],
                ]);
            }
        }

        // Create entity source (if source is provided)
        if (! empty($item['source'])) {
            $source = Source::where('code', $item['source'])->first();
            if ($source) {
                EntitySource::create([
                    'reference_type' => Race::class,
                    'reference_id' => $race->id,
                    'source_id' => $source->id,
                    'pages' => $item['pages'] ?? null,
                ]);
            }
        }
    }

    /**
     * Calculate total ability score points from bonuses and choices.
     *
     * @param  array  $bonuses  Ability bonuses [{ability: 'DEX', bonus: 2, is_choice: false}, ...]
     * @param  array  $choices  Ability choices [{choice_count: 2, value: 1}, ...] (if fixtures include them)
     * @return int Total ability score points
     */
    private function calculateTotalAbilityPoints(array $bonuses, array $choices = []): int
    {
        $total = 0;

        // Sum fixed ability bonuses
        foreach ($bonuses as $bonus) {
            $total += abs((int) ($bonus['bonus'] ?? 0));
        }

        // Sum choice-based ability bonuses (if fixtures include them)
        foreach ($choices as $choice) {
            $choiceCount = (int) ($choice['choice_count'] ?? 1);
            $value = abs((int) ($choice['value'] ?? 0));
            $total += $choiceCount * $value;
        }

        return $total;
    }
}

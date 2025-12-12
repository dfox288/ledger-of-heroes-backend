<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Race;
use Illuminate\Support\Collection;

class AbilityBonusService
{
    /**
     * Get all ability bonuses for a character.
     *
     * @return array{bonuses: Collection, totals: array<string, int>}
     */
    public function getBonuses(Character $character): array
    {
        $bonuses = collect();

        // Collect from all sources
        $bonuses = $bonuses->merge($this->getRaceBonuses($character));
        $bonuses = $bonuses->merge($this->getFeatBonuses($character));

        // Calculate totals from resolved bonuses only
        $totals = $this->calculateTotals($bonuses);

        return [
            'bonuses' => $bonuses,
            'totals' => $totals,
        ];
    }

    /**
     * Get ability bonuses from race (including parent race for subraces).
     *
     * Returns fixed bonuses and resolved choice bonuses.
     *
     * Note: Since choice data moved to entity_choices, all remaining
     * entity_modifiers rows are fixed (non-choice) by definition.
     */
    private function getRaceBonuses(Character $character): Collection
    {
        if (! $character->race) {
            return collect();
        }

        $bonuses = collect();
        $race = $character->race;

        // Load fixed modifiers from race and parent race (if subrace)
        // All entity_modifiers are now fixed since choices moved to entity_choices
        $raceModifiers = $this->getAbilityModifiers($race);
        if ($race->parent_race_id && $race->parent) {
            $raceModifiers = $raceModifiers->merge($this->getAbilityModifiers($race->parent));
        }

        // Pre-load all ability scores to avoid N+1 queries
        $abilityScores = AbilityScore::all()->keyBy('code');

        // Process fixed modifiers (all entity_modifiers are now fixed)
        foreach ($raceModifiers as $modifier) {
            if (! $modifier->abilityScore) {
                continue;
            }

            $bonuses->push($this->buildBonus(
                sourceType: 'race',
                sourceName: $race->name,
                sourceSlug: $race->slug,
                abilityCode: $modifier->abilityScore->code,
                abilityName: $modifier->abilityScore->name,
                value: (int) $modifier->value,
                isChoice: false,
            ));
        }

        // Get resolved ability score choices from character_ability_scores
        // These are linked via choice_group to entity_choices
        $resolvedChoices = $character->abilityScores()
            ->where('source', 'race')
            ->get();

        foreach ($resolvedChoices as $choice) {
            $abilityScore = $abilityScores->get($choice->ability_score_code);
            if (! $abilityScore) {
                continue;
            }

            $bonuses->push($this->buildBonus(
                sourceType: 'race',
                sourceName: $race->name,
                sourceSlug: $race->slug,
                abilityCode: $choice->ability_score_code,
                abilityName: $abilityScore->name,
                value: $choice->bonus,
                isChoice: true,
                choiceResolved: true,
                choiceGroup: $choice->choice_group,
            ));
        }

        return $bonuses;
    }

    /**
     * Get ability bonuses from feats.
     *
     * Returns fixed bonuses from feats selected via CharacterFeature.
     */
    private function getFeatBonuses(Character $character): Collection
    {
        $bonuses = collect();

        // Get feats via CharacterFeature
        $featFeatures = $character->features()
            ->where('feature_type', Feat::class)
            ->with('feature.modifiers.abilityScore')
            ->get();

        foreach ($featFeatures as $characterFeature) {
            $feat = $characterFeature->feature;

            // Fallback: If feature_id is null but slug exists (legacy data),
            // look up the feat by slug. This handles CharacterFeature records
            // created before the fix that populated feature_id.
            if (! $feat && $characterFeature->feature_slug) {
                $feat = Feat::where('slug', $characterFeature->feature_slug)
                    ->with('modifiers.abilityScore')
                    ->first();
            }

            if (! $feat) {
                continue;
            }

            // All entity_modifiers are now fixed since choices moved to entity_choices
            $abilityModifiers = $feat->modifiers
                ->where('modifier_category', 'ability_score');

            foreach ($abilityModifiers as $modifier) {
                if (! $modifier->abilityScore) {
                    continue;
                }

                $bonuses->push($this->buildBonus(
                    sourceType: 'feat',
                    sourceName: $feat->name,
                    sourceSlug: $feat->slug,
                    abilityCode: $modifier->abilityScore->code,
                    abilityName: $modifier->abilityScore->name,
                    value: (int) $modifier->value,
                    isChoice: false,
                ));
            }
        }

        return $bonuses;
    }

    /**
     * Get ability score modifiers for a race.
     */
    private function getAbilityModifiers(Race $race): Collection
    {
        return Modifier::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('modifier_category', 'ability_score')
            ->with('abilityScore')
            ->get();
    }

    /**
     * Calculate totals from resolved bonuses only.
     *
     * @return array<string, int>
     */
    private function calculateTotals(Collection $bonuses): array
    {
        $totals = [
            'STR' => 0,
            'DEX' => 0,
            'CON' => 0,
            'INT' => 0,
            'WIS' => 0,
            'CHA' => 0,
        ];

        // Sum only resolved bonuses (is_choice=false OR choice_resolved=true)
        foreach ($bonuses as $bonus) {
            if (! $bonus['is_choice'] || ($bonus['choice_resolved'] ?? false)) {
                $totals[$bonus['ability_code']] += $bonus['value'];
            }
        }

        return $totals;
    }

    /**
     * Build a bonus array structure.
     */
    private function buildBonus(
        string $sourceType,
        string $sourceName,
        string $sourceSlug,
        string $abilityCode,
        string $abilityName,
        int $value,
        bool $isChoice,
        ?bool $choiceResolved = null,
        ?string $choiceGroup = null,
    ): array {
        $bonus = [
            'source_type' => $sourceType,
            'source_name' => $sourceName,
            'source_slug' => $sourceSlug,
            'ability_code' => $abilityCode,
            'ability_name' => $abilityName,
            'value' => $value,
            'is_choice' => $isChoice,
        ];

        if ($isChoice) {
            $bonus['choice_resolved'] = $choiceResolved;
            $bonus['choice_group'] = $choiceGroup;
        }

        return $bonus;
    }
}

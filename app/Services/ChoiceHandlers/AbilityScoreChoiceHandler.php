<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\EntityChoice;
use App\Models\Race;
use Illuminate\Support\Collection;

class AbilityScoreChoiceHandler extends AbstractChoiceHandler
{
    public function getType(): string
    {
        return 'ability_score';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        if (! $character->race_slug) {
            return $choices;
        }

        // Load race with ability score choices
        if (! $character->relationLoaded('race')) {
            $character->load('race');
        }

        // Verify race was loaded (slug exists but race record might not)
        if (! $character->race) {
            return $choices;
        }

        // Get ability score choices from race (and parent race if subrace)
        // Returns [EntityChoice, Race] tuples to track source entity
        $entityChoicesWithSource = $this->getAbilityScoreChoices($character);

        foreach ($entityChoicesWithSource as $choiceWithSource) {
            $choice = $this->buildPendingChoice($character, $choiceWithSource);
            if ($choice) {
                $choices->push($choice);
            }
        }

        return $choices;
    }

    /**
     * Get ability score choices from race (and parent race if subrace).
     *
     * Returns an array of [EntityChoice, Race] tuples to track the source entity.
     *
     * @return Collection<int, array{0: EntityChoice, 1: Race}>
     */
    private function getAbilityScoreChoices(Character $character): Collection
    {
        $choices = collect();

        // Get from race
        $raceChoices = $character->race->abilityScoreChoices()->get();
        foreach ($raceChoices as $choice) {
            $choices->push([$choice, $character->race]);
        }

        // Get from parent race if subrace
        if ($character->race->parent_race_id && $character->race->parent) {
            $parentChoices = $character->race->parent->abilityScoreChoices()->get();
            foreach ($parentChoices as $choice) {
                $choices->push([$choice, $character->race->parent]);
            }
        }

        return $choices;
    }

    /**
     * @param  array{0: EntityChoice, 1: Race}  $choiceWithSource
     */
    private function buildPendingChoice(Character $character, array $choiceWithSource): ?PendingChoice
    {
        [$entityChoice, $sourceRace] = $choiceWithSource;

        // Get all ability score options
        $allAbilities = AbilityScore::all();

        // Get already-selected ability codes for this choice group
        $selected = $character->abilityScores()
            ->where('choice_group', $entityChoice->choice_group)
            ->pluck('ability_score_code')
            ->toArray();

        $quantity = $entityChoice->quantity ?? 1;
        $remaining = $quantity - count($selected);

        // Get bonus value from constraints
        $bonusValue = $this->parseBonusValue($entityChoice->constraints['value'] ?? '+1');
        $constraint = $entityChoice->constraints['constraint'] ?? 'different';

        // Build options list
        $options = $allAbilities
            ->map(fn ($ability) => [
                'code' => $ability->code,
                'name' => $ability->name,
            ])
            ->values()
            ->all();

        // Use the source race's slug for unique ID generation
        // This ensures inherited choices from parent races have distinct IDs
        return new PendingChoice(
            id: $this->generateChoiceId(
                'ability_score',
                'race',
                $sourceRace->slug,
                $entityChoice->level_granted ?? 1,
                $entityChoice->choice_group
            ),
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: $sourceRace->name,
            levelGranted: $entityChoice->level_granted ?? 1,
            required: $entityChoice->is_required ?? true,
            quantity: $quantity,
            remaining: $remaining,
            selected: $selected,
            options: $options,
            optionsEndpoint: null,
            metadata: [
                'choice_group' => $entityChoice->choice_group,
                'bonus_value' => $bonusValue,
                'choice_constraint' => $constraint,
                'source_race_slug' => $sourceRace->slug,
            ],
        );
    }

    /**
     * Parse bonus value from string like '+1' or '+2' to integer.
     */
    private function parseBonusValue(string $value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $choiceGroup = $parsed['group'];

        $selected = $selection['selected'] ?? [];
        if (empty($selected)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Validate quantity
        if (count($selected) !== $choice->quantity) {
            throw new InvalidSelectionException(
                $choice->id,
                implode(',', $selected),
                "Must select exactly {$choice->quantity} ability score(s)"
            );
        }

        // Validate constraint (e.g., 'different' means no duplicates)
        $constraint = $choice->metadata['choice_constraint'] ?? null;
        if ($constraint === 'different' && count($selected) !== count(array_unique($selected))) {
            throw new InvalidSelectionException(
                $choice->id,
                implode(',', $selected),
                'Selected ability scores must be different'
            );
        }

        // Validate ability codes exist
        $validCodes = AbilityScore::pluck('code')->toArray();
        foreach ($selected as $code) {
            if (! in_array($code, $validCodes)) {
                throw new InvalidSelectionException($choice->id, $code, "Invalid ability score code: {$code}");
            }
        }

        $bonusValue = $choice->metadata['bonus_value'] ?? 1;

        // Delete existing choices for this choice group
        $character->abilityScores()->where('choice_group', $choiceGroup)->delete();

        // Create new choices
        foreach ($selected as $code) {
            $character->abilityScores()->create([
                'ability_score_code' => $code,
                'bonus' => $bonusValue,
                'source' => 'race',
                'choice_group' => $choiceGroup,
            ]);
        }

        $character->load('abilityScores');
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        return true;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $choiceGroup = $parsed['group'];

        $character->abilityScores()->where('choice_group', $choiceGroup)->delete();
        $character->load('abilityScores');
    }
}

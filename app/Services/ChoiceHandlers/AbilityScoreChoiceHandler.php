<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\Modifier;
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

        // Load race with modifiers
        if (! $character->relationLoaded('race')) {
            $character->load('race.modifiers.abilityScore', 'race.parent.modifiers.abilityScore');
        }

        // Get choice modifiers from race (and parent race if subrace)
        $modifiers = $this->getChoiceModifiers($character);

        foreach ($modifiers as $modifier) {
            $choice = $this->buildPendingChoice($character, $modifier);
            if ($choice) {
                $choices->push($choice);
            }
        }

        return $choices;
    }

    private function getChoiceModifiers(Character $character): Collection
    {
        $modifiers = collect();

        // Get from race
        $raceModifiers = $character->race->modifiers()
            ->where('modifier_category', 'ability_score')
            ->where('is_choice', true)
            ->get();
        $modifiers = $modifiers->merge($raceModifiers);

        // Get from parent race if subrace
        if ($character->race->parent_race_id && $character->race->parent) {
            $parentModifiers = $character->race->parent->modifiers()
                ->where('modifier_category', 'ability_score')
                ->where('is_choice', true)
                ->get();
            $modifiers = $modifiers->merge($parentModifiers);
        }

        return $modifiers;
    }

    private function buildPendingChoice(Character $character, Modifier $modifier): ?PendingChoice
    {
        // Get all ability score options
        $allAbilities = AbilityScore::all();

        // Get already-selected ability codes for this modifier
        $selected = $character->abilityScores()
            ->where('modifier_id', $modifier->id)
            ->pluck('ability_score_code')
            ->toArray();

        $quantity = $modifier->choice_count ?? 1;
        $remaining = $quantity - count($selected);

        // Build options list
        $options = $allAbilities
            ->map(fn ($ability) => [
                'code' => $ability->code,
                'name' => $ability->name,
            ])
            ->values()
            ->all();

        return new PendingChoice(
            id: $this->generateChoiceId(
                'ability_score',
                'race',
                $character->race->full_slug,
                1,
                'modifier_'.$modifier->id
            ),
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: $character->race->name,
            levelGranted: 1,
            required: true,
            quantity: $quantity,
            remaining: $remaining,
            selected: $selected,
            options: $options,
            optionsEndpoint: null,
            metadata: [
                'modifier_id' => $modifier->id,
                'bonus_value' => (int) $modifier->value,
                'choice_constraint' => $modifier->choice_constraint,
            ],
        );
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $modifierId = (int) str_replace('modifier_', '', $parsed['group']);

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

        // Delete existing choices for this modifier
        $character->abilityScores()->where('modifier_id', $modifierId)->delete();

        // Create new choices
        foreach ($selected as $code) {
            $character->abilityScores()->create([
                'ability_score_code' => $code,
                'bonus' => $bonusValue,
                'source' => 'race',
                'modifier_id' => $modifierId,
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
        $modifierId = (int) str_replace('modifier_', '', $parsed['group']);

        $character->abilityScores()->where('modifier_id', $modifierId)->delete();
        $character->load('abilityScores');
    }
}

<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class AsiChoiceResult
{
    /**
     * @param  string  $choiceType  Either 'feat' or 'ability_increase'
     * @param  int  $asiChoicesRemaining  Remaining ASI choices after this one
     * @param  array<string, int>  $abilityIncreases  Ability score increases applied (e.g., ['CON' => 1])
     * @param  array<string, int>  $newAbilityScores  All 6 ability scores after changes
     * @param  array{id: int, name: string, slug: string}|null  $feat  Feat taken, or null if ability increase
     * @param  array<string>  $proficienciesGained  Names of proficiencies granted by feat
     * @param  array<array{id: int, name: string, slug: string}>  $spellsGained  Spells granted by feat
     */
    public function __construct(
        public string $choiceType,
        public int $asiChoicesRemaining,
        public array $abilityIncreases,
        public array $newAbilityScores,
        public ?array $feat,
        public array $proficienciesGained,
        public array $spellsGained,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'choice_type' => $this->choiceType,
            'asi_choices_remaining' => $this->asiChoicesRemaining,
            'changes' => [
                'feat' => $this->feat,
                'ability_increases' => $this->abilityIncreases,
                'proficiencies_gained' => $this->proficienciesGained,
                'spells_gained' => $this->spellsGained,
            ],
            'new_ability_scores' => $this->newAbilityScores,
        ];
    }
}

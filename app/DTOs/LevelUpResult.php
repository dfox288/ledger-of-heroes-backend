<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class LevelUpResult
{
    /**
     * @param  int  $previousLevel  Level before level-up
     * @param  int  $newLevel  Level after level-up
     * @param  int  $hpIncrease  HP gained from level-up (0 if using choice system)
     * @param  int  $newMaxHp  New max HP after level-up
     * @param  array<array{id: int, name: string, description: string|null}>  $featuresGained  Class features gained
     * @param  array<string, int>  $spellSlots  Current spell slots by level
     * @param  bool  $asiPending  Whether an ASI/Feat choice is pending
     * @param  bool  $hpChoicePending  Whether an HP choice (roll/average) is pending
     */
    public function __construct(
        public int $previousLevel,
        public int $newLevel,
        public int $hpIncrease,
        public int $newMaxHp,
        public array $featuresGained,
        public array $spellSlots,
        public bool $asiPending,
        public bool $hpChoicePending = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'previous_level' => $this->previousLevel,
            'new_level' => $this->newLevel,
            'hp_increase' => $this->hpIncrease,
            'new_max_hp' => $this->newMaxHp,
            'features_gained' => $this->featuresGained,
            'spell_slots' => $this->spellSlots,
            'asi_pending' => $this->asiPending,
            'hp_choice_pending' => $this->hpChoicePending,
        ];
    }
}

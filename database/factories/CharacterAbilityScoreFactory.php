<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterAbilityScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CharacterAbilityScore>
 */
class CharacterAbilityScoreFactory extends Factory
{
    protected $model = CharacterAbilityScore::class;

    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'ability_score_code' => fn () => fake()->randomElement(['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA']),
            'bonus' => fake()->randomElement([1, 2]),
            'source' => 'race',
            'modifier_id' => null,
        ];
    }

    public function fromRace(): static
    {
        return $this->state(fn () => ['source' => 'race']);
    }

    public function fromFeat(): static
    {
        return $this->state(fn () => ['source' => 'feat']);
    }

    public function fromAsi(): static
    {
        return $this->state(fn () => ['source' => 'asi']);
    }

    public function strength(int $bonus = 2): static
    {
        return $this->state(fn () => [
            'ability_score_code' => 'STR',
            'bonus' => $bonus,
        ]);
    }

    public function dexterity(int $bonus = 2): static
    {
        return $this->state(fn () => [
            'ability_score_code' => 'DEX',
            'bonus' => $bonus,
        ]);
    }

    public function constitution(int $bonus = 2): static
    {
        return $this->state(fn () => [
            'ability_score_code' => 'CON',
            'bonus' => $bonus,
        ]);
    }

    public function intelligence(int $bonus = 2): static
    {
        return $this->state(fn () => [
            'ability_score_code' => 'INT',
            'bonus' => $bonus,
        ]);
    }

    public function wisdom(int $bonus = 2): static
    {
        return $this->state(fn () => [
            'ability_score_code' => 'WIS',
            'bonus' => $bonus,
        ]);
    }

    public function charisma(int $bonus = 2): static
    {
        return $this->state(fn () => [
            'ability_score_code' => 'CHA',
            'bonus' => $bonus,
        ]);
    }
}

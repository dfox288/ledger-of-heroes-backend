<?php

namespace Database\Factories;

use App\Models\Monster;
use App\Models\MonsterSpellcasting;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonsterSpellcastingFactory extends Factory
{
    protected $model = MonsterSpellcasting::class;

    public function definition(): array
    {
        return [
            'monster_id' => Monster::factory(),
            'description' => fake()->sentence(),
            'spell_slots' => '0,3,2,1',
            'spellcasting_ability' => fake()->randomElement(['Charisma', 'Intelligence', 'Wisdom']),
            'spell_save_dc' => fake()->numberBetween(10, 20),
            'spell_attack_bonus' => fake()->numberBetween(2, 12),
        ];
    }
}

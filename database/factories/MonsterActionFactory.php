<?php

namespace Database\Factories;

use App\Models\Monster;
use App\Models\MonsterAction;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonsterActionFactory extends Factory
{
    protected $model = MonsterAction::class;

    public function definition(): array
    {
        return [
            'monster_id' => Monster::factory(),
            'action_type' => fake()->randomElement(['action', 'reaction', 'bonus_action']),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'attack_data' => null,
            'recharge' => null,
            'sort_order' => 0,
        ];
    }
}

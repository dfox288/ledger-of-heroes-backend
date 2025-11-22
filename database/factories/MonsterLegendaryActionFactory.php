<?php

namespace Database\Factories;

use App\Models\Monster;
use App\Models\MonsterLegendaryAction;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonsterLegendaryActionFactory extends Factory
{
    protected $model = MonsterLegendaryAction::class;

    public function definition(): array
    {
        return [
            'monster_id' => Monster::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'action_cost' => fake()->numberBetween(1, 3),
            'is_lair_action' => false,
            'attack_data' => null,
            'recharge' => null,
            'sort_order' => 0,
        ];
    }
}

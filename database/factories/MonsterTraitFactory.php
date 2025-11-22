<?php

namespace Database\Factories;

use App\Models\Monster;
use App\Models\MonsterTrait;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonsterTraitFactory extends Factory
{
    protected $model = MonsterTrait::class;

    public function definition(): array
    {
        return [
            'monster_id' => Monster::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'attack_data' => null,
            'sort_order' => 0,
        ];
    }
}

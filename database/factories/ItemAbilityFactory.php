<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemAbility;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemAbilityFactory extends Factory
{
    protected $model = ItemAbility::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'ability_type' => fake()->randomElement(['action', 'bonus_action', 'passive', 'spell']),
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function forItem(Item $item): static
    {
        return $this->state(fn (array $attributes) => [
            'item_id' => $item->id,
        ]);
    }
}

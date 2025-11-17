<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemType;
use App\Models\ItemRarity;
use App\Models\SourceBook;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'item_type_id' => ItemType::inRandomOrder()->first()?->id ?? ItemType::factory(),
            'rarity_id' => ItemRarity::inRandomOrder()->first()?->id ?? ItemRarity::factory(),
            'weight_lbs' => $this->faker->randomFloat(2, 0.1, 50),
            'value_gp' => $this->faker->randomFloat(2, 1, 1000),
            'description' => $this->faker->paragraph(),
            'attunement_required' => $this->faker->boolean(20),
            'attunement_requirements' => null,
            'source_book_id' => SourceBook::inRandomOrder()->first()?->id ?? SourceBook::factory(),
            'source_page' => $this->faker->numberBetween(1, 350),
        ];
    }

    public function requiresAttunement(): static
    {
        return $this->state(fn (array $attributes) => [
            'attunement_required' => true,
            'attunement_requirements' => 'by a spellcaster',
        ]);
    }
}

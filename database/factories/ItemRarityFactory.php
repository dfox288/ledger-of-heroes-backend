<?php

namespace Database\Factories;

use App\Models\ItemRarity;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemRarityFactory extends Factory
{
    protected $model = ItemRarity::class;

    public function definition(): array
    {
        $rarities = [
            ['code' => 'common', 'name' => 'Common'],
            ['code' => 'uncommon', 'name' => 'Uncommon'],
            ['code' => 'rare', 'name' => 'Rare'],
            ['code' => 'very_rare', 'name' => 'Very Rare'],
            ['code' => 'legendary', 'name' => 'Legendary'],
        ];

        $randomRarity = $this->faker->randomElement($rarities);

        return [
            'code' => $randomRarity['code'],
            'name' => $randomRarity['name'],
        ];
    }
}

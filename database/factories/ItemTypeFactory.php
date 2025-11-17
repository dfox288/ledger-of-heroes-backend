<?php

namespace Database\Factories;

use App\Models\ItemType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemTypeFactory extends Factory
{
    protected $model = ItemType::class;

    public function definition(): array
    {
        $types = [
            ['code' => 'M', 'name' => 'Melee Weapon', 'category' => 'weapon'],
            ['code' => 'R', 'name' => 'Ranged Weapon', 'category' => 'weapon'],
            ['code' => 'A', 'name' => 'Ammunition', 'category' => 'weapon'],
            ['code' => 'LA', 'name' => 'Light Armor', 'category' => 'armor'],
            ['code' => 'G', 'name' => 'Adventuring Gear', 'category' => 'gear'],
        ];

        $randomType = $this->faker->randomElement($types);

        return [
            'code' => $randomType['code'],
            'name' => $randomType['name'],
            'category' => $randomType['category'],
        ];
    }
}

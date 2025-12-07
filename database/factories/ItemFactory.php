<?php

namespace Database\Factories;

use App\Models\DamageType;
use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name);

        return [
            'name' => ucwords($name),
            'slug' => $slug,
            'full_slug' => 'test:'.$slug,
            'item_type_id' => ItemType::where('code', 'G')->first()->id,
            'rarity' => 'common',
            'requires_attunement' => false,
            'is_magic' => false,
            'cost_cp' => fake()->numberBetween(1, 50000),
            'weight' => fake()->randomFloat(2, 0.1, 50),
            'description' => fake()->paragraph(),
        ];
    }

    public function weapon(): static
    {
        return $this->state(function (array $attributes) {
            $damageType = DamageType::whereIn('name', ['Slashing', 'Piercing', 'Bludgeoning'])->inRandomOrder()->first();
            $weaponType = ItemType::whereIn('code', ['M', 'R'])->inRandomOrder()->first();

            return [
                'item_type_id' => $weaponType->id,
                'damage_dice' => fake()->randomElement(['1d4', '1d6', '1d8', '1d10', '1d12', '2d6']),
                'damage_type_id' => $damageType->id,
                'range_normal' => $weaponType->code === 'R' ? 80 : null,
                'range_long' => $weaponType->code === 'R' ? 320 : null,
            ];
        });
    }

    public function armor(): static
    {
        return $this->state(function (array $attributes) {
            $armorType = ItemType::whereIn('code', ['LA', 'MA', 'HA'])->inRandomOrder()->first();

            return [
                'item_type_id' => $armorType->id,
                'armor_class' => fake()->numberBetween(11, 18),
                'strength_requirement' => $armorType->code === 'HA' ? 13 : null,
                'stealth_disadvantage' => $armorType->code === 'HA',
            ];
        });
    }

    public function magic(): static
    {
        return $this->state(fn (array $attributes) => [
            'rarity' => fake()->randomElement(['uncommon', 'rare', 'very rare', 'legendary']),
            'requires_attunement' => fake()->boolean(60),
            'is_magic' => true,
        ]);
    }

    public function versatile(): static
    {
        return $this->state(fn (array $attributes) => [
            'damage_dice' => '1d8',
            'versatile_damage' => '1d10',
        ]);
    }
}

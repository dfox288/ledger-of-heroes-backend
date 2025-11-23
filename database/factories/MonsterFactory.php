<?php

namespace Database\Factories;

use App\Models\Monster;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonsterFactory extends Factory
{
    protected $model = Monster::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->slug(),
            'sort_name' => fake()->optional()->words(2, true),
            'size_id' => fn () => Size::inRandomOrder()->first()->id ?? 1,
            'type' => fake()->randomElement(['beast', 'humanoid', 'dragon', 'undead', 'aberration']),
            'alignment' => fake()->randomElement(['Lawful Good', 'Neutral Evil', 'Chaotic Neutral', null]),
            'armor_class' => fake()->numberBetween(10, 22),
            'armor_type' => fake()->optional()->randomElement(['natural armor', 'plate mail']),
            'hit_points_average' => fake()->numberBetween(10, 500),
            'hit_dice' => fake()->numberBetween(1, 30).'d'.fake()->randomElement([6, 8, 10, 12]).'+'.fake()->numberBetween(0, 50),
            'speed_walk' => fake()->numberBetween(0, 60),
            'speed_fly' => fake()->optional()->numberBetween(30, 120),
            'speed_swim' => fake()->optional()->numberBetween(20, 60),
            'speed_burrow' => fake()->optional()->numberBetween(10, 30),
            'speed_climb' => fake()->optional()->numberBetween(20, 40),
            'can_hover' => fake()->boolean(20),
            'strength' => fake()->numberBetween(1, 30),
            'dexterity' => fake()->numberBetween(1, 30),
            'constitution' => fake()->numberBetween(1, 30),
            'intelligence' => fake()->numberBetween(1, 30),
            'wisdom' => fake()->numberBetween(1, 30),
            'charisma' => fake()->numberBetween(1, 30),
            'challenge_rating' => fake()->randomElement(['0', '1/8', '1/4', '1/2', '1', '5', '10', '20']),
            'experience_points' => fake()->numberBetween(10, 50000),
            'passive_perception' => fake()->numberBetween(6, 25),
            'description' => fake()->optional()->paragraph(),
            'is_npc' => fake()->boolean(10), // 10% chance of being NPC
        ];
    }
}

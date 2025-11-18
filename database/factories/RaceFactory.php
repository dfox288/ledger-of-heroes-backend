<?php

namespace Database\Factories;

use App\Models\Size;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Race>
 */
class RaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'size_id' => Size::where('code', 'M')->first()->id,
            'speed' => 30,
            'description' => fake()->paragraph(),
            'source_id' => Source::where('code', 'PHB')->first()->id,
            'source_pages' => '20',
            'parent_race_id' => null,
        ];
    }
}

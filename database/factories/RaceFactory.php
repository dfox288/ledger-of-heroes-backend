<?php

namespace Database\Factories;

use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $name = fake()->unique()->word();

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'size_id' => Size::where('code', 'M')->first()->id,
            'speed' => 30,
            'parent_race_id' => null,
        ];
    }

    /**
     * Create a race with flying speed.
     */
    public function withFlySpeed(int $speed = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'fly_speed' => $speed,
        ]);
    }

    /**
     * Create a race with swimming speed.
     */
    public function withSwimSpeed(int $speed = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'swim_speed' => $speed,
        ]);
    }

    /**
     * Create a race with climbing speed.
     */
    public function withClimbSpeed(int $speed = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'climb_speed' => $speed,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Feat;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Feat>
 */
class FeatFactory extends Factory
{
    protected $model = Feat::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'prerequisites' => fake()->boolean(30) ? fake()->sentence(4) : null,
            'description' => fake()->paragraphs(3, true),
        ];
    }

    /**
     * Indicate that the feat has prerequisites.
     */
    public function withPrerequisites(?string $prerequisites = null): static
    {
        return $this->state(fn (array $attributes) => [
            'prerequisites' => $prerequisites ?? 'Strength 13 or higher',
        ]);
    }

    /**
     * Indicate that the feat has no prerequisites.
     */
    public function withoutPrerequisites(): static
    {
        return $this->state(fn (array $attributes) => [
            'prerequisites' => null,
        ]);
    }
}

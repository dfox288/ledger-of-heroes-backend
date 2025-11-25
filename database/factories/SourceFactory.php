<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Source>
 */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->words(3, true),
            'publisher' => 'Wizards of the Coast',
            'publication_year' => $this->faker->numberBetween(2014, 2024),
            'url' => $this->faker->url(),
            'author' => $this->faker->name(),
            'artist' => $this->faker->name(),
            'website' => $this->faker->url(),
            'category' => $this->faker->randomElement(['Core Rulebooks', 'Core Supplements', 'Adventure', 'Setting']),
            'description' => $this->faker->paragraphs(2, true),
        ];
    }

    /**
     * Create a PHB-like source.
     */
    public function phb(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'PHB',
            'name' => "Player's Handbook",
            'category' => 'Core Rulebooks',
            'publication_year' => 2014,
        ]);
    }

    /**
     * Create a minimal source (for testing).
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'url' => null,
            'author' => null,
            'artist' => null,
            'website' => null,
            'category' => null,
            'description' => null,
        ]);
    }
}

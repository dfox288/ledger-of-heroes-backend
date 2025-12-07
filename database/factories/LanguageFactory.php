<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name);

        return [
            'name' => ucwords($name),
            'slug' => $slug,
            'full_slug' => 'test:'.$slug,
            'script' => fake()->optional()->words(2, true),
            'typical_speakers' => fake()->optional()->words(3, true),
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Create a language with a specific name.
     */
    public function withName(string $name): static
    {
        $slug = Str::slug($name);

        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'slug' => $slug,
            'full_slug' => 'test:'.$slug,
        ]);
    }

    /**
     * Create a language with script information.
     */
    public function withScript(string $script): static
    {
        return $this->state(fn (array $attributes) => [
            'script' => $script,
        ]);
    }

    /**
     * Create a language with typical speakers.
     */
    public function withSpeakers(string $speakers): static
    {
        return $this->state(fn (array $attributes) => [
            'typical_speakers' => $speakers,
        ]);
    }
}

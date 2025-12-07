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

        $slug = Str::slug($name);

        return [
            'name' => ucwords($name),
            'slug' => $slug,
            'full_slug' => 'phb:'.$slug,
            'prerequisites_text' => fake()->boolean(30) ? fake()->sentence(4) : null,
            'description' => fake()->paragraphs(3, true),
        ];
    }

    /**
     * Indicate that the feat has prerequisites.
     */
    public function withPrerequisites(?string $prerequisites = null): static
    {
        return $this->state(fn (array $attributes) => [
            'prerequisites_text' => $prerequisites ?? 'Strength 13 or higher',
        ]);
    }

    /**
     * Indicate that the feat has no prerequisites.
     */
    public function withoutPrerequisites(): static
    {
        return $this->state(fn (array $attributes) => [
            'prerequisites_text' => null,
        ]);
    }

    /**
     * Indicate that the feat has sources.
     */
    public function withSources(): static
    {
        return $this->afterCreating(function (Feat $feat) {
            \App\Models\EntitySource::factory()
                ->forEntity(Feat::class, $feat->id)
                ->fromSource('PHB')
                ->create();
        });
    }

    /**
     * Indicate that the feat has modifiers.
     */
    public function withModifiers(): static
    {
        return $this->afterCreating(function (Feat $feat) {
            \App\Models\Modifier::factory()
                ->forEntity(Feat::class, $feat->id)
                ->create([
                    'modifier_category' => 'ability_score',
                    'value' => 1,
                ]);
        });
    }

    /**
     * Indicate that the feat has proficiencies.
     */
    public function withProficiencies(): static
    {
        return $this->afterCreating(function (Feat $feat) {
            \App\Models\Proficiency::factory()
                ->forEntity(Feat::class, $feat->id)
                ->create();
        });
    }

    /**
     * Indicate that the feat has conditions.
     */
    public function withConditions(): static
    {
        return $this->afterCreating(function (Feat $feat) {
            \App\Models\EntityCondition::factory()
                ->forEntity(Feat::class, $feat->id)
                ->create([
                    'effect_type' => 'advantage',
                    'description' => 'You have advantage on certain checks.',
                ]);
        });
    }
}

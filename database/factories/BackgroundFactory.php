<?php

namespace Database\Factories;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\EntitySource;
use App\Models\Proficiency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Background>
 */
class BackgroundFactory extends Factory
{
    protected $model = Background::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }

    /**
     * Background with description trait
     */
    public function withDescription(): static
    {
        return $this->afterCreating(function (Background $background) {
            CharacterTrait::factory()
                ->forEntity(Background::class, $background->id)
                ->create([
                    'name' => 'Description',
                    'description' => fake()->paragraphs(3, true),
                    'category' => null,
                ]);
        });
    }

    /**
     * Background with feature trait
     */
    public function withFeature(): static
    {
        return $this->afterCreating(function (Background $background) {
            CharacterTrait::factory()
                ->forEntity(Background::class, $background->id)
                ->create([
                    'name' => 'Feature: '.fake()->words(2, true),
                    'description' => fake()->paragraphs(2, true),
                    'category' => 'feature',
                ]);
        });
    }

    /**
     * Background with suggested characteristics trait
     */
    public function withCharacteristics(): static
    {
        return $this->afterCreating(function (Background $background) {
            CharacterTrait::factory()
                ->forEntity(Background::class, $background->id)
                ->create([
                    'name' => 'Suggested Characteristics',
                    'description' => fake()->paragraphs(2, true),
                    'category' => 'characteristics',
                ]);
        });
    }

    /**
     * Background with all standard traits
     */
    public function withTraits(): static
    {
        return $this->withDescription()
            ->withFeature()
            ->withCharacteristics();
    }

    /**
     * Background with skill proficiencies
     */
    public function withProficiencies(): static
    {
        return $this->afterCreating(function (Background $background) {
            // 2 skill proficiencies
            Proficiency::factory()
                ->count(2)
                ->forEntity(Background::class, $background->id)
                ->skill() // Existing factory state
                ->create();

            // Language proficiency
            Proficiency::factory()
                ->forEntity(Background::class, $background->id)
                ->create([
                    'proficiency_type' => 'language',
                    'proficiency_name' => 'Two of your choice',
                    'skill_id' => null,
                ]);
        });
    }

    /**
     * Background with source attribution
     */
    public function withSource(string $sourceCode = 'PHB', string $pages = '127'): static
    {
        return $this->afterCreating(function (Background $background) use ($sourceCode, $pages) {
            EntitySource::factory()
                ->forEntity(Background::class, $background->id)
                ->fromSource($sourceCode)
                ->create(['pages' => $pages]);
        });
    }

    /**
     * Complete background (all traits, proficiencies, source)
     */
    public function complete(): static
    {
        return $this->withTraits()
            ->withProficiencies()
            ->withSource();
    }
}

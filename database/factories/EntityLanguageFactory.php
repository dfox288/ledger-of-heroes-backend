<?php

namespace Database\Factories;

use App\Models\EntityLanguage;
use App\Models\Language;
use App\Models\Race;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EntityLanguage>
 */
class EntityLanguageFactory extends Factory
{
    protected $model = EntityLanguage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to Race as entity type
        $race = Race::factory()->create();
        $language = Language::factory()->create();

        return [
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $language->id,
            'is_choice' => false,
        ];
    }

    /**
     * Set the entity language to belong to a specific entity.
     */
    public function forEntity(string $entityType, int $entityId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $entityType,
            'reference_id' => $entityId,
        ]);
    }

    /**
     * Set the language for this entity language.
     */
    public function withLanguage(int $languageId): static
    {
        return $this->state(fn (array $attributes) => [
            'language_id' => $languageId,
        ]);
    }

    /**
     * Mark this as a choice slot (player selects language).
     */
    public function asChoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'language_id' => null,
            'is_choice' => true,
        ]);
    }

    /**
     * Mark this as a fixed language (not a choice).
     */
    public function asFixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_choice' => false,
        ]);
    }
}

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
            // Note: is_choice removed - choices now live in entity_choices table
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
     *
     * @deprecated Choices now live in entity_choices table. Use EntityChoiceFactory instead.
     */
    public function asChoice(): static
    {
        // This method is deprecated - choices should be created using EntityChoiceFactory
        return $this;
    }

    /**
     * Mark this as a fixed language (not a choice).
     *
     * @deprecated All entity_languages are now fixed. is_choice column removed.
     */
    public function asFixed(): static
    {
        // This method is a no-op - all entity_languages are now fixed by definition
        return $this;
    }
}

<?php

namespace Database\Factories;

use App\Enums\NoteCategory;
use App\Models\Character;
use App\Models\CharacterNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CharacterNote>
 */
class CharacterNoteFactory extends Factory
{
    protected $model = CharacterNote::class;

    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'category' => fake()->randomElement(NoteCategory::cases()),
            'title' => null,
            'content' => fake()->paragraph(),
            'sort_order' => 0,
        ];
    }

    /**
     * Create a personality trait note.
     */
    public function personalityTrait(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => NoteCategory::PersonalityTrait,
            'title' => null,
            'content' => fake()->sentence(),
        ]);
    }

    /**
     * Create an ideal note.
     */
    public function ideal(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => NoteCategory::Ideal,
            'title' => null,
            'content' => fake()->sentence(),
        ]);
    }

    /**
     * Create a bond note.
     */
    public function bond(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => NoteCategory::Bond,
            'title' => null,
            'content' => fake()->sentence(),
        ]);
    }

    /**
     * Create a flaw note.
     */
    public function flaw(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => NoteCategory::Flaw,
            'title' => null,
            'content' => fake()->sentence(),
        ]);
    }

    /**
     * Create a backstory note.
     */
    public function backstory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => NoteCategory::Backstory,
            'title' => fake()->words(3, true),
            'content' => fake()->paragraphs(3, true),
        ]);
    }

    /**
     * Create a custom note.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => NoteCategory::Custom,
            'title' => fake()->words(3, true),
            'content' => fake()->paragraph(),
        ]);
    }

    /**
     * Set the sort order.
     */
    public function sortOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}

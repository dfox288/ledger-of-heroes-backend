<?php

namespace Database\Factories;

use App\Models\AbilityScore;
use App\Models\CharacterClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CharacterClass>
 */
class CharacterClassFactory extends Factory
{
    protected $model = CharacterClass::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $abilities = ['Strength', 'Dexterity', 'Constitution', 'Intelligence', 'Wisdom', 'Charisma'];
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name);

        return [
            'slug' => 'test:'.$slug,
            'name' => $name,
            'parent_class_id' => null,
            'hit_die' => fake()->randomElement([6, 8, 10, 12]),
            'description' => fake()->paragraphs(2, true),
            'primary_ability' => fake()->randomElement($abilities),
            'spellcasting_ability_id' => null,
        ];
    }

    /**
     * Indicate that this is a base class (no parent).
     */
    public function baseClass(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_class_id' => null,
        ]);
    }

    /**
     * Indicate that this is a subclass with hierarchical slug.
     */
    public function subclass(?CharacterClass $parentClass = null): static
    {
        return $this->state(function (array $attributes) use ($parentClass) {
            $parent = $parentClass ?? CharacterClass::factory()->create();
            $subclassName = $attributes['name'] ?? fake()->unique()->words(2, true);
            $slug = Str::slug($parent->name.'-'.$subclassName);

            return [
                'parent_class_id' => $parent->id,
                'slug' => 'test:'.$slug,
            ];
        });
    }

    /**
     * Indicate that the class is a spellcaster.
     */
    public function spellcaster(?string $abilityCode = null): static
    {
        return $this->state(function (array $attributes) use ($abilityCode) {
            $code = $abilityCode ?? fake()->randomElement(['INT', 'WIS', 'CHA']);
            $ability = AbilityScore::where('code', $code)->first();

            return [
                'spellcasting_ability_id' => $ability->id,
            ];
        });
    }
}

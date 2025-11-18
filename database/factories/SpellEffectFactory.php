<?php

namespace Database\Factories;

use App\Models\DamageType;
use App\Models\Spell;
use App\Models\SpellEffect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SpellEffect>
 */
class SpellEffectFactory extends Factory
{
    protected $model = SpellEffect::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spell_id' => Spell::factory(),
            'effect_type' => fake()->randomElement(['damage', 'healing', 'other']),
            'description' => fake()->sentence(),
            'dice_formula' => null,
            'base_value' => null,
            'scaling_type' => null,
            'min_character_level' => null,
            'min_spell_slot' => null,
            'scaling_increment' => null,
            'damage_type_id' => null,
        ];
    }

    /**
     * Indicate this is a damage effect.
     */
    public function damage(?string $damageTypeName = null): static
    {
        return $this->state(function (array $attributes) use ($damageTypeName) {
            $typeName = $damageTypeName ?? fake()->randomElement([
                'Fire', 'Cold', 'Lightning', 'Thunder', 'Acid',
                'Poison', 'Necrotic', 'Radiant', 'Force', 'Psychic',
            ]);
            $damageType = DamageType::where('name', $typeName)->first();

            return [
                'effect_type' => 'damage',
                'dice_formula' => fake()->randomElement(['1d4', '1d6', '1d8', '2d6', '3d6', '4d6', '8d6']),
                'damage_type_id' => $damageType->id,
            ];
        });
    }

    /**
     * Indicate this effect scales with spell slot level.
     */
    public function scalingSpellSlot(int $minSlot = 1, string $increment = '1d6'): static
    {
        return $this->state(fn (array $attributes) => [
            'scaling_type' => 'spell_slot_level',
            'min_spell_slot' => $minSlot,
            'scaling_increment' => $increment,
        ]);
    }

    /**
     * Indicate this effect scales with character level (cantrip).
     */
    public function scalingCharacterLevel(int $minLevel = 5, string $increment = '1d6'): static
    {
        return $this->state(fn (array $attributes) => [
            'scaling_type' => 'character_level',
            'min_character_level' => $minLevel,
            'scaling_increment' => $increment,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\SpellSlotType;
use App\Models\Character;
use App\Models\CharacterSpellSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterSpellSlot>
 */
class CharacterSpellSlotFactory extends Factory
{
    protected $model = CharacterSpellSlot::class;

    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'spell_level' => $this->faker->numberBetween(1, 9),
            'max_slots' => $this->faker->numberBetween(1, 4),
            'used_slots' => 0,
            'slot_type' => SpellSlotType::STANDARD,
        ];
    }

    /**
     * Create a pact magic slot (Warlock).
     */
    public function pactMagic(): static
    {
        return $this->state(fn (array $attributes) => [
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);
    }

    /**
     * Set a specific spell level.
     */
    public function level(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'spell_level' => $level,
        ]);
    }

    /**
     * Set max slots.
     */
    public function maxSlots(int $slots): static
    {
        return $this->state(fn (array $attributes) => [
            'max_slots' => $slots,
        ]);
    }

    /**
     * Set used slots.
     */
    public function usedSlots(int $slots): static
    {
        return $this->state(fn (array $attributes) => [
            'used_slots' => $slots,
        ]);
    }

    /**
     * Create fully used slot.
     */
    public function fullyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_slots' => $attributes['max_slots'],
        ]);
    }
}

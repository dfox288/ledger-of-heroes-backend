<?php

namespace Database\Factories;

use App\Models\CharacterClass;
use App\Models\ClassLevelProgression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassLevelProgression>
 */
class ClassLevelProgressionFactory extends Factory
{
    protected $model = ClassLevelProgression::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => CharacterClass::factory(),
            'level' => fake()->numberBetween(1, 20),
            'cantrips_known' => fake()->optional(0.5)->numberBetween(2, 4),
            'spell_slots_1st' => fake()->optional(0.5)->numberBetween(1, 4),
            'spell_slots_2nd' => null,
            'spell_slots_3rd' => null,
            'spell_slots_4th' => null,
            'spell_slots_5th' => null,
            'spell_slots_6th' => null,
            'spell_slots_7th' => null,
            'spell_slots_8th' => null,
            'spell_slots_9th' => null,
        ];
    }

    /**
     * Set the progression to belong to a specific class.
     */
    public function forClass(CharacterClass $class): static
    {
        return $this->state(fn (array $attributes) => [
            'class_id' => $class->id,
        ]);
    }

    /**
     * Set a specific level for the progression.
     */
    public function atLevel(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }

    /**
     * Generate realistic full caster progression (like Wizard).
     */
    public function fullCaster(): static
    {
        return $this->state(function (array $attributes) {
            $level = $attributes['level'] ?? 1;

            // Cantrips progression for full casters
            $cantripsMap = [
                1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 4,
                6 => 4, 7 => 4, 8 => 4, 9 => 4, 10 => 5,
                11 => 5, 12 => 5, 13 => 5, 14 => 5, 15 => 5,
                16 => 5, 17 => 5, 18 => 5, 19 => 5, 20 => 5,
            ];

            // Spell slots for full casters
            $slotsMap = [
                1 => [2, null, null, null, null, null, null, null, null],
                2 => [3, null, null, null, null, null, null, null, null],
                3 => [4, 2, null, null, null, null, null, null, null],
                4 => [4, 3, null, null, null, null, null, null, null],
                5 => [4, 3, 2, null, null, null, null, null, null],
                6 => [4, 3, 3, null, null, null, null, null, null],
                7 => [4, 3, 3, 1, null, null, null, null, null],
                8 => [4, 3, 3, 2, null, null, null, null, null],
                9 => [4, 3, 3, 3, 1, null, null, null, null],
                10 => [4, 3, 3, 3, 2, null, null, null, null],
                11 => [4, 3, 3, 3, 2, 1, null, null, null],
                12 => [4, 3, 3, 3, 2, 1, null, null, null],
                13 => [4, 3, 3, 3, 2, 1, 1, null, null],
                14 => [4, 3, 3, 3, 2, 1, 1, null, null],
                15 => [4, 3, 3, 3, 2, 1, 1, 1, null],
                16 => [4, 3, 3, 3, 2, 1, 1, 1, null],
                17 => [4, 3, 3, 3, 2, 1, 1, 1, 1],
                18 => [4, 3, 3, 3, 3, 1, 1, 1, 1],
                19 => [4, 3, 3, 3, 3, 2, 1, 1, 1],
                20 => [4, 3, 3, 3, 3, 2, 2, 1, 1],
            ];

            $slots = $slotsMap[$level];

            return [
                'cantrips_known' => $cantripsMap[$level],
                'spell_slots_1st' => $slots[0],
                'spell_slots_2nd' => $slots[1],
                'spell_slots_3rd' => $slots[2],
                'spell_slots_4th' => $slots[3],
                'spell_slots_5th' => $slots[4],
                'spell_slots_6th' => $slots[5],
                'spell_slots_7th' => $slots[6],
                'spell_slots_8th' => $slots[7],
                'spell_slots_9th' => $slots[8],
            ];
        });
    }

    /**
     * Generate realistic half caster progression (like Paladin).
     */
    public function halfCaster(): static
    {
        return $this->state(function (array $attributes) {
            $level = $attributes['level'] ?? 1;

            // Half casters don't get cantrips
            // Half casters start at level 2

            // Spell slots for half casters
            $slotsMap = [
                1 => [null, null, null, null, null, null, null, null, null],
                2 => [2, null, null, null, null, null, null, null, null],
                3 => [3, null, null, null, null, null, null, null, null],
                4 => [3, null, null, null, null, null, null, null, null],
                5 => [4, 2, null, null, null, null, null, null, null],
                6 => [4, 2, null, null, null, null, null, null, null],
                7 => [4, 3, null, null, null, null, null, null, null],
                8 => [4, 3, null, null, null, null, null, null, null],
                9 => [4, 3, 2, null, null, null, null, null, null],
                10 => [4, 3, 2, null, null, null, null, null, null],
                11 => [4, 3, 3, null, null, null, null, null, null],
                12 => [4, 3, 3, null, null, null, null, null, null],
                13 => [4, 3, 3, 1, null, null, null, null, null],
                14 => [4, 3, 3, 1, null, null, null, null, null],
                15 => [4, 3, 3, 2, null, null, null, null, null],
                16 => [4, 3, 3, 2, null, null, null, null, null],
                17 => [4, 3, 3, 3, 1, null, null, null, null],
                18 => [4, 3, 3, 3, 1, null, null, null, null],
                19 => [4, 3, 3, 3, 2, null, null, null, null],
                20 => [4, 3, 3, 3, 2, null, null, null, null],
            ];

            $slots = $slotsMap[$level];

            return [
                'cantrips_known' => null,
                'spell_slots_1st' => $slots[0],
                'spell_slots_2nd' => $slots[1],
                'spell_slots_3rd' => $slots[2],
                'spell_slots_4th' => $slots[3],
                'spell_slots_5th' => $slots[4],
                'spell_slots_6th' => $slots[5],
                'spell_slots_7th' => $slots[6],
                'spell_slots_8th' => $slots[7],
                'spell_slots_9th' => $slots[8],
            ];
        });
    }
}

<?php

namespace Database\Factories;

use App\Models\EncounterPreset;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EncounterPreset>
 */
class EncounterPresetFactory extends Factory
{
    protected $model = EncounterPreset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Encounter',
            'party_id' => Party::factory(),
        ];
    }

    /**
     * Create a preset for a specific party.
     */
    public function forParty(Party $party): static
    {
        return $this->state(fn (array $attributes) => [
            'party_id' => $party->id,
        ]);
    }
}

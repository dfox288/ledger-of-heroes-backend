<?php

namespace Database\Factories;

use App\Models\AbilityScore;
use App\Models\Modifier;
use App\Models\Race;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Modifier>
 */
class ModifierFactory extends Factory
{
    protected $model = Modifier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to Race as reference type
        $race = Race::factory()->create();

        return [
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'other',
            'ability_score_id' => null,
            'skill_id' => null,
            'damage_type_id' => null,
            'value' => '+1',
            'condition' => null,
        ];
    }

    /**
     * Create an ability score modifier.
     */
    public function abilityScore(?string $abilityCode = null, string $value = '+2'): static
    {
        return $this->state(function (array $attributes) use ($abilityCode, $value) {
            $code = $abilityCode ?? fake()->randomElement(['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA']);
            $ability = AbilityScore::where('code', $code)->first();

            return [
                'modifier_category' => 'ability_score',
                'ability_score_id' => $ability->id,
                'value' => $value,
            ];
        });
    }

    /**
     * Set the modifier to belong to a specific entity.
     */
    public function forEntity(string $referenceType, int $referenceId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }
}

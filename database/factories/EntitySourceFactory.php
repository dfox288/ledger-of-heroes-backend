<?php

namespace Database\Factories;

use App\Models\EntitySource;
use App\Models\Source;
use App\Models\Spell;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EntitySource>
 */
class EntitySourceFactory extends Factory
{
    protected $model = EntitySource::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sourceCodes = ['PHB', 'DMG', 'MM', 'XGE', 'TCE', 'VGM'];
        $sourceCode = fake()->randomElement($sourceCodes);
        $source = Source::where('code', $sourceCode)->first()
            ?? Source::factory()->create(['code' => $sourceCode, 'name' => "Test Source {$sourceCode}"]);

        // Default to Spell as reference type
        $spell = Spell::factory()->create();

        return [
            'reference_type' => Spell::class,
            'reference_id' => $spell->id,
            'source_id' => $source->id,
            'pages' => fake()->numberBetween(1, 300),
        ];
    }

    /**
     * Set the reference to a specific entity.
     */
    public function forEntity(string $referenceType, int $referenceId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Set the source to a specific source code.
     */
    public function fromSource(string $sourceCode): static
    {
        return $this->state(function (array $attributes) use ($sourceCode) {
            $source = Source::where('code', $sourceCode)->first()
                ?? Source::factory()->create(['code' => $sourceCode, 'name' => "Test Source {$sourceCode}"]);

            return [
                'source_id' => $source->id,
            ];
        });
    }
}

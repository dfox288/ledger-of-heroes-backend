<?php

namespace Database\Factories;

use App\Enums\OptionalFeatureType;
use App\Enums\ResourceType;
use App\Models\CharacterClass;
use App\Models\EntitySource;
use App\Models\OptionalFeature;
use App\Models\SpellSchool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OptionalFeature>
 */
class OptionalFeatureFactory extends Factory
{
    protected $model = OptionalFeature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $slug = Str::slug($name);

        return [
            'name' => ucwords($name),
            'slug' => $slug,
            'full_slug' => 'test:'.$slug,
            'feature_type' => fake()->randomElement(OptionalFeatureType::cases()),
            'level_requirement' => fake()->boolean(50) ? fake()->numberBetween(1, 20) : null,
            'prerequisite_text' => fake()->boolean(30) ? fake()->sentence(4) : null,
            'description' => fake()->paragraphs(2, true),
            'casting_time' => null,
            'range' => null,
            'duration' => null,
            'spell_school_id' => null,
            'resource_type' => null,
            'resource_cost' => null,
        ];
    }

    /**
     * Configure as an Eldritch Invocation.
     */
    public function invocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_type' => OptionalFeatureType::ELDRITCH_INVOCATION,
        ]);
    }

    /**
     * Configure as an Elemental Discipline (spell-like).
     */
    public function elementalDiscipline(): static
    {
        return $this->state(function (array $attributes) {
            // Try to get a random spell school, or use null
            $spellSchool = SpellSchool::inRandomOrder()->first();

            return [
                'feature_type' => OptionalFeatureType::ELEMENTAL_DISCIPLINE,
                'casting_time' => '1 action',
                'range' => fake()->randomElement(['Self', '30 feet', '60 feet']),
                'duration' => fake()->randomElement(['Instantaneous', '1 minute', 'Concentration, up to 1 minute']),
                'spell_school_id' => $spellSchool?->id,
                'resource_type' => ResourceType::KI_POINTS,
                'resource_cost' => fake()->numberBetween(2, 6),
            ];
        });
    }

    /**
     * Configure as a Maneuver.
     */
    public function maneuver(): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_type' => OptionalFeatureType::MANEUVER,
            'resource_type' => ResourceType::SUPERIORITY_DIE,
            'resource_cost' => 1,
        ]);
    }

    /**
     * Configure as a Metamagic option.
     */
    public function metamagic(): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_type' => OptionalFeatureType::METAMAGIC,
            'resource_type' => ResourceType::SORCERY_POINTS,
            'resource_cost' => fake()->numberBetween(1, 3),
        ]);
    }

    /**
     * Configure as a Fighting Style.
     */
    public function fightingStyle(): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_type' => OptionalFeatureType::FIGHTING_STYLE,
            'level_requirement' => null,
            'prerequisite_text' => 'Fighting Style Feature',
        ]);
    }

    /**
     * Configure as an Artificer Infusion.
     */
    public function artificerInfusion(): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_type' => OptionalFeatureType::ARTIFICER_INFUSION,
        ]);
    }

    /**
     * Configure as a Rune.
     */
    public function rune(): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_type' => OptionalFeatureType::RUNE,
        ]);
    }

    /**
     * Configure as an Arcane Shot.
     */
    public function arcaneShot(): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_type' => OptionalFeatureType::ARCANE_SHOT,
        ]);
    }

    /**
     * Set a level requirement.
     */
    public function atLevel(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level_requirement' => $level,
            'prerequisite_text' => "{$level}th level",
        ]);
    }

    /**
     * Add resource cost.
     */
    public function withResourceCost(ResourceType $type, int $cost): static
    {
        return $this->state(fn (array $attributes) => [
            'resource_type' => $type,
            'resource_cost' => $cost,
        ]);
    }

    /**
     * Add spell-like mechanics.
     */
    public function withSpellMechanics(): static
    {
        return $this->state(function (array $attributes) {
            // Try to get Evocation spell school, or any spell school
            $spellSchool = SpellSchool::where('code', 'EV')->first()
                ?? SpellSchool::first();

            return [
                'casting_time' => '1 action',
                'range' => '60 feet',
                'duration' => 'Instantaneous',
                'spell_school_id' => $spellSchool?->id,
            ];
        });
    }

    /**
     * Attach to a class after creation.
     */
    public function forClass(CharacterClass|string $class, ?string $subclass = null): static
    {
        return $this->afterCreating(function (OptionalFeature $feature) use ($class, $subclass) {
            if (is_string($class)) {
                $class = CharacterClass::where('name', $class)->whereNull('parent_class_id')->first();
            }

            if ($class) {
                $feature->classes()->attach($class->id, [
                    'subclass_name' => $subclass,
                ]);
            }
        });
    }

    /**
     * Add source after creation.
     */
    public function withSources(string $sourceCode = 'PHB'): static
    {
        return $this->afterCreating(function (OptionalFeature $feature) use ($sourceCode) {
            EntitySource::factory()
                ->forEntity(OptionalFeature::class, $feature->id)
                ->fromSource($sourceCode)
                ->create();
        });
    }
}

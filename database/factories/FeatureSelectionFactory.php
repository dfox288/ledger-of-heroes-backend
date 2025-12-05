<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\FeatureSelection;
use App\Models\OptionalFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FeatureSelection>
 */
class FeatureSelectionFactory extends Factory
{
    protected $model = FeatureSelection::class;

    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'optional_feature_id' => OptionalFeature::factory(),
            'class_id' => null,
            'subclass_name' => null,
            'level_acquired' => 1,
            'uses_remaining' => null,
            'max_uses' => null,
        ];
    }

    /**
     * Associate with a specific class.
     */
    public function forClass(CharacterClass|int $class, ?string $subclassName = null): static
    {
        return $this->state(fn () => [
            'class_id' => $class instanceof CharacterClass ? $class->id : $class,
            'subclass_name' => $subclassName,
        ]);
    }

    /**
     * Set the level acquired.
     */
    public function atLevel(int $level): static
    {
        return $this->state(fn () => [
            'level_acquired' => $level,
        ]);
    }

    /**
     * Configure with limited uses.
     */
    public function withLimitedUses(int $maxUses, ?int $remaining = null): static
    {
        return $this->state(fn () => [
            'max_uses' => $maxUses,
            'uses_remaining' => $remaining ?? $maxUses,
        ]);
    }

    /**
     * Use a specific optional feature.
     */
    public function withFeature(OptionalFeature|int $feature): static
    {
        return $this->state(fn () => [
            'optional_feature_id' => $feature instanceof OptionalFeature ? $feature->id : $feature,
        ]);
    }
}

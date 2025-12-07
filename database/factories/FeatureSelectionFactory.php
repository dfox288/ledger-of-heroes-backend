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
            'optional_feature_slug' => fn () => OptionalFeature::factory()->create()->full_slug,
            'class_slug' => null,
            'subclass_name' => null,
            'level_acquired' => 1,
            'uses_remaining' => null,
            'max_uses' => null,
        ];
    }

    /**
     * Associate with a specific class.
     */
    public function forClass(CharacterClass|string $class, ?string $subclassName = null): static
    {
        return $this->state(fn () => [
            'class_slug' => $class instanceof CharacterClass ? $class->full_slug : $class,
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
    public function withFeature(OptionalFeature|string $feature): static
    {
        return $this->state(fn () => [
            'optional_feature_slug' => $feature instanceof OptionalFeature ? $feature->full_slug : $feature,
        ]);
    }
}

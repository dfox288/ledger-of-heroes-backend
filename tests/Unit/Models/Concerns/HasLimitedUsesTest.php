<?php

namespace Tests\Unit\Models\Concerns;

use App\Models\CharacterFeature;
use App\Models\FeatureSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the HasLimitedUses trait.
 *
 * Both CharacterFeature and FeatureSelection use this trait.
 * We test through FeatureSelection since it has a factory.
 */
class HasLimitedUsesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function has_limited_uses_returns_true_when_max_uses_is_set()
    {
        $feature = FeatureSelection::factory()->create([
            'max_uses' => 3,
            'uses_remaining' => 3,
        ]);

        $this->assertTrue($feature->hasLimitedUses());
    }

    #[Test]
    public function has_limited_uses_returns_false_when_max_uses_is_null()
    {
        $feature = FeatureSelection::factory()->create([
            'max_uses' => null,
            'uses_remaining' => null,
        ]);

        $this->assertFalse($feature->hasLimitedUses());
    }

    #[Test]
    public function has_uses_remaining_returns_true_when_uses_available()
    {
        $feature = FeatureSelection::factory()->create([
            'max_uses' => 3,
            'uses_remaining' => 2,
        ]);

        $this->assertTrue($feature->hasUsesRemaining());
    }

    #[Test]
    public function has_uses_remaining_returns_false_when_no_uses_left()
    {
        $feature = FeatureSelection::factory()->create([
            'max_uses' => 3,
            'uses_remaining' => 0,
        ]);

        $this->assertFalse($feature->hasUsesRemaining());
    }

    #[Test]
    public function has_uses_remaining_returns_true_for_unlimited_feature()
    {
        $feature = FeatureSelection::factory()->create([
            'max_uses' => null,
            'uses_remaining' => null,
        ]);

        $this->assertTrue($feature->hasUsesRemaining());
    }

    #[Test]
    public function use_feature_decrements_uses_remaining()
    {
        $feature = FeatureSelection::factory()->create([
            'max_uses' => 3,
            'uses_remaining' => 3,
        ]);

        $result = $feature->useFeature();

        $this->assertTrue($result);
        $this->assertEquals(2, $feature->fresh()->uses_remaining);
    }

    #[Test]
    public function use_feature_returns_false_when_no_uses_remaining()
    {
        $feature = FeatureSelection::factory()->create([
            'max_uses' => 3,
            'uses_remaining' => 0,
        ]);

        $result = $feature->useFeature();

        $this->assertFalse($result);
        $this->assertEquals(0, $feature->fresh()->uses_remaining);
    }

    #[Test]
    public function use_feature_returns_true_for_unlimited_feature()
    {
        $feature = FeatureSelection::factory()->create([
            'max_uses' => null,
            'uses_remaining' => null,
        ]);

        $result = $feature->useFeature();

        $this->assertTrue($result);
        $this->assertNull($feature->fresh()->uses_remaining);
    }

    #[Test]
    public function reset_uses_restores_to_max()
    {
        $feature = FeatureSelection::factory()->create([
            'max_uses' => 3,
            'uses_remaining' => 0,
        ]);

        $feature->resetUses();

        $this->assertEquals(3, $feature->fresh()->uses_remaining);
    }

    #[Test]
    public function reset_uses_does_nothing_for_unlimited_feature()
    {
        $feature = FeatureSelection::factory()->create([
            'max_uses' => null,
            'uses_remaining' => null,
        ]);

        $feature->resetUses();

        $this->assertNull($feature->fresh()->uses_remaining);
    }

    #[Test]
    public function character_feature_model_has_same_methods()
    {
        // CharacterFeature doesn't have a factory, so verify methods exist via reflection
        $this->assertTrue(method_exists(CharacterFeature::class, 'hasLimitedUses'));
        $this->assertTrue(method_exists(CharacterFeature::class, 'hasUsesRemaining'));
        $this->assertTrue(method_exists(CharacterFeature::class, 'useFeature'));
        $this->assertTrue(method_exists(CharacterFeature::class, 'resetUses'));
    }
}

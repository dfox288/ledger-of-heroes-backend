<?php

namespace Tests\Unit\Models;

use App\Models\Feat;
use App\Models\Modifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class FeatAccessorsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // is_half_feat accessor tests
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_true_for_feat_with_plus_one_ability_modifier(): void
    {
        $feat = Feat::factory()->create(['name' => 'Actor']);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'value' => '1',
        ]);

        $this->assertTrue($feat->is_half_feat);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_false_for_feat_with_plus_two_ability_modifier(): void
    {
        $feat = Feat::factory()->create(['name' => 'Test Feat']);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'value' => '2',
        ]);

        $this->assertFalse($feat->is_half_feat);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_false_for_feat_without_ability_modifiers(): void
    {
        $feat = Feat::factory()->create(['name' => 'Great Weapon Master']);

        // No modifiers

        $this->assertFalse($feat->is_half_feat);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_false_for_feat_with_non_ability_modifiers(): void
    {
        $feat = Feat::factory()->create(['name' => 'Test Feat']);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'skill',
            'value' => '1',
        ]);

        $this->assertFalse($feat->is_half_feat);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_true_when_any_modifier_is_plus_one_ability(): void
    {
        $feat = Feat::factory()->create(['name' => 'Multi-Modifier Feat']);

        // Has a +2 ability modifier
        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'value' => '2',
        ]);

        // Also has a +1 ability modifier (makes it a half-feat)
        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'value' => '1',
        ]);

        $this->assertTrue($feat->is_half_feat);
    }

    // =========================================================================
    // parent_feat_slug accessor tests
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_parent_slug_for_variant_feat(): void
    {
        $feat = Feat::factory()->create([
            'name' => 'Resilient (Constitution)',
            'slug' => 'resilient-constitution',
        ]);

        $this->assertEquals('resilient', $feat->parent_feat_slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_parent_slug_for_elemental_adept_variant(): void
    {
        $feat = Feat::factory()->create([
            'name' => 'Elemental Adept (Fire)',
            'slug' => 'elemental-adept-fire',
        ]);

        $this->assertEquals('elemental-adept', $feat->parent_feat_slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_parent_slug_for_magic_initiate_variant(): void
    {
        $feat = Feat::factory()->create([
            'name' => 'Magic Initiate (Wizard)',
            'slug' => 'magic-initiate-wizard',
        ]);

        $this->assertEquals('magic-initiate', $feat->parent_feat_slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_non_variant_feat(): void
    {
        $feat = Feat::factory()->create([
            'name' => 'Great Weapon Master',
            'slug' => 'great-weapon-master',
        ]);

        $this->assertNull($feat->parent_feat_slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_feat_with_parentheses_in_middle(): void
    {
        // Edge case: feat name has parentheses but not as variant notation
        $feat = Feat::factory()->create([
            'name' => 'Aberrant Dragonmark',
            'slug' => 'aberrant-dragonmark',
        ]);

        $this->assertNull($feat->parent_feat_slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_athlete_variants_correctly(): void
    {
        $feat = Feat::factory()->create([
            'name' => 'Athlete (Strength)',
            'slug' => 'athlete-strength',
        ]);

        $this->assertEquals('athlete', $feat->parent_feat_slug);
    }
}

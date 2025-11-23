<?php

namespace Tests\Feature\Models;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterClassFeatureMergingTest extends TestCase
{
    use RefreshDatabase;

    protected CharacterClass $baseClass;

    protected CharacterClass $subclass;

    protected function setUp(): void
    {
        parent::setUp();

        // Create base class with 3 features
        $this->baseClass = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
            'hit_die' => 8,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->baseClass->id,
            'level' => 1,
            'feature_name' => 'Sneak Attack',
            'sort_order' => 0,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->baseClass->id,
            'level' => 1,
            'feature_name' => 'Expertise',
            'sort_order' => 1,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->baseClass->id,
            'level' => 3,
            'feature_name' => 'Roguish Archetype',
            'sort_order' => 2,
        ]);

        // Create subclass with 2 features
        $this->subclass = CharacterClass::factory()->create([
            'name' => 'Arcane Trickster',
            'slug' => 'rogue-arcane-trickster',
            'parent_class_id' => $this->baseClass->id,
            'hit_die' => 8,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->subclass->id,
            'level' => 3,
            'feature_name' => 'Spellcasting (Arcane Trickster)',
            'sort_order' => 3,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->subclass->id,
            'level' => 9,
            'feature_name' => 'Magical Ambush (Arcane Trickster)',
            'sort_order' => 4,
        ]);
    }

    #[Test]
    public function base_class_returns_only_its_own_features()
    {
        $this->baseClass->load('features');

        $features = $this->baseClass->getAllFeatures(true);

        $this->assertCount(3, $features, 'Base class should have 3 features');
        $this->assertEquals('Sneak Attack', $features[0]->feature_name);
        $this->assertEquals('Expertise', $features[1]->feature_name);
        $this->assertEquals('Roguish Archetype', $features[2]->feature_name);
    }

    #[Test]
    public function subclass_merges_base_and_subclass_features_by_default()
    {
        $this->subclass->load(['features', 'parentClass.features']);

        $features = $this->subclass->getAllFeatures(true);

        $this->assertCount(5, $features, 'Subclass should have 5 total features (3 base + 2 subclass)');

        // Verify correct order: sorted by level, then sort_order
        $names = $features->pluck('feature_name')->toArray();
        $this->assertEquals([
            'Sneak Attack',                          // L1, sort=0 (base)
            'Expertise',                             // L1, sort=1 (base)
            'Roguish Archetype',                     // L3, sort=2 (base)
            'Spellcasting (Arcane Trickster)',       // L3, sort=3 (subclass)
            'Magical Ambush (Arcane Trickster)',     // L9, sort=4 (subclass)
        ], $names);
    }

    #[Test]
    public function subclass_can_return_only_subclass_specific_features()
    {
        $this->subclass->load('features');

        $features = $this->subclass->getAllFeatures(false); // include_inherited=false

        $this->assertCount(2, $features, 'Subclass should have only 2 subclass-specific features');
        $this->assertEquals('Spellcasting (Arcane Trickster)', $features[0]->feature_name);
        $this->assertEquals('Magical Ambush (Arcane Trickster)', $features[1]->feature_name);
    }

    #[Test]
    public function features_are_sorted_by_level_then_sort_order()
    {
        // Create more complex scenario with mixed levels
        ClassFeature::factory()->create([
            'class_id' => $this->baseClass->id,
            'level' => 5,
            'feature_name' => 'Uncanny Dodge',
            'sort_order' => 5,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->subclass->id,
            'level' => 5,
            'feature_name' => 'Extra Subclass Feature',
            'sort_order' => 6,
        ]);

        $this->subclass->load(['features', 'parentClass.features']);
        $features = $this->subclass->getAllFeatures(true);

        // Extract level+name pairs for easier verification
        $levelFeatures = $features->map(fn ($f) => "L{$f->level}: {$f->feature_name}")->toArray();

        // Verify levels are grouped together and sort_order is preserved within each level
        $this->assertEquals([
            'L1: Sneak Attack',
            'L1: Expertise',
            'L3: Roguish Archetype',
            'L3: Spellcasting (Arcane Trickster)',
            'L5: Uncanny Dodge',
            'L5: Extra Subclass Feature',
            'L9: Magical Ambush (Arcane Trickster)',
        ], $levelFeatures);
    }

    #[Test]
    public function base_class_with_include_false_behaves_same_as_include_true()
    {
        $this->baseClass->load('features');

        $featuresIncluded = $this->baseClass->getAllFeatures(true);
        $featuresExcluded = $this->baseClass->getAllFeatures(false);

        $this->assertEquals($featuresIncluded->count(), $featuresExcluded->count());
        $this->assertEquals(
            $featuresIncluded->pluck('feature_name')->toArray(),
            $featuresExcluded->pluck('feature_name')->toArray()
        );
    }

    #[Test]
    public function subclass_without_parent_loaded_returns_only_own_features_with_include_true()
    {
        // If parentClass relationship is not loaded, should gracefully fall back
        $this->subclass->load('features'); // Don't load parentClass

        $features = $this->subclass->getAllFeatures(true);

        // Should only have subclass features since parent wasn't eager-loaded
        $this->assertCount(2, $features);
    }
}

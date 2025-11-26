<?php

namespace Tests\Feature\Models;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ClassFeatureParentChildTest extends TestCase
{
    use RefreshDatabase;

    protected CharacterClass $fighter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);
    }

    #[Test]
    public function feature_can_have_parent_feature()
    {
        $parentFeature = ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
            'is_optional' => false,
        ]);

        $childFeature = ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style: Archery',
            'is_optional' => true,
            'parent_feature_id' => $parentFeature->id,
        ]);

        $this->assertEquals($parentFeature->id, $childFeature->parent_feature_id);
        $this->assertEquals($parentFeature->id, $childFeature->parentFeature->id);
    }

    #[Test]
    public function parent_feature_can_have_multiple_children()
    {
        $parentFeature = ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
            'is_optional' => false,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style: Archery',
            'is_optional' => true,
            'parent_feature_id' => $parentFeature->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style: Defense',
            'is_optional' => true,
            'parent_feature_id' => $parentFeature->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style: Dueling',
            'is_optional' => true,
            'parent_feature_id' => $parentFeature->id,
        ]);

        $parentFeature->load('childFeatures');

        $this->assertCount(3, $parentFeature->childFeatures);
        $this->assertEquals(
            ['Fighting Style: Archery', 'Fighting Style: Defense', 'Fighting Style: Dueling'],
            $parentFeature->childFeatures->pluck('feature_name')->sort()->values()->toArray()
        );
    }

    #[Test]
    public function feature_without_parent_returns_null()
    {
        $feature = ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'is_optional' => false,
        ]);

        $this->assertNull($feature->parent_feature_id);
        $this->assertNull($feature->parentFeature);
    }

    #[Test]
    public function feature_has_children_returns_correct_boolean()
    {
        $parentFeature = ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
            'is_optional' => false,
        ]);

        $standaloneFeature = ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'is_optional' => false,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style: Archery',
            'is_optional' => true,
            'parent_feature_id' => $parentFeature->id,
        ]);

        $this->assertTrue($parentFeature->hasChildren());
        $this->assertFalse($standaloneFeature->hasChildren());
    }

    #[Test]
    public function is_choice_option_returns_true_for_child_features()
    {
        $parentFeature = ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
            'is_optional' => false,
        ]);

        $childFeature = ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style: Archery',
            'is_optional' => true,
            'parent_feature_id' => $parentFeature->id,
        ]);

        $this->assertTrue($childFeature->is_choice_option);
        $this->assertFalse($parentFeature->is_choice_option);
    }

    #[Test]
    public function child_features_are_eager_loadable()
    {
        $parentFeature = ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
            'is_optional' => false,
        ]);

        ClassFeature::factory()->count(3)->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'is_optional' => true,
            'parent_feature_id' => $parentFeature->id,
        ]);

        // Load features with children eager-loaded
        $features = ClassFeature::where('class_id', $this->fighter->id)
            ->whereNull('parent_feature_id')
            ->with('childFeatures')
            ->get();

        $this->assertTrue($features->first()->relationLoaded('childFeatures'));
        $this->assertCount(3, $features->first()->childFeatures);
    }

    #[Test]
    public function scope_top_level_excludes_child_features()
    {
        $parentFeature = ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
            'is_optional' => false,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'is_optional' => false,
        ]);

        ClassFeature::factory()->count(3)->create([
            'class_id' => $this->fighter->id,
            'level' => 1,
            'is_optional' => true,
            'parent_feature_id' => $parentFeature->id,
        ]);

        // Total: 5 features (1 parent + 1 standalone + 3 children)
        $this->assertEquals(5, ClassFeature::where('class_id', $this->fighter->id)->count());

        // Top level should only return 2 (parent + standalone)
        $topLevel = ClassFeature::where('class_id', $this->fighter->id)
            ->topLevel()
            ->get();

        $this->assertCount(2, $topLevel);
        $this->assertEquals(
            ['Fighting Style', 'Second Wind'],
            $topLevel->pluck('feature_name')->sort()->values()->toArray()
        );
    }
}

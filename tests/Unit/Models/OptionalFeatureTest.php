<?php

namespace Tests\Unit\Models;

use App\Enums\OptionalFeatureType;
use App\Models\CharacterClass;
use App\Models\ClassOptionalFeature;
use App\Models\OptionalFeature;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OptionalFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_spell_school(): void
    {
        $spellSchool = SpellSchool::firstOrCreate(
            ['code' => 'EV'],
            ['name' => 'Evocation', 'description' => 'Energy manipulation spells']
        );
        $feature = OptionalFeature::factory()->create(['spell_school_id' => $spellSchool->id]);

        $this->assertInstanceOf(SpellSchool::class, $feature->spellSchool);
        $this->assertEquals($spellSchool->id, $feature->spellSchool->id);
    }

    #[Test]
    public function it_belongs_to_many_classes(): void
    {
        $feature = OptionalFeature::factory()->create();
        $class1 = CharacterClass::factory()->create();
        $class2 = CharacterClass::factory()->create();

        $feature->classes()->attach($class1->id, ['subclass_name' => 'Way of the Four Elements']);
        $feature->classes()->attach($class2->id, ['subclass_name' => 'Circle of the Moon']);

        $this->assertCount(2, $feature->classes);
        $this->assertInstanceOf(CharacterClass::class, $feature->classes->first());
    }

    #[Test]
    public function classes_relationship_includes_subclass_name_pivot(): void
    {
        $feature = OptionalFeature::factory()->create();
        $class = CharacterClass::factory()->create();

        $feature->classes()->attach($class->id, ['subclass_name' => 'Way of the Four Elements']);

        $attached = $feature->fresh()->classes->first();
        $this->assertEquals('Way of the Four Elements', $attached->pivot->subclass_name);
    }

    #[Test]
    public function it_has_many_class_pivots(): void
    {
        $feature = OptionalFeature::factory()->create();
        $class = CharacterClass::factory()->create();

        // Create pivot records directly
        for ($i = 0; $i < 3; $i++) {
            ClassOptionalFeature::create([
                'class_id' => $class->id,
                'optional_feature_id' => $feature->id,
                'subclass_name' => 'Subclass '.$i,
            ]);
        }

        $this->assertCount(3, $feature->classPivots);
        $this->assertInstanceOf(ClassOptionalFeature::class, $feature->classPivots->first());
    }

    #[Test]
    public function scope_of_type_filters_by_feature_type_enum(): void
    {
        OptionalFeature::factory()->create(['feature_type' => OptionalFeatureType::ELDRITCH_INVOCATION->value]);
        OptionalFeature::factory()->create(['feature_type' => OptionalFeatureType::FIGHTING_STYLE->value]);
        OptionalFeature::factory()->create(['feature_type' => OptionalFeatureType::ELDRITCH_INVOCATION->value]);

        $features = OptionalFeature::ofType(OptionalFeatureType::ELDRITCH_INVOCATION)->get();

        $this->assertCount(2, $features);
        $this->assertTrue($features->every(fn ($f) => $f->feature_type === OptionalFeatureType::ELDRITCH_INVOCATION));
    }

    #[Test]
    public function scope_of_type_filters_by_feature_type_string(): void
    {
        OptionalFeature::factory()->create(['feature_type' => OptionalFeatureType::FIGHTING_STYLE->value]);
        OptionalFeature::factory()->create(['feature_type' => OptionalFeatureType::METAMAGIC->value]);

        $features = OptionalFeature::ofType('fighting_style')->get();

        $this->assertCount(1, $features);
        $this->assertEquals(OptionalFeatureType::FIGHTING_STYLE, $features->first()->feature_type);
    }

    #[Test]
    public function scope_for_class_filters_by_class_id(): void
    {
        $class1 = CharacterClass::factory()->create();
        $class2 = CharacterClass::factory()->create();

        $feature1 = OptionalFeature::factory()->create();
        $feature2 = OptionalFeature::factory()->create();
        $feature3 = OptionalFeature::factory()->create();

        $feature1->classes()->attach($class1->id);
        $feature2->classes()->attach($class2->id);
        $feature3->classes()->attach($class1->id);

        $features = OptionalFeature::forClass($class1->id)->get();

        $this->assertCount(2, $features);
        $this->assertTrue($features->contains($feature1));
        $this->assertTrue($features->contains($feature3));
        $this->assertFalse($features->contains($feature2));
    }

    #[Test]
    public function scope_for_class_filters_by_class_model(): void
    {
        $class = CharacterClass::factory()->create();
        $feature = OptionalFeature::factory()->create();

        $feature->classes()->attach($class->id);

        $features = OptionalFeature::forClass($class)->get();

        $this->assertCount(1, $features);
        $this->assertEquals($feature->id, $features->first()->id);
    }

    #[Test]
    public function scope_for_subclass_filters_by_subclass_name(): void
    {
        $feature1 = OptionalFeature::factory()->create();
        $feature2 = OptionalFeature::factory()->create();
        $feature3 = OptionalFeature::factory()->create();
        $class = CharacterClass::factory()->create();

        ClassOptionalFeature::create([
            'class_id' => $class->id,
            'optional_feature_id' => $feature1->id,
            'subclass_name' => 'Way of the Four Elements',
        ]);
        ClassOptionalFeature::create([
            'class_id' => $class->id,
            'optional_feature_id' => $feature2->id,
            'subclass_name' => 'Circle of the Moon',
        ]);
        ClassOptionalFeature::create([
            'class_id' => $class->id,
            'optional_feature_id' => $feature3->id,
            'subclass_name' => 'Way of the Four Elements',
        ]);

        $features = OptionalFeature::forSubclass('Way of the Four Elements')->get();

        $this->assertCount(2, $features);
        $this->assertTrue($features->contains($feature1));
        $this->assertTrue($features->contains($feature3));
    }

    #[Test]
    public function has_spell_mechanics_returns_true_when_has_casting_time(): void
    {
        $feature = OptionalFeature::factory()->create([
            'casting_time' => '1 action',
            'range' => null,
        ]);

        $this->assertTrue($feature->has_spell_mechanics);
    }

    #[Test]
    public function has_spell_mechanics_returns_true_when_has_range(): void
    {
        $feature = OptionalFeature::factory()->create([
            'casting_time' => null,
            'range' => '30 feet',
        ]);

        $this->assertTrue($feature->has_spell_mechanics);
    }

    #[Test]
    public function has_spell_mechanics_returns_true_when_has_both(): void
    {
        $feature = OptionalFeature::factory()->create([
            'casting_time' => '1 action',
            'range' => '30 feet',
        ]);

        $this->assertTrue($feature->has_spell_mechanics);
    }

    #[Test]
    public function has_spell_mechanics_returns_false_when_has_neither(): void
    {
        $feature = OptionalFeature::factory()->create([
            'casting_time' => null,
            'range' => null,
        ]);

        $this->assertFalse($feature->has_spell_mechanics);
    }

    #[Test]
    public function feature_type_casts_to_enum(): void
    {
        $feature = OptionalFeature::factory()->create([
            'feature_type' => OptionalFeatureType::ELDRITCH_INVOCATION->value,
        ]);

        $this->assertInstanceOf(OptionalFeatureType::class, $feature->feature_type);
        $this->assertEquals(OptionalFeatureType::ELDRITCH_INVOCATION, $feature->feature_type);
    }

    #[Test]
    public function level_requirement_casts_to_integer(): void
    {
        $feature = OptionalFeature::factory()->create(['level_requirement' => '5']);

        $this->assertIsInt($feature->level_requirement);
        $this->assertEquals(5, $feature->level_requirement);
    }

    #[Test]
    public function resource_cost_casts_to_integer(): void
    {
        $feature = OptionalFeature::factory()->create(['resource_cost' => '2']);

        $this->assertIsInt($feature->resource_cost);
        $this->assertEquals(2, $feature->resource_cost);
    }
}

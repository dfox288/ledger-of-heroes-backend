<?php

namespace Tests\Feature\Models;

use App\Enums\OptionalFeatureType;
use App\Enums\ResourceType;
use App\Models\CharacterClass;
use App\Models\ClassOptionalFeature;
use App\Models\EntityDataTable;
use App\Models\EntityPrerequisite;
use App\Models\EntitySource;
use App\Models\OptionalFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class OptionalFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    // Basic Model Tests

    #[Test]
    public function it_can_create_an_optional_feature(): void
    {
        $feature = OptionalFeature::factory()->create([
            'name' => 'Test Feature',
            'feature_type' => OptionalFeatureType::ELDRITCH_INVOCATION,
        ]);

        $this->assertDatabaseHas('optional_features', [
            'name' => 'Test Feature',
            'feature_type' => 'eldritch_invocation',
        ]);

        $this->assertInstanceOf(OptionalFeature::class, $feature);
        $this->assertEquals('Test Feature', $feature->name);
    }

    #[Test]
    public function it_casts_feature_type_to_enum(): void
    {
        $feature = OptionalFeature::factory()->create([
            'feature_type' => OptionalFeatureType::MANEUVER,
        ]);

        $this->assertInstanceOf(OptionalFeatureType::class, $feature->feature_type);
        $this->assertEquals(OptionalFeatureType::MANEUVER, $feature->feature_type);
        $this->assertEquals('maneuver', $feature->feature_type->value);
    }

    #[Test]
    public function it_casts_resource_type_to_enum(): void
    {
        $feature = OptionalFeature::factory()->create([
            'resource_type' => ResourceType::SORCERY_POINTS,
            'resource_cost' => 2,
        ]);

        $this->assertInstanceOf(ResourceType::class, $feature->resource_type);
        $this->assertEquals(ResourceType::SORCERY_POINTS, $feature->resource_type);
        $this->assertEquals('sorcery_points', $feature->resource_type->value);
    }

    #[Test]
    public function it_generates_has_spell_mechanics_attribute(): void
    {
        // Feature with casting_time and range
        $featureWithMechanics = OptionalFeature::factory()->create([
            'casting_time' => '1 action',
            'range' => '60 feet',
        ]);
        $this->assertTrue($featureWithMechanics->has_spell_mechanics);

        // Feature with only casting_time
        $featureWithCastingTime = OptionalFeature::factory()->create([
            'casting_time' => '1 bonus action',
            'range' => null,
        ]);
        $this->assertTrue($featureWithCastingTime->has_spell_mechanics);

        // Feature with only range
        $featureWithRange = OptionalFeature::factory()->create([
            'casting_time' => null,
            'range' => 'Self',
        ]);
        $this->assertTrue($featureWithRange->has_spell_mechanics);

        // Feature without spell mechanics
        $featureWithoutMechanics = OptionalFeature::factory()->create([
            'casting_time' => null,
            'range' => null,
        ]);
        $this->assertFalse($featureWithoutMechanics->has_spell_mechanics);
    }

    // Relationship Tests

    #[Test]
    public function it_belongs_to_many_classes_through_pivot(): void
    {
        $feature = OptionalFeature::factory()->create();
        $class = CharacterClass::factory()->create(['name' => 'Warlock']);

        $feature->classes()->attach($class->id, ['subclass_name' => null]);

        $feature->refresh();

        $this->assertCount(1, $feature->classes);
        $this->assertInstanceOf(CharacterClass::class, $feature->classes->first());
        $this->assertEquals('Warlock', $feature->classes->first()->name);
    }

    #[Test]
    public function it_includes_subclass_name_in_pivot(): void
    {
        $feature = OptionalFeature::factory()->create();
        $class = CharacterClass::factory()->create(['name' => 'Monk']);

        $feature->classes()->attach($class->id, ['subclass_name' => 'Way of the Four Elements']);

        $feature->refresh();

        $pivotData = $feature->classes->first()->pivot;
        $this->assertEquals('Way of the Four Elements', $pivotData->subclass_name);
    }

    #[Test]
    public function it_has_many_class_pivots(): void
    {
        $feature = OptionalFeature::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);
        $paladin = CharacterClass::factory()->create(['name' => 'Paladin']);

        $feature->classes()->attach($fighter->id, ['subclass_name' => null]);
        $feature->classes()->attach($paladin->id, ['subclass_name' => null]);

        $feature->refresh();

        $this->assertCount(2, $feature->classPivots);
        $this->assertInstanceOf(ClassOptionalFeature::class, $feature->classPivots->first());
    }

    #[Test]
    public function it_morphs_many_sources(): void
    {
        $feature = OptionalFeature::factory()->create();

        EntitySource::factory()
            ->forEntity(OptionalFeature::class, $feature->id)
            ->fromSource('PHB')
            ->create(['pages' => '110']);

        $feature->refresh();

        $this->assertCount(1, $feature->sources);
        $this->assertInstanceOf(EntitySource::class, $feature->sources->first());
        $this->assertEquals('110', $feature->sources->first()->pages);
    }

    #[Test]
    public function it_morphs_many_prerequisites(): void
    {
        $feature = OptionalFeature::factory()->create();

        EntityPrerequisite::factory()
            ->forEntity(OptionalFeature::class, $feature->id)
            ->abilityScore('STR', 13)
            ->create();

        $feature->refresh();

        $this->assertCount(1, $feature->prerequisites);
        $this->assertInstanceOf(EntityPrerequisite::class, $feature->prerequisites->first());
    }

    #[Test]
    public function it_morphs_many_rolls(): void
    {
        $feature = OptionalFeature::factory()->create();

        EntityDataTable::create([
            'reference_type' => OptionalFeature::class,
            'reference_id' => $feature->id,
            'table_name' => 'Damage',
            'dice_type' => 'd8',
        ]);

        $feature->refresh();

        $this->assertCount(1, $feature->rolls);
        $this->assertInstanceOf(EntityDataTable::class, $feature->rolls->first());
        $this->assertEquals('Damage', $feature->rolls->first()->table_name);
    }

    // Scope Tests

    #[Test]
    public function it_filters_by_feature_type_using_scope(): void
    {
        OptionalFeature::factory()->invocation()->create(['name' => 'Invocation 1']);
        OptionalFeature::factory()->invocation()->create(['name' => 'Invocation 2']);
        OptionalFeature::factory()->maneuver()->create(['name' => 'Maneuver 1']);
        OptionalFeature::factory()->metamagic()->create(['name' => 'Metamagic 1']);

        // Test with enum
        $invocations = OptionalFeature::ofType(OptionalFeatureType::ELDRITCH_INVOCATION)->get();
        $this->assertCount(2, $invocations);

        // Test with string
        $maneuvers = OptionalFeature::ofType('maneuver')->get();
        $this->assertCount(1, $maneuvers);
    }

    #[Test]
    public function it_filters_by_class_using_scope(): void
    {
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock']);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);

        $invocation = OptionalFeature::factory()->create();
        $maneuver = OptionalFeature::factory()->create();

        $invocation->classes()->attach($warlock->id);
        $maneuver->classes()->attach($fighter->id);

        // Test with class instance
        $warlockFeatures = OptionalFeature::forClass($warlock)->get();
        $this->assertCount(1, $warlockFeatures);
        $this->assertEquals($invocation->id, $warlockFeatures->first()->id);

        // Test with class ID
        $fighterFeatures = OptionalFeature::forClass($fighter->id)->get();
        $this->assertCount(1, $fighterFeatures);
        $this->assertEquals($maneuver->id, $fighterFeatures->first()->id);
    }

    #[Test]
    public function it_filters_by_subclass_using_scope(): void
    {
        $monk = CharacterClass::factory()->create(['name' => 'Monk']);

        $discipline1 = OptionalFeature::factory()->create();
        $discipline2 = OptionalFeature::factory()->create();
        $otherFeature = OptionalFeature::factory()->create();

        $discipline1->classes()->attach($monk->id, ['subclass_name' => 'Way of the Four Elements']);
        $discipline2->classes()->attach($monk->id, ['subclass_name' => 'Way of the Four Elements']);
        $otherFeature->classes()->attach($monk->id, ['subclass_name' => 'Way of the Open Hand']);

        $fourElementsFeatures = OptionalFeature::forSubclass('Way of the Four Elements')->get();

        $this->assertCount(2, $fourElementsFeatures);
        $this->assertTrue($fourElementsFeatures->contains($discipline1));
        $this->assertTrue($fourElementsFeatures->contains($discipline2));
        $this->assertFalse($fourElementsFeatures->contains($otherFeature));
    }

    // Searchable Tests

    #[Test]
    public function it_has_correct_searchable_index_name(): void
    {
        $feature = OptionalFeature::factory()->create();

        $expectedIndex = config('scout.prefix').'optional_features';
        $this->assertEquals($expectedIndex, $feature->searchableAs());
    }

    #[Test]
    public function it_generates_searchable_array_with_expected_keys(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);
        $feature = OptionalFeature::factory()
            ->invocation()
            ->create([
                'name' => 'Agonizing Blast',
                'slug' => 'agonizing-blast',
                'level_requirement' => 5,
                'prerequisite_text' => '5th level, eldritch blast cantrip',
                'description' => 'Add your Charisma modifier to the damage.',
            ]);

        $feature->classes()->attach($class->id, ['subclass_name' => null]);

        EntitySource::factory()
            ->forEntity(OptionalFeature::class, $feature->id)
            ->fromSource('PHB')
            ->create();

        $feature->attachTags(['damage']);

        $searchableArray = $feature->fresh()->toSearchableArray();

        $this->assertArrayHasKey('id', $searchableArray);
        $this->assertArrayHasKey('slug', $searchableArray);
        $this->assertArrayHasKey('name', $searchableArray);
        $this->assertArrayHasKey('feature_type', $searchableArray);
        $this->assertArrayHasKey('level_requirement', $searchableArray);
        $this->assertArrayHasKey('prerequisite_text', $searchableArray);
        $this->assertArrayHasKey('description', $searchableArray);
        $this->assertArrayHasKey('resource_type', $searchableArray);
        $this->assertArrayHasKey('resource_cost', $searchableArray);
        $this->assertArrayHasKey('has_spell_mechanics', $searchableArray);
        $this->assertArrayHasKey('class_slugs', $searchableArray);
        $this->assertArrayHasKey('class_names', $searchableArray);
        $this->assertArrayHasKey('subclass_names', $searchableArray);
        $this->assertArrayHasKey('source_codes', $searchableArray);
        $this->assertArrayHasKey('tag_slugs', $searchableArray);

        $this->assertEquals('agonizing-blast', $searchableArray['slug']);
        $this->assertEquals('eldritch_invocation', $searchableArray['feature_type']);
        $this->assertEquals(5, $searchableArray['level_requirement']);
        $this->assertEquals(['warlock'], $searchableArray['class_slugs']);
        $this->assertEquals(['PHB'], $searchableArray['source_codes']);
        $this->assertEquals(['damage'], $searchableArray['tag_slugs']);
    }

    #[Test]
    public function it_includes_subclass_names_in_searchable_array(): void
    {
        $monk = CharacterClass::factory()->create(['name' => 'Monk', 'slug' => 'monk']);
        $feature = OptionalFeature::factory()->elementalDiscipline()->create();

        $feature->classes()->attach($monk->id, ['subclass_name' => 'Way of the Four Elements']);

        $searchableArray = $feature->fresh()->toSearchableArray();

        $this->assertContains('Way of the Four Elements', $searchableArray['subclass_names']);
    }

    #[Test]
    public function it_correctly_reports_has_spell_mechanics_in_searchable_array(): void
    {
        $withMechanics = OptionalFeature::factory()
            ->withSpellMechanics()
            ->create();

        $withoutMechanics = OptionalFeature::factory()->create([
            'casting_time' => null,
            'range' => null,
        ]);

        $this->assertTrue($withMechanics->toSearchableArray()['has_spell_mechanics']);
        $this->assertFalse($withoutMechanics->toSearchableArray()['has_spell_mechanics']);
    }
}

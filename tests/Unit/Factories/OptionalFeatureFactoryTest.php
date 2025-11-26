<?php

namespace Tests\Unit\Factories;

use App\Enums\OptionalFeatureType;
use App\Enums\ResourceType;
use App\Models\CharacterClass;
use App\Models\OptionalFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class OptionalFeatureFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Auto-seed for all tests

    #[Test]
    public function it_creates_optional_feature_with_default_state(): void
    {
        $feature = OptionalFeature::factory()->create();

        $this->assertInstanceOf(OptionalFeature::class, $feature);
        $this->assertNotNull($feature->id);
        $this->assertNotNull($feature->name);
        $this->assertNotNull($feature->slug);
        $this->assertNotNull($feature->feature_type);
        $this->assertNotNull($feature->description);
        $this->assertInstanceOf(OptionalFeatureType::class, $feature->feature_type);
    }

    #[Test]
    public function it_creates_invocation_type(): void
    {
        $feature = OptionalFeature::factory()->invocation()->create();

        $this->assertEquals(OptionalFeatureType::ELDRITCH_INVOCATION, $feature->feature_type);
        $this->assertEquals('eldritch_invocation', $feature->feature_type->value);
    }

    #[Test]
    public function it_creates_elemental_discipline_with_spell_mechanics(): void
    {
        $feature = OptionalFeature::factory()->elementalDiscipline()->create();

        $this->assertEquals(OptionalFeatureType::ELEMENTAL_DISCIPLINE, $feature->feature_type);
        $this->assertNotNull($feature->casting_time);
        $this->assertNotNull($feature->range);
        $this->assertNotNull($feature->duration);
        // spell_school_id may be null if no spell schools are seeded
        $this->assertEquals(ResourceType::KI_POINTS, $feature->resource_type);
        $this->assertNotNull($feature->resource_cost);
        $this->assertGreaterThanOrEqual(2, $feature->resource_cost);
        $this->assertLessThanOrEqual(6, $feature->resource_cost);
        $this->assertTrue($feature->has_spell_mechanics);
    }

    #[Test]
    public function it_creates_maneuver_with_superiority_die(): void
    {
        $feature = OptionalFeature::factory()->maneuver()->create();

        $this->assertEquals(OptionalFeatureType::MANEUVER, $feature->feature_type);
        $this->assertEquals(ResourceType::SUPERIORITY_DIE, $feature->resource_type);
        $this->assertEquals(1, $feature->resource_cost);
    }

    #[Test]
    public function it_creates_metamagic_with_sorcery_points(): void
    {
        $feature = OptionalFeature::factory()->metamagic()->create();

        $this->assertEquals(OptionalFeatureType::METAMAGIC, $feature->feature_type);
        $this->assertEquals(ResourceType::SORCERY_POINTS, $feature->resource_type);
        $this->assertNotNull($feature->resource_cost);
        $this->assertGreaterThanOrEqual(1, $feature->resource_cost);
        $this->assertLessThanOrEqual(3, $feature->resource_cost);
    }

    #[Test]
    public function it_creates_fighting_style(): void
    {
        $feature = OptionalFeature::factory()->fightingStyle()->create();

        $this->assertEquals(OptionalFeatureType::FIGHTING_STYLE, $feature->feature_type);
        $this->assertNull($feature->level_requirement);
        $this->assertEquals('Fighting Style Feature', $feature->prerequisite_text);
    }

    #[Test]
    public function it_creates_artificer_infusion(): void
    {
        $feature = OptionalFeature::factory()->artificerInfusion()->create();

        $this->assertEquals(OptionalFeatureType::ARTIFICER_INFUSION, $feature->feature_type);
    }

    #[Test]
    public function it_creates_rune(): void
    {
        $feature = OptionalFeature::factory()->rune()->create();

        $this->assertEquals(OptionalFeatureType::RUNE, $feature->feature_type);
    }

    #[Test]
    public function it_creates_arcane_shot(): void
    {
        $feature = OptionalFeature::factory()->arcaneShot()->create();

        $this->assertEquals(OptionalFeatureType::ARCANE_SHOT, $feature->feature_type);
    }

    #[Test]
    public function it_sets_level_requirement_with_at_level(): void
    {
        $feature = OptionalFeature::factory()->atLevel(5)->create();

        $this->assertEquals(5, $feature->level_requirement);
        $this->assertEquals('5th level', $feature->prerequisite_text);
    }

    #[Test]
    public function it_sets_custom_resource_cost(): void
    {
        $feature = OptionalFeature::factory()
            ->withResourceCost(ResourceType::KI_POINTS, 4)
            ->create();

        $this->assertEquals(ResourceType::KI_POINTS, $feature->resource_type);
        $this->assertEquals(4, $feature->resource_cost);
    }

    #[Test]
    public function it_adds_spell_mechanics(): void
    {
        $feature = OptionalFeature::factory()->withSpellMechanics()->create();

        $this->assertEquals('1 action', $feature->casting_time);
        $this->assertEquals('60 feet', $feature->range);
        $this->assertEquals('Instantaneous', $feature->duration);
        // spell_school_id is set to Evocation if available, or null
        // We verify the relationship works by checking if it can be loaded
        $feature->load('spellSchool');
        $this->assertTrue($feature->has_spell_mechanics);
    }

    #[Test]
    public function it_attaches_class_association(): void
    {
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock']);

        $feature = OptionalFeature::factory()
            ->invocation()
            ->forClass($warlock)
            ->create();

        $this->assertCount(1, $feature->classes);
        $this->assertEquals('Warlock', $feature->classes->first()->name);
    }

    #[Test]
    public function it_attaches_class_with_subclass_name(): void
    {
        $monk = CharacterClass::factory()->create(['name' => 'Monk']);

        $feature = OptionalFeature::factory()
            ->elementalDiscipline()
            ->forClass($monk, 'Way of the Four Elements')
            ->create();

        $this->assertCount(1, $feature->classes);
        $pivotData = $feature->classes->first()->pivot;
        $this->assertEquals('Way of the Four Elements', $pivotData->subclass_name);
    }

    #[Test]
    public function it_attaches_class_by_name(): void
    {
        CharacterClass::factory()->create(['name' => 'Fighter']);

        $feature = OptionalFeature::factory()
            ->maneuver()
            ->forClass('Fighter', 'Battle Master')
            ->create();

        $this->assertCount(1, $feature->classes);
        $this->assertEquals('Fighter', $feature->classes->first()->name);
        $this->assertEquals('Battle Master', $feature->classes->first()->pivot->subclass_name);
    }

    #[Test]
    public function it_adds_sources_after_creation(): void
    {
        $feature = OptionalFeature::factory()
            ->withSources('PHB')
            ->create();

        $this->assertCount(1, $feature->sources);
        $this->assertEquals('PHB', $feature->sources->first()->source->code);
    }

    #[Test]
    public function it_chains_multiple_factory_states(): void
    {
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock']);

        $feature = OptionalFeature::factory()
            ->invocation()
            ->atLevel(5)
            ->withSpellMechanics()
            ->forClass($warlock)
            ->withSources('PHB')
            ->create();

        $this->assertEquals(OptionalFeatureType::ELDRITCH_INVOCATION, $feature->feature_type);
        $this->assertEquals(5, $feature->level_requirement);
        $this->assertNotNull($feature->casting_time);
        $this->assertCount(1, $feature->classes);
        $this->assertCount(1, $feature->sources);
    }

    #[Test]
    public function it_generates_unique_names_and_slugs(): void
    {
        $feature1 = OptionalFeature::factory()->create();
        $feature2 = OptionalFeature::factory()->create();

        $this->assertNotEquals($feature1->name, $feature2->name);
        $this->assertNotEquals($feature1->slug, $feature2->slug);
    }
}

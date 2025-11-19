<?php

namespace Tests\Unit\Factories;

use App\Models\AbilityScore;
use App\Models\EntityPrerequisite;
use App\Models\Feat;
use App\Models\Item;
use App\Models\ProficiencyType;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityPrerequisiteFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Auto-seed for all tests

    /** @test */
    public function it_creates_prerequisite_with_default_values()
    {
        $prerequisite = EntityPrerequisite::factory()->create();

        $this->assertInstanceOf(EntityPrerequisite::class, $prerequisite);
        $this->assertEquals(1, $prerequisite->group_id);
    }

    /** @test */
    public function it_creates_ability_score_prerequisite()
    {
        $feat = Feat::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();

        $prerequisite = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->abilityScore('STR', 13)
            ->create();

        $this->assertEquals(Feat::class, $prerequisite->reference_type);
        $this->assertEquals($feat->id, $prerequisite->reference_id);
        $this->assertEquals(AbilityScore::class, $prerequisite->prerequisite_type);
        $this->assertEquals($str->id, $prerequisite->prerequisite_id);
        $this->assertEquals(13, $prerequisite->minimum_value);
    }

    /** @test */
    public function it_creates_race_prerequisite()
    {
        $feat = Feat::factory()->create();
        $race = Race::factory()->create();

        $prerequisite = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->race($race->id)
            ->create();

        $this->assertEquals(Race::class, $prerequisite->prerequisite_type);
        $this->assertEquals($race->id, $prerequisite->prerequisite_id);
    }

    /** @test */
    public function it_creates_proficiency_prerequisite()
    {
        $feat = Feat::factory()->create();
        $profType = ProficiencyType::where('name', 'Medium Armor')->first();

        $prerequisite = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->proficiency($profType->id)
            ->create();

        $this->assertEquals(ProficiencyType::class, $prerequisite->prerequisite_type);
        $this->assertEquals($profType->id, $prerequisite->prerequisite_id);
    }

    /** @test */
    public function it_creates_free_form_feature_prerequisite()
    {
        $feat = Feat::factory()->create();

        $prerequisite = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->feature('Spellcasting or Pact Magic feature')
            ->create();

        $this->assertNull($prerequisite->prerequisite_type);
        $this->assertNull($prerequisite->prerequisite_id);
        $this->assertEquals('Spellcasting or Pact Magic feature', $prerequisite->description);
    }

    /** @test */
    public function it_sets_group_id_for_logical_grouping()
    {
        $feat = Feat::factory()->create();
        $race1 = Race::factory()->create();
        $race2 = Race::factory()->create();

        $prereq1 = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->race($race1->id)
            ->inGroup(1)
            ->create();

        $prereq2 = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->race($race2->id)
            ->inGroup(1)
            ->create();

        $this->assertEquals(1, $prereq1->group_id);
        $this->assertEquals(1, $prereq2->group_id);
    }

    /** @test */
    public function it_can_be_used_for_items()
    {
        $item = Item::factory()->create();

        $prerequisite = EntityPrerequisite::factory()
            ->forEntity(Item::class, $item->id)
            ->abilityScore('STR', 15)
            ->create();

        $this->assertEquals(Item::class, $prerequisite->reference_type);
        $this->assertEquals($item->id, $prerequisite->reference_id);
    }
}

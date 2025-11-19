<?php

namespace Tests\Feature\Models;

use App\Models\AbilityScore;
use App\Models\EntityPrerequisite;
use App\Models\Feat;
use App\Models\Item;
use App\Models\ProficiencyType;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityPrerequisiteModelTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[\PHPUnit\Framework\Attributes\Test]
    public function feat_has_prerequisites_relationship()
    {
        $feat = Feat::factory()->create();

        EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->abilityScore('STR', 13)
            ->create();

        $feat->refresh();

        $this->assertCount(1, $feat->prerequisites);
        $this->assertInstanceOf(EntityPrerequisite::class, $feat->prerequisites->first());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function item_has_prerequisites_relationship()
    {
        $item = Item::factory()->create();

        EntityPrerequisite::factory()
            ->forEntity(Item::class, $item->id)
            ->abilityScore('STR', 15)
            ->create();

        $this->assertCount(1, $item->prerequisites);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prerequisite_belongs_to_reference_entity()
    {
        $feat = Feat::factory()->create();

        $prerequisite = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->abilityScore('DEX', 13)
            ->create();

        $this->assertInstanceOf(Feat::class, $prerequisite->reference);
        $this->assertEquals($feat->id, $prerequisite->reference->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prerequisite_belongs_to_ability_score()
    {
        $feat = Feat::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();

        $prerequisite = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->abilityScore('STR', 13)
            ->create();

        $this->assertInstanceOf(AbilityScore::class, $prerequisite->prerequisite);
        $this->assertEquals($str->id, $prerequisite->prerequisite->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prerequisite_belongs_to_race()
    {
        $feat = Feat::factory()->create();
        $race = Race::factory()->create(['name' => 'Elf']);

        $prerequisite = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->race($race->id)
            ->create();

        $this->assertInstanceOf(Race::class, $prerequisite->prerequisite);
        $this->assertEquals('Elf', $prerequisite->prerequisite->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prerequisite_belongs_to_proficiency_type()
    {
        $feat = Feat::factory()->create();
        $profType = ProficiencyType::where('name', 'Medium Armor')->first();

        $prerequisite = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->proficiency($profType->id)
            ->create();

        $this->assertInstanceOf(ProficiencyType::class, $prerequisite->prerequisite);
        $this->assertEquals('Medium Armor', $prerequisite->prerequisite->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prerequisite_supports_free_form_description()
    {
        $feat = Feat::factory()->create();

        $prerequisite = EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->feature('The ability to cast at least one spell')
            ->create();

        $this->assertNull($prerequisite->prerequisite);
        $this->assertEquals('The ability to cast at least one spell', $prerequisite->description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function feat_can_have_multiple_prerequisites_with_groups()
    {
        $feat = Feat::factory()->create();
        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
        $gnome = Race::factory()->create(['name' => 'Gnome']);
        $mediumArmor = ProficiencyType::where('name', 'Medium Armor')->first();

        // Group 1: Dwarf OR Gnome
        EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->race($dwarf->id)
            ->inGroup(1)
            ->create();

        EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->race($gnome->id)
            ->inGroup(1)
            ->create();

        // Group 2: Medium Armor proficiency
        EntityPrerequisite::factory()
            ->forEntity(Feat::class, $feat->id)
            ->proficiency($mediumArmor->id)
            ->inGroup(2)
            ->create();

        $feat->refresh();

        $prerequisites = $feat->prerequisites;
        $this->assertCount(3, $prerequisites);

        $group1 = $prerequisites->where('group_id', 1);
        $group2 = $prerequisites->where('group_id', 2);

        $this->assertCount(2, $group1); // 2 races in OR group
        $this->assertCount(1, $group2); // 1 proficiency in AND group
    }
}

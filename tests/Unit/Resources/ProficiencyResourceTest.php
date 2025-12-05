<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ProficiencyResource;
use App\Models\AbilityScore;
use App\Models\Item;
use App\Models\Proficiency;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ProficiencyResourceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_transforms_basic_proficiency_fields(): void
    {
        $race = Race::factory()->create();

        $proficiency = Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Longsword',
            'grants' => true,
            'is_choice' => false,
            'quantity' => 1,
            'level' => null,
        ]);

        $resource = new ProficiencyResource($proficiency);
        $array = $resource->toArray(request());

        $this->assertEquals($proficiency->id, $array['id']);
        $this->assertEquals('weapon', $array['proficiency_type']);
        $this->assertEquals('Longsword', $array['proficiency_name']);
        $this->assertTrue($array['grants']);
        $this->assertFalse($array['is_choice']);
        $this->assertEquals(1, $array['quantity']);
        $this->assertNull($array['level']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_proficiency_type_detail_when_loaded(): void
    {
        $race = Race::factory()->create();
        $proficiencyType = ProficiencyType::where('name', 'Light Armor')->first();

        $proficiency = Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'armor',
            'proficiency_type_id' => $proficiencyType->id,
            'proficiency_name' => null,
            'grants' => true,
            'is_choice' => false,
        ]);

        $proficiency->load('proficiencyType');

        $resource = new ProficiencyResource($proficiency);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('proficiency_type_detail', $array);
        $this->assertNotNull($array['proficiency_type_detail']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_skill_when_loaded(): void
    {
        $race = Race::factory()->create();
        $skill = Skill::where('name', 'Perception')->first();

        $proficiency = Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'skill',
            'skill_id' => $skill->id,
            'proficiency_name' => null,
            'grants' => true,
            'is_choice' => false,
        ]);

        $proficiency->load('skill');

        $resource = new ProficiencyResource($proficiency);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('skill', $array);
        $this->assertNotNull($array['skill']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_item_when_present(): void
    {
        $race = Race::factory()->create();
        $item = Item::factory()->create(['name' => 'Longsword']);

        $proficiency = Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'weapon',
            'item_id' => $item->id,
            'proficiency_name' => null,
            'grants' => true,
            'is_choice' => false,
        ]);

        $proficiency->load('item');

        $resource = new ProficiencyResource($proficiency);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('item', $array);
        $this->assertNotNull($array['item']);
        $this->assertEquals($item->id, $array['item']['id']);
        $this->assertEquals('Longsword', $array['item']['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_ability_score_when_loaded(): void
    {
        $race = Race::factory()->create();
        $abilityScore = AbilityScore::where('code', 'STR')->first();

        $proficiency = Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'saving_throw',
            'ability_score_id' => $abilityScore->id,
            'proficiency_name' => null,
            'grants' => true,
            'is_choice' => false,
        ]);

        $proficiency->load('abilityScore');

        $resource = new ProficiencyResource($proficiency);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('ability_score', $array);
        $this->assertNotNull($array['ability_score']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_choice_fields(): void
    {
        $race = Race::factory()->create();

        $proficiency = Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'skill',
            'proficiency_name' => 'Any skill',
            'grants' => true,
            'is_choice' => true,
            'choice_group' => 'skills',
            'choice_option' => 2,
            'quantity' => 2,
        ]);

        $resource = new ProficiencyResource($proficiency);
        $array = $resource->toArray(request());

        $this->assertTrue($array['is_choice']);
        $this->assertEquals('skills', $array['choice_group']);
        $this->assertEquals(2, $array['choice_option']);
        $this->assertEquals(2, $array['quantity']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_proficiency_subcategory_when_present(): void
    {
        $race = Race::factory()->create();

        $proficiency = Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'tool',
            'proficiency_subcategory' => 'artisan',
            'proficiency_name' => 'Smith\'s Tools',
            'grants' => true,
            'is_choice' => false,
        ]);

        $resource = new ProficiencyResource($proficiency);
        $array = $resource->toArray(request());

        $this->assertEquals('artisan', $array['proficiency_subcategory']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_level_when_granted_at_specific_level(): void
    {
        $race = Race::factory()->create();

        $proficiency = Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Longbow',
            'grants' => true,
            'is_choice' => false,
            'level' => 3,
        ]);

        $resource = new ProficiencyResource($proficiency);
        $array = $resource->toArray(request());

        $this->assertEquals(3, $array['level']);
    }
}

<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\RaceResource;
use App\Models\CharacterTrait;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class RaceResourceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_transforms_basic_race_fields(): void
    {
        $race = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'speed' => 30,
            'fly_speed' => null,
            'swim_speed' => null,
            'climb_speed' => null,
            'subrace_required' => false,
        ]);

        $race->load('size');

        $resource = new RaceResource($race);
        $array = $resource->toArray(request());

        $this->assertEquals($race->id, $array['id']);
        $this->assertEquals('high-elf', $array['slug']);
        $this->assertEquals('High Elf', $array['name']);
        $this->assertEquals(30, $array['speed']);
        $this->assertNull($array['fly_speed']);
        $this->assertNull($array['swim_speed']);
        $this->assertNull($array['climb_speed']);
        $this->assertFalse($array['is_subrace']);
        $this->assertFalse($array['subrace_required']);
        $this->assertNotNull($array['size']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_parent_race_when_is_subrace(): void
    {
        $parentRace = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
        ]);

        $subrace = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'parent_race_id' => $parentRace->id,
        ]);

        $subrace->load('parent');

        $resource = new RaceResource($subrace);
        $array = $resource->toArray(request());

        $this->assertTrue($array['is_subrace']);
        $this->assertArrayHasKey('parent_race', $array);
        $this->assertNotNull($array['parent_race']);
        $this->assertEquals('Elf', $array['parent_race']['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_traits_when_loaded(): void
    {
        $race = Race::factory()->create();

        CharacterTrait::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'name' => 'Darkvision',
            'description' => 'You can see in dim light within 60 feet.',
        ]);

        $race->load('traits');

        $resource = new RaceResource($race);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('traits', $array);
        $this->assertCount(1, $array['traits']);
        $this->assertEquals('Darkvision', $array['traits'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_modifiers_when_loaded(): void
    {
        $race = Race::factory()->create();

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'modifier_subcategory' => 'STR',
            'value' => '2',
        ]);

        $race->load('modifiers');

        $resource = new RaceResource($race);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('modifiers', $array);
        $this->assertCount(1, $array['modifiers']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_proficiencies_when_loaded(): void
    {
        $race = Race::factory()->create();

        Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Longsword',
            'grants' => true,
            'is_choice' => false,
        ]);

        $race->load('proficiencies');

        $resource = new RaceResource($race);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('proficiencies', $array);
        $this->assertCount(1, $array['proficiencies']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_subraces_when_loaded(): void
    {
        $parentRace = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
        ]);

        $subrace1 = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'parent_race_id' => $parentRace->id,
        ]);

        $subrace2 = Race::factory()->create([
            'name' => 'Wood Elf',
            'slug' => 'wood-elf',
            'parent_race_id' => $parentRace->id,
        ]);

        $parentRace->load('subraces');

        $resource = new RaceResource($parentRace);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('subraces', $array);
        $this->assertCount(2, $array['subraces']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_inherited_data_for_subraces_with_loaded_parent(): void
    {
        $parentRace = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
        ]);

        // Add traits to parent
        CharacterTrait::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'name' => 'Darkvision',
            'description' => 'You can see in dim light within 60 feet.',
        ]);

        // Add modifier to parent
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'modifier_category' => 'ability_score',
            'modifier_subcategory' => 'DEX',
            'value' => '2',
        ]);

        $subrace = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'parent_race_id' => $parentRace->id,
        ]);

        $subrace->load('parent.traits', 'parent.modifiers');

        $resource = new RaceResource($subrace);
        $array = $resource->toArray(request());

        $this->assertTrue($array['is_subrace']);
        $this->assertArrayHasKey('inherited_data', $array);
        $this->assertNotNull($array['inherited_data']);
        $this->assertArrayHasKey('traits', $array['inherited_data']);
        $this->assertArrayHasKey('modifiers', $array['inherited_data']);
        $this->assertCount(1, $array['inherited_data']['traits']);
        $this->assertCount(1, $array['inherited_data']['modifiers']);
        $this->assertEquals('Darkvision', $array['inherited_data']['traits'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_null_values_for_unloaded_parent_relationships_in_inherited_data(): void
    {
        $parentRace = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
        ]);

        $subrace = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'parent_race_id' => $parentRace->id,
        ]);

        // Load parent but NOT its relationships
        $subrace->load('parent');

        $resource = new RaceResource($subrace);
        $array = $resource->toArray(request());

        $this->assertTrue($array['is_subrace']);
        $this->assertArrayHasKey('inherited_data', $array);
        $this->assertNull($array['inherited_data']['traits']);
        $this->assertNull($array['inherited_data']['modifiers']);
        $this->assertNull($array['inherited_data']['proficiencies']);
        $this->assertNull($array['inherited_data']['languages']);
        $this->assertNull($array['inherited_data']['conditions']);
        $this->assertNull($array['inherited_data']['senses']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_all_inherited_relationship_types(): void
    {
        $parentRace = Race::factory()->create([
            'name' => 'Dwarf',
            'slug' => 'dwarf',
        ]);

        // Add various relationships to parent
        CharacterTrait::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'name' => 'Stonecunning',
            'description' => 'Advantage on History checks related to stonework.',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'modifier_category' => 'ability_score',
            'modifier_subcategory' => 'CON',
            'value' => '2',
        ]);

        Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Battleaxe',
            'grants' => true,
            'is_choice' => false,
        ]);

        $subrace = Race::factory()->create([
            'name' => 'Mountain Dwarf',
            'slug' => 'mountain-dwarf',
            'parent_race_id' => $parentRace->id,
        ]);

        $subrace->load('parent.traits', 'parent.modifiers', 'parent.proficiencies', 'parent.languages', 'parent.conditions', 'parent.senses');

        $resource = new RaceResource($subrace);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('inherited_data', $array);
        $this->assertNotNull($array['inherited_data']['traits']);
        $this->assertNotNull($array['inherited_data']['modifiers']);
        $this->assertNotNull($array['inherited_data']['proficiencies']);
        $this->assertNotNull($array['inherited_data']['languages']);
        $this->assertNotNull($array['inherited_data']['conditions']);
        $this->assertNotNull($array['inherited_data']['senses']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_tags_when_loaded(): void
    {
        $race = Race::factory()->create();
        $race->attachTag('playable');

        $race->load('tags');

        $resource = new RaceResource($race);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('tags', $array);
        $this->assertCount(1, $array['tags']);
    }
}

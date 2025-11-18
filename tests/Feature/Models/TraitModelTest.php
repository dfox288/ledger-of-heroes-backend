<?php

namespace Tests\Feature\Models;

use App\Models\CharacterTrait;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraitModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_trait_belongs_to_race_via_polymorphic(): void
    {
        $race = Race::factory()->create();

        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'name' => 'Darkvision',
            'category' => 'species',
            'description' => 'You can see in dim light within 60 feet...',
            'sort_order' => 1,
        ]);

        $this->assertEquals($race->id, $trait->reference->id);
        $this->assertInstanceOf(Race::class, $trait->reference);
    }

    public function test_race_has_many_traits(): void
    {
        $race = Race::factory()->create();

        CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'name' => 'Darkvision',
            'category' => 'species',
            'description' => 'You can see in dim light...',
            'sort_order' => 1,
        ]);

        CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'name' => 'Keen Senses',
            'category' => 'species',
            'description' => 'You have proficiency in Perception...',
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $race->traits);
    }
}

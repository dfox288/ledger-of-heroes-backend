<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\Race;
use App\Models\Size;
use App\Models\Skill;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;


class RaceApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_get_all_races()
    {
        // Create test races
        $source = $this->getSource('PHB');

        $race1 = Race::factory()->create([
            'name' => 'Dragonborn',
        ]);

        $race1->sources()->create([
            'source_id' => $source->id,
            'pages' => '32',
        ]);

        $race2 = Race::factory()->create([
            'name' => 'Dwarf, Hill',
            'speed' => 25,
        ]);

        $race2->sources()->create([
            'source_id' => $source->id,
            'pages' => '19',
        ]);

        $response = $this->getJson('/api/v1/races');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'size' => ['id', 'code', 'name'],
                        'speed',
                        'sources' => [
                            '*' => ['code', 'name', 'pages'],
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function can_search_races()
    {
        $source = $this->getSource('PHB');

        $race1 = Race::factory()->create([
            'name' => 'Dragonborn',
        ]);

        $race1->sources()->create([
            'source_id' => $source->id,
            'pages' => '32',
        ]);

        $race2 = Race::factory()->create([
            'name' => 'Dwarf, Hill',
            'speed' => 25,
        ]);

        $race2->sources()->create([
            'source_id' => $source->id,
            'pages' => '19',
        ]);

        $response = $this->getJson('/api/v1/races?search=Dragon');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Dragonborn');
    }

    #[Test]
    public function can_get_single_race()
    {
        $source = $this->getSource('PHB');

        $race = Race::factory()->create([
            'name' => 'Dragonborn',
        ]);

        $race->sources()->create([
            'source_id' => $source->id,
            'pages' => '32',
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $race->id,
                    'name' => 'Dragonborn',
                    'speed' => 30,
                ],
            ]);
    }

    #[Test]
    public function it_includes_parent_race_in_response()
    {
        // Create base race and subrace
        $baseRace = Race::factory()->create([
            'name' => 'Dwarf',
            'parent_race_id' => null,
        ]);

        $subrace = Race::factory()->create([
            'name' => 'Hill',
            'parent_race_id' => $baseRace->id,
        ]);

        $response = $this->getJson("/api/v1/races/{$subrace->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'parent_race',
                'subraces',
            ]
        ]);

        $this->assertEquals('Dwarf', $response->json('data.parent_race.name'));
    }

    #[Test]
    public function it_includes_subraces_in_response()
    {
        $baseRace = Race::factory()->create([
            'name' => 'Elf',
            'parent_race_id' => null,
        ]);

        Race::factory()->create([
            'name' => 'High',
            'parent_race_id' => $baseRace->id,
        ]);

        Race::factory()->create([
            'name' => 'Wood',
            'parent_race_id' => $baseRace->id,
        ]);

        $response = $this->getJson("/api/v1/races/{$baseRace->id}");

        $response->assertStatus(200);
        $subraces = $response->json('data.subraces');
        $this->assertCount(2, $subraces);
    }

    #[Test]
    public function it_includes_proficiencies_in_response()
    {
        $race = Race::factory()->create(['name' => 'High Elf']);
        $skill = $this->getSkill('Perception');

        Proficiency::factory()->forEntity(Race::class, $race->id)->create([
            'proficiency_type' => 'skill',
            'skill_id' => $skill->id,
        ]);

        Proficiency::factory()->forEntity(Race::class, $race->id)->create([
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Longsword',
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'proficiencies' => [
                    '*' => ['id', 'proficiency_type']
                ]
            ]
        ]);

        $proficiencies = $response->json('data.proficiencies');
        $this->assertCount(2, $proficiencies);
    }

    #[Test]
    public function it_includes_traits_in_response()
    {
        $race = Race::factory()->create(['name' => 'Elf']);

        \App\Models\CharacterTrait::factory()->forEntity(Race::class, $race->id)->create([
            'name' => 'Darkvision',
            'category' => 'species',
            'description' => 'You can see in dim light...',
            'sort_order' => 1,
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'traits' => [
                    '*' => ['id', 'name', 'category', 'description', 'sort_order']
                ]
            ]
        ]);

        $traits = $response->json('data.traits');
        $this->assertCount(1, $traits);
        $this->assertEquals('Darkvision', $traits[0]['name']);
    }

    #[Test]
    public function it_includes_modifiers_in_response()
    {
        $race = Race::factory()->create(['name' => 'Dragonborn']);
        $str = $this->getAbilityScore('STR');

        Modifier::factory()->forEntity(Race::class, $race->id)->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '+2',
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);
        $modifiers = $response->json('data.modifiers');
        $this->assertCount(1, $modifiers);
        $this->assertEquals('ability_score', $modifiers[0]['modifier_category']);
        $this->assertEquals('+2', $modifiers[0]['value']);
    }

    #[Test]
    public function test_race_modifiers_include_ability_score_resource()
    {
        $strAbility = $this->getAbilityScore('STR');

        $race = Race::factory()->create([
            'name' => 'Test Strong Race',
        ]);

        $race->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => 2,
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'modifiers' => [
                        '*' => [
                            'modifier_category',
                            'value',
                            'ability_score' => [
                                'id',
                                'name',
                                'code',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'code' => 'STR',
                'name' => 'Strength',
            ])
            ->assertJsonPath('data.modifiers.0.value', '2');
    }

    #[Test]
    public function test_race_proficiencies_include_skill_resource()
    {
        $skill = $this->getSkill('Perception');

        $race = Race::factory()->create([
            'name' => 'Test Perceptive Race',
        ]);

        $race->proficiencies()->create([
            'proficiency_type' => 'skill',
            'skill_id' => $skill->id,
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'proficiencies' => [
                        '*' => [
                            'proficiency_type',
                            'skill' => [
                                'id',
                                'name',
                                'ability_score' => [
                                    'id',
                                    'code',
                                    'name',
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'proficiency_type' => 'skill',
                'name' => 'Perception',
            ]);
    }

    #[Test]
    public function test_race_traits_with_random_tables_include_entry_resource()
    {
        $race = Race::factory()->create([
            'name' => 'Test Random Race',
        ]);

        $trait = $race->traits()->create([
            'name' => 'Random Feature',
            'category' => 'feature',
            'description' => 'Roll for your feature',
            'sort_order' => 0,
        ]);

        $randomTable = \App\Models\RandomTable::factory()->forEntity(\App\Models\CharacterTrait::class, $trait->id)->create([
            'table_name' => 'Feature Table',
            'dice_type' => '1d6',
            'description' => 'Test table',
        ]);

        $trait->update(['random_table_id' => $randomTable->id]);

        $randomTable->entries()->create([
            'roll_min' => 1,
            'roll_max' => 1,
            'result_text' => 'Feature A',
            'sort_order' => 1,
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'traits' => [
                        '*' => [
                            'random_tables' => [
                                '*' => [
                                    'entries' => [
                                        '*' => [
                                            'id',
                                            'roll_min',
                                            'roll_max',
                                            'result_text',
                                            'sort_order',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_modifier_includes_skill_when_present()
    {
        $skill = $this->getSkill('Stealth');

        $race = Race::factory()->create([
            'name' => 'Stealthy Race',
        ]);

        $race->modifiers()->create([
            'modifier_category' => 'skill',
            'skill_id' => $skill->id,
            'value' => 2,
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'modifiers' => [
                        '*' => [
                            'skill' => [
                                'id',
                                'name',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_proficiency_includes_ability_score_when_present()
    {
        $strAbility = $this->getAbilityScore('STR');

        $race = Race::factory()->create([
            'name' => 'Strong Race',
        ]);

        $race->proficiencies()->create([
            'proficiency_type' => 'saving_throw',
            'ability_score_id' => $strAbility->id,
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'proficiencies' => [
                        '*' => [
                            'ability_score' => [
                                'id',
                                'code',
                                'name',
                            ],
                        ],
                    ],
                ],
            ]);
    }
}

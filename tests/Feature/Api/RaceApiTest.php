<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\CharacterTrait;
use App\Models\EntityCondition;
use App\Models\EntityDataTable;
use App\Models\EntityDataTableEntry;
use App\Models\EntitySpell;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\Race;
use App\Models\Skill;
use App\Models\Spell;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Race API endpoints.
 *
 * These tests use factory-based data and are self-contained.
 */
#[Group('feature-search')]
#[Group('search-isolated')]
class RaceApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = TestDatabaseSeeder::class;

    #[Test]
    public function can_get_all_races()
    {
        // Verify database has races from import
        $this->assertGreaterThan(0, Race::count(), 'Database must be seeded with races');

        $response = $this->getJson('/api/v1/races');

        $response->assertStatus(200)
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

        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should return imported races');
    }

    #[Test]
    public function can_search_races()
    {
        $response = $this->getJson('/api/v1/races?search=Dragon');

        $response->assertStatus(200);

        // Dragonborn should be in results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Dragonborn', $names, 'Expected to find Dragonborn in search results');
    }

    #[Test]
    public function can_get_single_race()
    {
        // Use imported Dragonborn race
        $race = Race::where('name', 'Dragonborn')->first();
        $this->assertNotNull($race, 'Dragonborn should exist in imported data');

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
        // Use imported Hill Dwarf subrace
        $subrace = Race::where('name', 'Hill')->whereNotNull('parent_race_id')->first();
        $this->assertNotNull($subrace, 'Hill Dwarf subrace should exist in imported data');

        $response = $this->getJson("/api/v1/races/{$subrace->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'parent_race',
                'subraces',
            ],
        ]);

        $this->assertEquals('Dwarf', $response->json('data.parent_race.name'));
    }

    #[Test]
    public function it_includes_subraces_in_response()
    {
        // Use imported Elf base race
        $baseRace = Race::where('name', 'Elf')->whereNull('parent_race_id')->first();
        $this->assertNotNull($baseRace, 'Elf base race should exist in imported data');

        $response = $this->getJson("/api/v1/races/{$baseRace->id}");

        $response->assertStatus(200);
        $subraces = $response->json('data.subraces');
        $this->assertGreaterThan(0, count($subraces), 'Elf should have subraces');

        // Verify structure
        $subraceNames = collect($subraces)->pluck('name')->toArray();
        $this->assertContains('High', $subraceNames, 'High Elf subrace should be included');
    }

    #[Test]
    public function it_includes_proficiencies_in_response()
    {
        $race = Race::factory()->create(['slug' => 'test:race-proficiencies-'.uniqid()]);
        Proficiency::factory()->forEntity(Race::class, $race->id)->create();

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'proficiencies' => [
                    '*' => ['id', 'proficiency_type'],
                ],
            ],
        ]);

        $proficiencies = $response->json('data.proficiencies');
        $this->assertGreaterThan(0, count($proficiencies), 'Race should have proficiencies');
    }

    #[Test]
    public function it_includes_traits_in_response()
    {
        // Use imported Dragonborn which has traits
        $race = Race::where('name', 'Dragonborn')->first();
        $this->assertNotNull($race, 'Dragonborn should exist in imported data');

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'traits' => [
                    '*' => ['id', 'name', 'category', 'description', 'sort_order'],
                ],
            ],
        ]);

        $traits = $response->json('data.traits');
        $this->assertGreaterThan(0, count($traits), 'Dragonborn should have traits');
    }

    #[Test]
    public function it_includes_modifiers_in_response()
    {
        // Use imported Dragonborn which has ability score modifiers
        $race = Race::where('name', 'Dragonborn')->first();
        $this->assertNotNull($race, 'Dragonborn should exist in imported data');

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);
        $modifiers = $response->json('data.modifiers');
        $this->assertGreaterThan(0, count($modifiers), 'Dragonborn should have modifiers');

        // Verify structure
        $this->assertArrayHasKey('modifier_category', $modifiers[0]);
        $this->assertArrayHasKey('value', $modifiers[0]);
    }

    #[Test]
    public function race_modifiers_include_ability_score_resource()
    {
        // Use imported Dragonborn which has STR modifier
        $race = Race::where('name', 'Dragonborn')->first();
        $this->assertNotNull($race, 'Dragonborn should exist in imported data');

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
            ]);

        // Verify at least one modifier has ability score
        $modifiers = $response->json('data.modifiers');
        $abilityScoreModifiers = collect($modifiers)->filter(function ($mod) {
            return $mod['modifier_category'] === 'ability_score' && isset($mod['ability_score']);
        });
        $this->assertGreaterThan(0, $abilityScoreModifiers->count(), 'Should have ability score modifiers');
    }

    #[Test]
    public function race_proficiencies_include_skill_resource()
    {
        $race = Race::factory()->create(['slug' => 'test:race-skill-prof-'.uniqid()]);
        Proficiency::factory()->forEntity(Race::class, $race->id)->skill('Stealth')->create();

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);

        $proficiencies = $response->json('data.proficiencies');
        $skillProf = collect($proficiencies)->first(fn ($p) => $p['proficiency_type'] === 'skill');

        $this->assertNotNull($skillProf, 'Expected a skill-type proficiency in the response');
        $this->assertArrayHasKey('skill', $skillProf);
        $this->assertArrayHasKey('id', $skillProf['skill']);
        $this->assertArrayHasKey('name', $skillProf['skill']);
    }

    #[Test]
    public function race_traits_with_data_tables_include_entry_resource()
    {
        $race = Race::factory()->create(['slug' => 'test:race-data-table-'.uniqid()]);
        $trait = CharacterTrait::factory()->forEntity(Race::class, $race->id)->create();
        $table = EntityDataTable::factory()->forEntity(CharacterTrait::class, $trait->id)->create();
        EntityDataTableEntry::factory()->forTable($table)->create();

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);

        $traits = $response->json('data.traits');
        $traitWithTable = collect($traits)->first(fn ($t) => ! empty($t['data_tables']));
        $this->assertNotNull($traitWithTable, 'Expected a trait with at least one data_table');
        $this->assertArrayHasKey('entries', $traitWithTable['data_tables'][0]);
        $this->assertGreaterThan(0, count($traitWithTable['data_tables'][0]['entries']));
    }

    #[Test]
    public function modifier_includes_skill_when_present()
    {
        $race = Race::factory()->create(['slug' => 'test:race-skill-mod-'.uniqid()]);
        $skill = Skill::firstOrFail();
        Modifier::factory()->forEntity(Race::class, $race->id)->create([
            'modifier_category' => 'skill',
            'skill_id' => $skill->id,
            'value' => '+1',
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);

        $modifiers = $response->json('data.modifiers');
        $skillMod = collect($modifiers)->first(fn ($m) => $m['modifier_category'] === 'skill');
        $this->assertNotNull($skillMod, 'Expected a skill-category modifier in the response');
        $this->assertArrayHasKey('skill', $skillMod);
        $this->assertArrayHasKey('id', $skillMod['skill']);
        $this->assertArrayHasKey('name', $skillMod['skill']);
    }

    #[Test]
    public function proficiency_includes_ability_score_when_present()
    {
        $race = Race::factory()->create(['slug' => 'test:race-save-prof-'.uniqid()]);
        $ability = AbilityScore::firstOrFail();
        Proficiency::factory()->forEntity(Race::class, $race->id)->create([
            'proficiency_type' => 'saving_throw',
            'ability_score_id' => $ability->id,
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);

        $proficiencies = $response->json('data.proficiencies');
        $savingThrowProf = collect($proficiencies)->first(fn ($p) => $p['proficiency_type'] === 'saving_throw');

        $this->assertNotNull($savingThrowProf, 'Expected a saving_throw-type proficiency in the response');
        $this->assertArrayHasKey('ability_score', $savingThrowProf);
        $this->assertArrayHasKey('id', $savingThrowProf['ability_score']);
        $this->assertArrayHasKey('code', $savingThrowProf['ability_score']);
        $this->assertArrayHasKey('name', $savingThrowProf['ability_score']);
    }

    #[Test]
    public function race_response_includes_conditions()
    {
        $race = Race::factory()->create(['slug' => 'test:race-conditions-'.uniqid()]);
        EntityCondition::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'condition_id' => null,
            'effect_type' => 'advantage',
            'description' => 'Advantage on saving throws vs being charmed.',
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'conditions' => [
                        '*' => [
                            'id',
                            'condition_id',
                            'condition',
                            'effect_type',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function race_response_includes_spells()
    {
        $race = Race::factory()->create(['slug' => 'test:race-spells-'.uniqid()]);
        $spell = Spell::factory()->create();
        $ability = AbilityScore::firstOrFail();
        EntitySpell::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'spell_id' => $spell->id,
            'ability_score_id' => $ability->id,
            'is_cantrip' => false,
            'level_requirement' => 1,
            'usage_limit' => 1,
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'spells' => [
                        '*' => [
                            'id',
                            'spell_id',
                            'spell',
                            'ability_score_id',
                            'ability_score',
                            'is_cantrip',
                            'level_requirement',
                            'usage_limit',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_includes_sources_in_subraces_response()
    {
        // Use imported Human base race which has subraces (Variant, Mark of Finding, etc.)
        $baseRace = Race::where('name', 'Human')->whereNull('parent_race_id')->first();
        $this->assertNotNull($baseRace, 'Human base race should exist in imported data');

        $response = $this->getJson("/api/v1/races/{$baseRace->id}");

        $response->assertStatus(200);
        $subraces = $response->json('data.subraces');
        $this->assertGreaterThan(0, count($subraces), 'Human should have subraces');

        // Find the Variant Human subrace
        $variantHuman = collect($subraces)->first(fn ($s) => $s['name'] === 'Variant');
        $this->assertNotNull($variantHuman, 'Variant Human subrace should be included');

        // Regression test for GitHub issue #399: subraces should have sources populated
        $this->assertNotNull($variantHuman['sources'], 'Subrace sources should not be null');
        $this->assertIsArray($variantHuman['sources'], 'Subrace sources should be an array');
        $this->assertGreaterThan(0, count($variantHuman['sources']), 'Variant Human should have at least one source');

        // Verify source structure
        $response->assertJsonStructure([
            'data' => [
                'subraces' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'sources' => [
                            '*' => ['code', 'name', 'pages'],
                        ],
                    ],
                ],
            ],
        ]);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Race API endpoints.
 *
 * These tests use factory-based data and are self-contained.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class RaceApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

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
        // Find any race with proficiencies
        $race = Race::has('proficiencies')->first();

        if (! $race) {
            $this->markTestSkipped('No races with proficiencies in fixtures');
        }

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
    public function test_race_modifiers_include_ability_score_resource()
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
    public function test_race_proficiencies_include_skill_resource()
    {
        // Find a race with skill proficiencies
        $race = Race::whereHas('proficiencies', function ($q) {
            $q->where('proficiency_type', 'skill');
        })->first();

        if (! $race) {
            $this->markTestSkipped('No races with skill proficiencies in imported data');
        }

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'proficiencies' => [
                        '*' => [
                            'proficiency_type',
                        ],
                    ],
                ],
            ]);

        // Find skill proficiency and verify it has skill resource
        $proficiencies = $response->json('data.proficiencies');
        $skillProf = collect($proficiencies)->first(fn ($p) => $p['proficiency_type'] === 'skill');

        if ($skillProf) {
            $this->assertArrayHasKey('skill', $skillProf);
            $this->assertArrayHasKey('id', $skillProf['skill']);
            $this->assertArrayHasKey('name', $skillProf['skill']);
        }
    }

    #[Test]
    public function test_race_traits_with_data_tables_include_entry_resource()
    {
        // Find a race with traits that have data tables
        $race = Race::whereHas('traits.dataTables')->first();

        if (! $race) {
            $this->markTestSkipped('No races with data table traits in imported data');
        }

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);

        $traits = $response->json('data.traits');
        $traitsWithTables = collect($traits)->filter(fn ($t) => ! empty($t['data_tables']));

        if ($traitsWithTables->isNotEmpty()) {
            $traitWithTable = $traitsWithTables->first();
            $this->assertArrayHasKey('data_tables', $traitWithTable);
            $this->assertArrayHasKey('entries', $traitWithTable['data_tables'][0]);
        }
    }

    #[Test]
    public function test_modifier_includes_skill_when_present()
    {
        // Find a race with skill modifiers
        $race = Race::whereHas('modifiers', function ($q) {
            $q->where('modifier_category', 'skill');
        })->first();

        if (! $race) {
            $this->markTestSkipped('No races with skill modifiers in imported data');
        }

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);

        $modifiers = $response->json('data.modifiers');
        $skillMod = collect($modifiers)->first(fn ($m) => $m['modifier_category'] === 'skill');

        if ($skillMod) {
            $this->assertArrayHasKey('skill', $skillMod);
            $this->assertArrayHasKey('id', $skillMod['skill']);
            $this->assertArrayHasKey('name', $skillMod['skill']);
        }
    }

    #[Test]
    public function test_proficiency_includes_ability_score_when_present()
    {
        // Find a race with saving throw proficiencies
        $race = Race::whereHas('proficiencies', function ($q) {
            $q->where('proficiency_type', 'saving_throw');
        })->first();

        if (! $race) {
            $this->markTestSkipped('No races with saving throw proficiencies in imported data');
        }

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200);

        $proficiencies = $response->json('data.proficiencies');
        $savingThrowProf = collect($proficiencies)->first(fn ($p) => $p['proficiency_type'] === 'saving_throw');

        if ($savingThrowProf) {
            $this->assertArrayHasKey('ability_score', $savingThrowProf);
            $this->assertArrayHasKey('id', $savingThrowProf['ability_score']);
            $this->assertArrayHasKey('code', $savingThrowProf['ability_score']);
            $this->assertArrayHasKey('name', $savingThrowProf['ability_score']);
        }
    }

    #[Test]
    public function race_response_includes_conditions()
    {
        // Find a race with conditions
        $race = Race::whereHas('conditions')->first();

        if (! $race) {
            $this->markTestSkipped('No races with conditions in imported data');
        }

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
        // Find a race with spells
        $race = Race::whereHas('spells')->first();

        if (! $race) {
            $this->markTestSkipped('No races with spells in imported data');
        }

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
    public function modifier_includes_choice_fields()
    {
        // Find a race with choice modifiers
        $race = Race::whereHas('modifiers', function ($q) {
            $q->where('is_choice', true);
        })->first();

        if (! $race) {
            $this->markTestSkipped('No races with choice modifiers in imported data');
        }

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'modifiers' => [
                        '*' => [
                            'is_choice',
                            'choice_count',
                            'choice_constraint',
                        ],
                    ],
                ],
            ]);
    }
}

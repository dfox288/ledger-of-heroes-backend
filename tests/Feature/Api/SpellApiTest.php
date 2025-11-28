<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * These tests use fixture-based test data from TestDatabaseSeeder.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class SpellApiTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    #[Test]
    public function can_get_all_spells()
    {
        $response = $this->getJson('/api/v1/spells?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'level',
                        'school' => ['id', 'name', 'code'],
                        'casting_time',
                        'range',
                        'components',
                        'duration',
                        'needs_concentration',
                        'is_ritual',
                        'description',
                        'sources' => [
                            '*' => ['code', 'name', 'pages'],
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);

        // Verify we have at least some spells from imported data
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    #[Test]
    public function can_search_spells()
    {
        // Use a spell that exists in fixtures
        $spell = Spell::first();
        $this->assertNotNull($spell, 'Should have spells in database');

        $response = $this->getJson('/api/v1/spells?q='.urlencode($spell->name));

        $response->assertStatus(200);

        // Verify that the spell is in the results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains($spell->name, $names, "Expected to find {$spell->name} in search results");
    }

    #[Test]
    public function spell_includes_effects_in_response()
    {
        // Use any spell from fixtures
        $spell = Spell::first();
        $this->assertNotNull($spell, 'Should have spells in database');

        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'effects',
                ],
            ]);
    }

    #[Test]
    public function spell_includes_classes_in_response()
    {
        // Find a spell that has classes
        $spell = Spell::has('classes')->first();

        if (! $spell) {
            $this->markTestSkipped('No spells with classes in fixtures');
        }

        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'classes' => [
                        '*' => [
                            'id',
                            'name',
                            'hit_die',
                            'description',
                        ],
                    ],
                ],
            ]);

        // Verify spell has at least one class
        $this->assertGreaterThan(0, count($response->json('data.classes')));
    }

    #[Test]
    public function spell_includes_spell_school_resource()
    {
        // Use any spell from fixtures
        $spell = Spell::first();
        $this->assertNotNull($spell, 'Should have spells in database');

        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'school' => [
                        'id',
                        'code',
                        'name',
                    ],
                ],
            ]);

        // Verify school is present
        $this->assertNotNull($response->json('data.school.code'));
    }

    #[Test]
    public function spell_includes_sources_as_resource()
    {
        // Find a spell with sources
        $spell = Spell::has('sources')->first();

        if (! $spell) {
            $this->markTestSkipped('No spells with sources in fixtures');
        }

        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'sources' => [
                        '*' => [
                            'code',
                            'name',
                            'pages',
                        ],
                    ],
                ],
            ]);

        // Verify spell has at least one source
        $this->assertGreaterThan(0, count($response->json('data.sources')));
    }

    #[Test]
    public function spell_exposes_component_breakdown_fields()
    {
        // Use any spell from fixtures
        $spell = Spell::first();
        $this->assertNotNull($spell, 'Should have spells in database');

        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'requires_verbal',
                    'requires_somatic',
                    'requires_material',
                ],
            ]);

        // Verify boolean fields are present (actual values depend on spell)
        $this->assertIsBool($response->json('data.requires_verbal'));
        $this->assertIsBool($response->json('data.requires_somatic'));
        $this->assertIsBool($response->json('data.requires_material'));
    }

    #[Test]
    public function can_filter_spells_by_level()
    {
        $response = $this->getJson('/api/v1/spells?filter=level = 3');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected some level 3 spells');

        // Verify all results are level 3
        foreach ($response->json('data') as $spell) {
            $this->assertEquals(3, $spell['level']);
        }
    }

    #[Test]
    public function can_filter_spells_by_school()
    {
        $response = $this->getJson('/api/v1/spells?filter=school_code = EV');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected some Evocation spells');

        // Verify all results are Evocation
        foreach ($response->json('data') as $spell) {
            $this->assertEquals('EV', $spell['school']['code']);
        }
    }

    #[Test]
    public function can_filter_spells_by_class()
    {
        $response = $this->getJson('/api/v1/spells?filter=class_slugs IN [wizard]');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected some Wizard spells');
    }

    #[Test]
    public function returns_404_for_nonexistent_spell()
    {
        $response = $this->getJson('/api/v1/spells/999999');

        $response->assertNotFound();
    }

    #[Test]
    public function returns_404_for_nonexistent_spell_slug()
    {
        $response = $this->getJson('/api/v1/spells/nonexistent-spell-xyz-123');

        $response->assertNotFound();
    }
}

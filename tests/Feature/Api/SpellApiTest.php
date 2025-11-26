<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * These tests use pre-imported data from SearchTestExtension.
 * No RefreshDatabase needed - all tests are read-only against shared data.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-imported')]
class SpellApiTest extends TestCase
{
    protected $seed = false;

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
        $response = $this->getJson('/api/v1/spells?q=fireball');

        $response->assertStatus(200);

        // Verify that Fireball is in the results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Fireball', $names, 'Expected to find Fireball in search results');
    }

    #[Test]
    public function spell_includes_effects_in_response()
    {
        // Magic Missile should have effects
        $spell = Spell::where('slug', 'magic-missile')->firstOrFail();

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
        // Fireball should have classes (Wizard, Sorcerer)
        $spell = Spell::where('slug', 'fireball')->firstOrFail();

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

        // Verify Fireball has at least one class
        $this->assertGreaterThan(0, count($response->json('data.classes')));
    }

    #[Test]
    public function spell_includes_spell_school_resource()
    {
        $spell = Spell::where('slug', 'fireball')->firstOrFail();

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
            ])
            ->assertJsonPath('data.school.code', 'EV');
    }

    #[Test]
    public function spell_includes_sources_as_resource()
    {
        $spell = Spell::where('slug', 'fireball')->firstOrFail();

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

        // Verify Fireball has at least one source
        $this->assertGreaterThan(0, count($response->json('data.sources')));
    }

    #[Test]
    public function spell_exposes_component_breakdown_fields()
    {
        // Test Verbal/Somatic/Material breakdown
        $spell = Spell::where('slug', 'fireball')->firstOrFail();

        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'requires_verbal',
                    'requires_somatic',
                    'requires_material',
                ],
            ]);

        // Fireball requires V, S, M
        $response->assertJsonPath('data.requires_verbal', true)
            ->assertJsonPath('data.requires_somatic', true)
            ->assertJsonPath('data.requires_material', true);
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

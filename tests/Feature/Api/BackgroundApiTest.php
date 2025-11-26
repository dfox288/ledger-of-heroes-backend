<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Background API endpoints.
 *
 * These tests use pre-imported data from SearchTestExtension.
 * No RefreshDatabase needed - all tests are read-only against shared data.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class BackgroundApiTest extends TestCase
{
    protected $seed = false;

    #[Test]
    public function can_get_all_backgrounds()
    {
        // Verify database has backgrounds from import
        $this->assertGreaterThan(0, Background::count(), 'Database must be seeded with backgrounds');

        $response = $this->getJson('/api/v1/backgrounds');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sources' => [
                            '*' => ['code', 'name', 'pages'],
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should return imported backgrounds');
    }

    #[Test]
    public function can_search_backgrounds()
    {
        $response = $this->getJson('/api/v1/backgrounds?search=Acolyte');

        $response->assertStatus(200);

        // Verify Acolyte is in results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Acolyte', $names, 'Expected to find Acolyte in search results');
    }

    #[Test]
    public function can_get_single_background()
    {
        // Use imported Acolyte background
        $bg = Background::where('name', 'Acolyte')->first();
        $this->assertNotNull($bg, 'Acolyte background should exist in imported data');

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'sources',
                ],
            ])
            ->assertJsonPath('data.name', 'Acolyte');
    }

    #[Test]
    public function it_includes_traits_in_response()
    {
        // Find a background with traits
        $bg = Background::whereHas('traits')->first();
        $this->assertNotNull($bg, 'At least one background should have traits');

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200);
        $traits = $response->json('data.traits');
        $this->assertGreaterThan(0, count($traits), 'Background should have traits');

        // Verify structure
        $this->assertArrayHasKey('name', $traits[0]);
        $this->assertArrayHasKey('description', $traits[0]);
    }

    #[Test]
    public function it_includes_proficiencies_in_response()
    {
        // Find a background with proficiencies
        $bg = Background::whereHas('proficiencies')->first();
        $this->assertNotNull($bg, 'At least one background should have proficiencies');

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200);
        $proficiencies = $response->json('data.proficiencies');
        $this->assertGreaterThan(0, count($proficiencies), 'Background should have proficiencies');

        // Verify structure
        $this->assertArrayHasKey('proficiency_name', $proficiencies[0]);
        $this->assertArrayHasKey('proficiency_type', $proficiencies[0]);
    }

    #[Test]
    public function it_includes_sources_as_resource()
    {
        // Use imported background with sources
        $bg = Background::whereHas('sources')->first();
        $this->assertNotNull($bg, 'At least one background should have sources');

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'sources' => [
                        '*' => ['code', 'name', 'pages'],
                    ],
                ],
            ]);

        $sources = $response->json('data.sources');
        $this->assertGreaterThan(0, count($sources), 'Background should have sources');
    }

    #[Test]
    public function background_traits_include_random_tables()
    {
        // Find a background with traits that have random tables
        $bg = Background::whereHas('traits.randomTables')->first();

        if (! $bg) {
            $this->markTestSkipped('No backgrounds with random table traits in imported data');
        }

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200);

        $traits = $response->json('data.traits');
        $traitsWithTables = collect($traits)->filter(fn ($t) => ! empty($t['random_tables']));

        if ($traitsWithTables->isNotEmpty()) {
            $traitWithTable = $traitsWithTables->first();
            $this->assertArrayHasKey('random_tables', $traitWithTable);
            $this->assertArrayHasKey('table_name', $traitWithTable['random_tables'][0]);
            $this->assertArrayHasKey('entries', $traitWithTable['random_tables'][0]);
        }
    }

    #[Test]
    public function background_proficiencies_include_skill_resource()
    {
        // Find a background with skill proficiencies
        $bg = Background::whereHas('proficiencies', function ($q) {
            $q->where('proficiency_type', 'skill')->whereNotNull('skill_id');
        })->first();

        if (! $bg) {
            $this->markTestSkipped('No backgrounds with skill proficiencies in imported data');
        }

        $response = $this->getJson("/api/v1/backgrounds/{$bg->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'proficiencies' => [
                        '*' => [
                            'proficiency_name',
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
    public function can_paginate_backgrounds()
    {
        // Verify we have enough backgrounds for pagination
        $totalBackgrounds = Background::count();
        $this->assertGreaterThan(5, $totalBackgrounds, 'Should have more than 5 backgrounds for pagination test');

        $response = $this->getJson('/api/v1/backgrounds?per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 5);

        $this->assertLessThanOrEqual(5, count($response->json('data')), 'Should return at most 5 backgrounds per page');
    }

    #[Test]
    public function can_sort_backgrounds()
    {
        $response = $this->getJson('/api/v1/backgrounds?sort_by=name&sort_direction=asc&per_page=5');

        $response->assertStatus(200);

        // Verify results are sorted alphabetically
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sortedNames = $names;
        sort($sortedNames);

        $this->assertEquals($sortedNames, $names, 'Backgrounds should be sorted alphabetically by name');

        // Verify Acolyte is first (alphabetically)
        if (count($names) > 0) {
            $this->assertEquals('Acolyte', $names[0], 'Acolyte should be first when sorted by name ascending');
        }
    }
}

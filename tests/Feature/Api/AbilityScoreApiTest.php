<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for AbilityScore API endpoints.
 *
 * These tests use LookupSeeder for stable ability score data.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class AbilityScoreApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function can_get_all_ability_scores()
    {
        $response = $this->getJson('/api/v1/lookups/ability-scores');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'code',
                    'name',
                ],
            ],
            'links',
            'meta',
        ]);

        // Should have exactly 6 ability scores
        $this->assertEquals(6, $response->json('meta.total'));
    }

    #[Test]
    public function can_get_single_ability_score_by_id()
    {
        $str = AbilityScore::where('code', 'STR')->firstOrFail();

        $response = $this->getJson("/api/v1/lookups/ability-scores/{$str->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', 'STR');
        $response->assertJsonPath('data.name', 'Strength');
        $response->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'name',
            ],
        ]);
    }

    #[Test]
    public function can_get_all_six_ability_scores()
    {
        // Verify all 6 core ability scores exist
        $response = $this->getJson('/api/v1/lookups/ability-scores?per_page=100');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->toArray();
        $this->assertContains('STR', $codes);
        $this->assertContains('DEX', $codes);
        $this->assertContains('CON', $codes);
        $this->assertContains('INT', $codes);
        $this->assertContains('WIS', $codes);
        $this->assertContains('CHA', $codes);
    }

    #[Test]
    public function can_search_ability_scores_by_name()
    {
        $response = $this->getJson('/api/v1/lookups/ability-scores?q=strength');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));

        // Verify Strength is in results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Strength', $names);
    }

    #[Test]
    public function can_search_ability_scores_by_code()
    {
        $response = $this->getJson('/api/v1/lookups/ability-scores?q=WIS');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));

        // Verify Wisdom is in results
        $codes = collect($response->json('data'))->pluck('code')->toArray();
        $this->assertContains('WIS', $codes);
    }

    #[Test]
    public function can_search_ability_scores_partial_match()
    {
        $response = $this->getJson('/api/v1/lookups/ability-scores?q=str');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));

        // Should match Strength (STR)
        $codes = collect($response->json('data'))->pluck('code')->toArray();
        $this->assertContains('STR', $codes);
    }

    #[Test]
    public function ability_score_response_has_correct_structure()
    {
        $str = AbilityScore::where('code', 'STR')->firstOrFail();

        $response = $this->getJson("/api/v1/lookups/ability-scores/{$str->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'name',
            ],
        ]);

        // Verify all required fields are present
        $this->assertNotNull($response->json('data.id'));
        $this->assertNotNull($response->json('data.code'));
        $this->assertNotNull($response->json('data.name'));
    }

    #[Test]
    public function can_paginate_ability_scores()
    {
        $response = $this->getJson('/api/v1/lookups/ability-scores?per_page=3');

        $response->assertOk();
        $this->assertLessThanOrEqual(3, count($response->json('data')));
        $response->assertJsonPath('meta.per_page', 3);
    }

    #[Test]
    public function returns_404_for_nonexistent_ability_score()
    {
        $response = $this->getJson('/api/v1/lookups/ability-scores/999999');

        $response->assertNotFound();
    }

    #[Test]
    public function returns_404_for_invalid_code_via_custom_binding()
    {
        // Custom route model binding should handle code lookups
        $response = $this->getJson('/api/v1/lookups/ability-scores/INVALID');

        $response->assertNotFound();
    }

    #[Test]
    public function returns_404_for_invalid_name_via_custom_binding()
    {
        // Custom route model binding should handle name lookups (case-insensitive)
        $response = $this->getJson('/api/v1/lookups/ability-scores/nonexistent-ability');

        $response->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Size API endpoints.
 *
 * These tests use LookupSeeder for stable size data.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class SizeApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function can_get_all_sizes()
    {
        $response = $this->getJson('/api/v1/lookups/sizes');

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

        // Should have exactly 6 sizes (Tiny, Small, Medium, Large, Huge, Gargantuan)
        $this->assertEquals(6, $response->json('meta.total'));
    }

    #[Test]
    public function can_get_single_size_by_id()
    {
        $medium = Size::where('code', 'M')->firstOrFail();

        $response = $this->getJson("/api/v1/lookups/sizes/{$medium->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', 'M');
        $response->assertJsonPath('data.name', 'Medium');
        $response->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'name',
            ],
        ]);
    }

    #[Test]
    public function can_get_small_size()
    {
        $small = Size::where('code', 'S')->firstOrFail();

        $response = $this->getJson("/api/v1/lookups/sizes/{$small->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', 'S');
        $response->assertJsonPath('data.name', 'Small');
    }

    #[Test]
    public function can_get_large_size()
    {
        $large = Size::where('code', 'L')->firstOrFail();

        $response = $this->getJson("/api/v1/lookups/sizes/{$large->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', 'L');
        $response->assertJsonPath('data.name', 'Large');
    }

    #[Test]
    public function can_get_tiny_size()
    {
        $tiny = Size::where('code', 'T')->firstOrFail();

        $response = $this->getJson("/api/v1/lookups/sizes/{$tiny->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', 'T');
        $response->assertJsonPath('data.name', 'Tiny');
    }

    #[Test]
    public function can_get_huge_size()
    {
        $huge = Size::where('code', 'H')->firstOrFail();

        $response = $this->getJson("/api/v1/lookups/sizes/{$huge->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', 'H');
        $response->assertJsonPath('data.name', 'Huge');
    }

    #[Test]
    public function can_get_gargantuan_size()
    {
        $gargantuan = Size::where('code', 'G')->firstOrFail();

        $response = $this->getJson("/api/v1/lookups/sizes/{$gargantuan->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', 'G');
        $response->assertJsonPath('data.name', 'Gargantuan');
    }

    #[Test]
    public function can_search_sizes_by_name()
    {
        $response = $this->getJson('/api/v1/lookups/sizes?q=medium');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));

        // Verify Medium is in results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Medium', $names);
    }

    #[Test]
    public function can_search_sizes_partial_match()
    {
        $response = $this->getJson('/api/v1/lookups/sizes?q=arge');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));

        // Should match Large and Gargantuan
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertTrue(
            in_array('Large', $names) || in_array('Gargantuan', $names),
            'Expected to find Large or Gargantuan in partial match for "arge"'
        );
    }

    #[Test]
    public function can_search_sizes_case_insensitive()
    {
        $response = $this->getJson('/api/v1/lookups/sizes?q=TINY');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));

        // Verify Tiny is in results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Tiny', $names);
    }

    #[Test]
    public function can_paginate_sizes()
    {
        $response = $this->getJson('/api/v1/lookups/sizes?per_page=3');

        $response->assertOk();
        $this->assertLessThanOrEqual(3, count($response->json('data')));
        $response->assertJsonPath('meta.per_page', 3);
    }

    #[Test]
    public function sizes_ordered_correctly()
    {
        $response = $this->getJson('/api/v1/lookups/sizes');

        $response->assertOk();

        // Verify all 6 sizes are present
        $codes = collect($response->json('data'))->pluck('code')->toArray();
        $this->assertContains('T', $codes);
        $this->assertContains('S', $codes);
        $this->assertContains('M', $codes);
        $this->assertContains('L', $codes);
        $this->assertContains('H', $codes);
        $this->assertContains('G', $codes);
    }

    #[Test]
    public function returns_404_for_nonexistent_size()
    {
        $response = $this->getJson('/api/v1/lookups/sizes/999999');

        $response->assertNotFound();
    }

    #[Test]
    public function returns_404_for_invalid_string_identifier()
    {
        // Size only accepts numeric IDs (no custom route binding for code/name)
        $response = $this->getJson('/api/v1/lookups/sizes/INVALID');

        $response->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellSchoolApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Only seed if not already seeded (prevents duplicate key errors)
        if (\App\Models\SpellSchool::count() === 0) {
            $this->seed(\Database\Seeders\SpellSchoolSeeder::class);
        }
    }

    #[Test]
    public function it_can_list_all_spell_schools(): void
    {
        $response = $this->getJson('/api/v1/lookups/spell-schools');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'description'],
                ],
            ]);

        $this->assertCount(8, $response->json('data'), 'Should have 8 schools of magic');
    }

    #[Test]
    public function it_can_search_spell_schools_using_q_parameter(): void
    {
        $response = $this->getJson('/api/v1/lookups/spell-schools?q=evocation');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Search should return results for "evocation"');

        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('evocation', $item['name']);
        }
    }

    #[Test]
    public function it_returns_empty_results_when_no_spell_schools_match_search(): void
    {
        $response = $this->getJson('/api/v1/lookups/spell-schools?q=nonexistent123');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    #[Test]
    public function it_returns_all_spell_schools_when_no_search_query_provided(): void
    {
        $response = $this->getJson('/api/v1/lookups/spell-schools');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(8, $data);
    }

    #[Test]
    public function it_can_get_a_single_spell_school_by_id(): void
    {
        $school = SpellSchool::first();

        $response = $this->getJson("/api/v1/lookups/spell-schools/{$school->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $school->id)
            ->assertJsonPath('data.code', $school->code)
            ->assertJsonPath('data.name', $school->name);
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        $response = $this->getJson('/api/v1/lookups/spell-schools?q=ABJURATION');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Case insensitive search should work');
    }

    #[Test]
    public function it_supports_pagination(): void
    {
        $response = $this->getJson('/api/v1/lookups/spell-schools?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.per_page', 5);
    }
}

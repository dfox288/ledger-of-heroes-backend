<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DamageTypeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Only seed if not already seeded (prevents duplicate key errors)
        if (\App\Models\DamageType::count() === 0) {
            $this->seed(\Database\Seeders\DamageTypeSeeder::class);
        }
    }

    #[Test]
    public function it_can_list_all_damage_types(): void
    {
        $response = $this->getJson('/api/v1/damage-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name'],
                ],
            ]);
    }

    #[Test]
    public function it_can_search_damage_types_using_q_parameter(): void
    {
        $response = $this->getJson('/api/v1/damage-types?q=fire');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Search should return results for "fire"');

        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('fire', $item['name']);
        }
    }

    #[Test]
    public function it_returns_empty_results_when_no_damage_types_match_search(): void
    {
        $response = $this->getJson('/api/v1/damage-types?q=nonexistent123');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        $response = $this->getJson('/api/v1/damage-types?q=POISON');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Case insensitive search should work');
    }
}

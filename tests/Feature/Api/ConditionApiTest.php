<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConditionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ConditionSeeder::class);
    }

    #[Test]
    public function it_can_list_all_conditions(): void
    {
        $response = $this->getJson('/api/v1/lookups/conditions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ]);
    }

    #[Test]
    public function it_can_search_conditions_using_q_parameter(): void
    {
        $response = $this->getJson('/api/v1/lookups/conditions?q=charmed');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Search should return results for "charmed"');

        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('charmed', $item['name']);
        }
    }

    #[Test]
    public function it_returns_empty_results_when_no_conditions_match_search(): void
    {
        $response = $this->getJson('/api/v1/lookups/conditions?q=nonexistent123');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        $response = $this->getJson('/api/v1/lookups/conditions?q=BLINDED');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Case insensitive search should work');
    }
}

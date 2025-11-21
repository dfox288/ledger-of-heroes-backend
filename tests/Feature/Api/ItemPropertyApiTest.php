<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemPropertyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ItemPropertySeeder::class);
    }

    #[Test]
    public function it_can_list_all_item_properties(): void
    {
        $response = $this->getJson('/api/v1/item-properties');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'description'],
                ],
            ]);
    }

    #[Test]
    public function it_can_search_item_properties_using_q_parameter(): void
    {
        $response = $this->getJson('/api/v1/item-properties?q=finesse');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Search should return results for "finesse"');

        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('finesse', $item['name']);
        }
    }

    #[Test]
    public function it_returns_empty_results_when_no_item_properties_match_search(): void
    {
        $response = $this->getJson('/api/v1/item-properties?q=nonexistent123');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        $response = $this->getJson('/api/v1/item-properties?q=HEAVY');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Case insensitive search should work');
    }
}

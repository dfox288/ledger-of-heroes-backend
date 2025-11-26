<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ItemTypeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ItemTypeSeeder::class);
    }

    #[Test]
    public function it_can_list_all_item_types(): void
    {
        $response = $this->getJson('/api/v1/lookups/item-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'description'],
                ],
            ]);
    }

    #[Test]
    public function it_can_search_item_types_using_q_parameter(): void
    {
        $response = $this->getJson('/api/v1/lookups/item-types?q=weapon');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Search should return results for "weapon"');

        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('weapon', $item['name']);
        }
    }

    #[Test]
    public function it_returns_empty_results_when_no_item_types_match_search(): void
    {
        $response = $this->getJson('/api/v1/lookups/item-types?q=nonexistent123');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        $response = $this->getJson('/api/v1/lookups/item-types?q=AMMUNITION');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Case insensitive search should work');
    }
}

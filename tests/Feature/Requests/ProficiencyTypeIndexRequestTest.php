<?php

namespace Tests\Feature\Requests;

use Database\Seeders\ProficiencyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProficiencyTypeIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProficiencyTypeSeeder::class);
    }

    #[Test]
    public function it_returns_paginated_proficiency_types()
    {
        $response = $this->getJson('/api/v1/lookups/proficiency-types?per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'category', 'subcategory'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(5, 'data');
    }

    #[Test]
    public function it_validates_per_page_limit()
    {
        // Valid: 50
        $response = $this->getJson('/api/v1/lookups/proficiency-types?per_page=50');
        $response->assertStatus(200);

        // Invalid: 101 (exceeds max of 100)
        $response = $this->getJson('/api/v1/lookups/proficiency-types?per_page=101');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function it_searches_by_name()
    {
        $response = $this->getJson('/api/v1/lookups/proficiency-types?q=long');

        $response->assertStatus(200);

        // Verify only matching results are returned
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('long', $item['name']);
        }
    }

    #[Test]
    public function it_filters_by_category()
    {
        $response = $this->getJson('/api/v1/lookups/proficiency-types?category=weapon');

        $response->assertStatus(200);

        // Verify all results have weapon category
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals('weapon', $item['category']);
        }
    }

    #[Test]
    public function it_validates_q_max_length()
    {
        // Valid: 255 characters
        $search = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/lookups/proficiency-types?q={$search}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $search = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/lookups/proficiency-types?q={$search}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_validates_page_is_positive_integer()
    {
        // Valid: 1
        $response = $this->getJson('/api/v1/lookups/proficiency-types?page=1');
        $response->assertStatus(200);

        // Invalid: 0
        $response = $this->getJson('/api/v1/lookups/proficiency-types?page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // Invalid: -1
        $response = $this->getJson('/api/v1/lookups/proficiency-types?page=-1');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    #[Test]
    public function it_validates_category_max_length()
    {
        // Valid: 255 characters
        $category = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/lookups/proficiency-types?category={$category}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $category = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/lookups/proficiency-types?category={$category}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }
}

<?php

namespace Tests\Feature\Requests;

use App\Models\ProficiencyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProficiencyTypeIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_paginated_proficiency_types()
    {
        ProficiencyType::factory()->count(10)->create();

        $response = $this->getJson('/api/v1/proficiency-types?per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'category', 'subcategory'],
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
        $response = $this->getJson('/api/v1/proficiency-types?per_page=50');
        $response->assertStatus(200);

        // Invalid: 101 (exceeds max of 100)
        $response = $this->getJson('/api/v1/proficiency-types?per_page=101');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function it_searches_by_name()
    {
        ProficiencyType::factory()->create(['name' => 'Longsword', 'category' => 'weapon']);
        ProficiencyType::factory()->create(['name' => 'Shortsword', 'category' => 'weapon']);
        ProficiencyType::factory()->create(['name' => 'Heavy Armor', 'category' => 'armor']);

        $response = $this->getJson('/api/v1/proficiency-types?search=long');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Longsword');
    }

    #[Test]
    public function it_filters_by_category()
    {
        ProficiencyType::factory()->create(['name' => 'Longsword', 'category' => 'weapon']);
        ProficiencyType::factory()->create(['name' => 'Shortsword', 'category' => 'weapon']);
        ProficiencyType::factory()->create(['name' => 'Heavy Armor', 'category' => 'armor']);

        $response = $this->getJson('/api/v1/proficiency-types?category=weapon');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_validates_search_max_length()
    {
        // Valid: 255 characters
        $search = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/proficiency-types?search={$search}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $search = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/proficiency-types?search={$search}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    #[Test]
    public function it_validates_page_is_positive_integer()
    {
        // Valid: 1
        $response = $this->getJson('/api/v1/proficiency-types?page=1');
        $response->assertStatus(200);

        // Invalid: 0
        $response = $this->getJson('/api/v1/proficiency-types?page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // Invalid: -1
        $response = $this->getJson('/api/v1/proficiency-types?page=-1');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    #[Test]
    public function it_validates_category_max_length()
    {
        // Valid: 255 characters
        $category = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/proficiency-types?category={$category}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $category = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/proficiency-types?category={$category}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }
}

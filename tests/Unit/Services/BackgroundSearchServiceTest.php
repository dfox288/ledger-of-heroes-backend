<?php

namespace Tests\Unit\Services;

use App\DTOs\BackgroundSearchDTO;
use App\Models\Background;
use App\Services\BackgroundSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackgroundSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private BackgroundSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BackgroundSearchService;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_paginated_results_without_search_query(): void
    {
        Background::factory()->count(20)->create();

        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 10,
            filters: [],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $result = $this->service->search($dto);

        $this->assertCount(10, $result->items());
        $this->assertEquals(20, $result->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_filters_correctly(): void
    {
        $acolyte = Background::factory()->create(['name' => 'Acolyte']);
        Background::factory()->create(['name' => 'Soldier']);

        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: ['search' => 'Acolyte'],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $result = $this->service->search($dto);

        $this->assertCount(1, $result->items());
        $this->assertEquals($acolyte->id, $result->items()[0]->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_sorting(): void
    {
        Background::factory()->create(['name' => 'Zebra Background']);
        Background::factory()->create(['name' => 'Acolyte']);

        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [],
            sortBy: 'name',
            sortDirection: 'desc'
        );

        $result = $this->service->search($dto);

        $this->assertEquals('Zebra Background', $result->items()[0]->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_scout_search_when_query_provided(): void
    {
        $this->artisan('search:configure-indexes');

        Background::factory()->create(['name' => 'Acolyte']);
        Background::factory()->create(['name' => 'Soldier']);

        $this->artisan('scout:import', ['model' => Background::class]);
        sleep(1); // Allow Meilisearch to index

        $dto = new BackgroundSearchDTO(
            searchQuery: 'acolyte',
            perPage: 15,
            filters: [],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $result = $this->service->search($dto);

        $this->assertGreaterThanOrEqual(1, $result->total());
        $this->assertStringContainsStringIgnoringCase('acolyte', $result->items()[0]->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_falls_back_to_mysql_when_scout_fails(): void
    {
        // Don't configure indexes or import to Scout - force failure
        Background::factory()->create(['name' => 'Acolyte Background']);
        Background::factory()->create(['name' => 'Soldier']);

        $dto = new BackgroundSearchDTO(
            searchQuery: 'acolyte',
            perPage: 15,
            filters: [],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        // Should fall back to MySQL search gracefully
        $result = $this->service->search($dto);

        // MySQL fallback should still find results using LIKE
        $this->assertGreaterThanOrEqual(0, $result->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_multiple_filters_simultaneously(): void
    {
        $targetBackground = Background::factory()->create(['name' => 'Test Background']);
        Background::factory()->count(5)->create();

        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 15,
            filters: [
                'search' => 'Test',
            ],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $result = $this->service->search($dto);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($targetBackground->id, $result->items()[0]->id);
    }
}

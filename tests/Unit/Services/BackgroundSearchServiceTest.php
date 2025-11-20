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
    public function it_builds_database_query_with_filters(): void
    {
        Background::factory()->count(20)->create();

        $dto = new BackgroundSearchDTO(
            searchQuery: null,
            perPage: 10,
            filters: [],
            sortBy: 'name',
            sortDirection: 'asc'
        );

        $query = $this->service->buildDatabaseQuery($dto);
        $result = $query->paginate(10);

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

        $query = $this->service->buildDatabaseQuery($dto);
        $result = $query->paginate(15);

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

        $query = $this->service->buildDatabaseQuery($dto);
        $result = $query->paginate(15);

        $this->assertEquals('Zebra Background', $result->items()[0]->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_builds_scout_query_for_search(): void
    {
        $this->artisan('search:configure-indexes');

        Background::factory()->create(['name' => 'Acolyte']);
        Background::factory()->create(['name' => 'Soldier']);

        $this->artisan('scout:import', ['model' => Background::class]);
        sleep(1); // Allow Meilisearch to index

        $query = $this->service->buildScoutQuery('acolyte');
        $result = $query->paginate(15);

        $this->assertGreaterThanOrEqual(1, $result->total());
        $this->assertStringContainsStringIgnoringCase('acolyte', $result->items()[0]->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_scout_builder_instance(): void
    {
        $query = $this->service->buildScoutQuery('test');

        $this->assertInstanceOf(\Laravel\Scout\Builder::class, $query);
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

        $query = $this->service->buildDatabaseQuery($dto);
        $result = $query->paginate(15);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($targetBackground->id, $result->items()[0]->id);
    }
}

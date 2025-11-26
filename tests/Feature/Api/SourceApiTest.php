<?php

namespace Tests\Feature\Api;

use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class SourceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test sources via factory
        if (Source::count() === 0) {
            Source::factory()->create(['code' => 'PHB', 'name' => 'Player\'s Handbook']);
            Source::factory()->create(['code' => 'DMG', 'name' => 'Dungeon Master\'s Guide']);
            Source::factory()->create(['code' => 'XGE', 'name' => 'Xanathar\'s Guide to Everything']);
        }
    }

    #[Test]
    public function it_can_list_all_sources(): void
    {
        $response = $this->getJson('/api/v1/lookups/sources');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'publisher', 'publication_year'],
                ],
            ]);
    }

    #[Test]
    public function it_can_search_sources_by_name_using_q_parameter(): void
    {
        $response = $this->getJson('/api/v1/lookups/sources?q=xanathar');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Search should return results for "xanathar"');

        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('xanathar', $item['name']);
        }
    }

    #[Test]
    public function it_can_search_sources_by_code_using_q_parameter(): void
    {
        $response = $this->getJson('/api/v1/lookups/sources?q=XGE');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Search should return results for "XGE"');

        $names = collect($data)->pluck('code')->toArray();
        $this->assertContains('XGE', $names);
    }

    #[Test]
    public function it_returns_empty_results_when_no_sources_match_search(): void
    {
        $response = $this->getJson('/api/v1/lookups/sources?q=nonexistent123');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    #[Test]
    public function it_returns_all_sources_when_no_search_query_provided(): void
    {
        $totalSources = Source::count();

        $response = $this->getJson('/api/v1/lookups/sources');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount($totalSources, $data);
    }

    #[Test]
    public function it_can_get_a_single_source_by_id(): void
    {
        $source = Source::first();

        $response = $this->getJson("/api/v1/lookups/sources/{$source->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $source->id)
            ->assertJsonPath('data.code', $source->code)
            ->assertJsonPath('data.name', $source->name);
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        $response = $this->getJson('/api/v1/lookups/sources?q=XANATHAR');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Case insensitive search should work');
    }

    #[Test]
    public function it_supports_pagination(): void
    {
        $response = $this->getJson('/api/v1/lookups/sources?per_page=2');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.per_page', 2);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class LanguageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    #[Test]
    public function it_can_list_all_languages(): void
    {
        $response = $this->getJson('/api/v1/lookups/languages');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ]);
    }

    #[Test]
    public function it_can_search_languages_using_q_parameter(): void
    {
        $response = $this->getJson('/api/v1/lookups/languages?q=elvish');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Search should return results for "elvish"');

        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('elvish', $item['name']);
        }
    }

    #[Test]
    public function it_returns_empty_results_when_no_languages_match_search(): void
    {
        $response = $this->getJson('/api/v1/lookups/languages?q=nonexistent123');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    #[Test]
    public function it_returns_all_languages_when_no_search_query_provided(): void
    {
        $totalLanguages = Language::count();

        $response = $this->getJson('/api/v1/lookups/languages');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount($totalLanguages, $data);
    }

    #[Test]
    public function it_can_get_a_single_language_by_id(): void
    {
        $language = Language::first();

        $response = $this->getJson("/api/v1/lookups/languages/{$language->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $language->id)
            ->assertJsonPath('data.name', $language->name)
            ->assertJsonPath('data.slug', $language->slug);
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        $response = $this->getJson('/api/v1/lookups/languages?q=COMMON');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Case insensitive search should work');
    }

    #[Test]
    public function it_supports_pagination(): void
    {
        $response = $this->getJson('/api/v1/lookups/languages?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.per_page', 5);
    }
}

<?php

namespace Tests\Feature\Requests;

use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LanguageIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_paginates_languages()
    {
        Language::factory()->count(10)->create();

        $response = $this->getJson('/api/v1/languages?per_page=5');
        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    #[Test]
    public function it_searches_languages_by_name()
    {
        Language::factory()->create(['name' => 'Test Language Alpha', 'slug' => 'test-language-alpha']);
        Language::factory()->create(['name' => 'Test Language Beta', 'slug' => 'test-language-beta']);

        $response = $this->getJson('/api/v1/languages?search=Alpha');
        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Test Language Alpha']);
        $response->assertJsonMissing(['name' => 'Test Language Beta']);
    }

    #[Test]
    public function it_validates_per_page_limit()
    {
        $response = $this->getJson('/api/v1/languages?per_page=101');
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function it_validates_page_is_positive_integer()
    {
        // Valid: 1
        $response = $this->getJson('/api/v1/languages?page=1');
        $response->assertStatus(200);

        // Invalid: 0
        $response = $this->getJson('/api/v1/languages?page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // Invalid: -1
        $response = $this->getJson('/api/v1/languages?page=-1');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    #[Test]
    public function it_validates_search_max_length()
    {
        // Valid: 255 characters
        $search = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/languages?search={$search}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $search = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/languages?search={$search}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }
}

<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-imported')]
class SpellFilterExceptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_422_for_invalid_meilisearch_filter(): void
    {
        // This test assumes Meilisearch is running and will reject invalid filters
        $response = $this->getJson('/api/v1/spells?filter=nonexistent_field = value');

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'error',
            'filter',
            'documentation',
        ]);
        $response->assertJson([
            'message' => 'Invalid filter syntax',
            'filter' => 'nonexistent_field = value',
        ]);
    }

    #[Test]
    public function it_provides_documentation_link_in_error_response(): void
    {
        $response = $this->getJson('/api/v1/spells?filter=invalid_syntax');

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('documentation', $data);
        $this->assertStringContainsString('docs/meilisearch-filters', $data['documentation']);
    }

    #[Test]
    public function it_includes_meilisearch_error_message(): void
    {
        $response = $this->getJson('/api/v1/spells?filter=nonexistent_field = value');

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('error', $data);
        $this->assertNotEmpty($data['error']);
    }
}

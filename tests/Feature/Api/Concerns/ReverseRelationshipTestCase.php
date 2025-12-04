<?php

namespace Tests\Feature\Api\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Abstract base class for testing reverse relationship API endpoints.
 *
 * This class provides reusable test methods for lookup entities that have
 * reverse relationship endpoints (e.g., /api/v1/lookups/conditions/poisoned/spells).
 *
 * Subclasses define their specific configurations and call the test helper methods.
 *
 * Reduces code duplication across 8+ reverse relationship test files by
 * centralizing common assertion patterns for:
 * - Returning related entities
 * - Returning empty results
 * - Accepting slug/code/name identifiers
 * - Paginating results
 */
abstract class ReverseRelationshipTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Test helper: Assert endpoint returns related entities.
     *
     * @param string $route Full API route (e.g., "/api/v1/lookups/conditions/poisoned/spells")
     * @param int $expectedCount Expected number of entities in response
     * @param array $expectedNames Expected names in order
     * @return \Illuminate\Testing\TestResponse
     */
    protected function assertReturnsRelatedEntities(string $route, int $expectedCount, array $expectedNames = []): \Illuminate\Testing\TestResponse
    {
        $response = $this->getJson($route);

        $response->assertOk()
            ->assertJsonCount($expectedCount, 'data');

        foreach ($expectedNames as $index => $name) {
            $response->assertJsonPath("data.{$index}.name", $name);
        }

        return $response;
    }

    /**
     * Test helper: Assert endpoint returns empty array.
     *
     * @param string $route Full API route
     * @return \Illuminate\Testing\TestResponse
     */
    protected function assertReturnsEmpty(string $route): \Illuminate\Testing\TestResponse
    {
        $response = $this->getJson($route);

        $response->assertOk()
            ->assertJsonCount(0, 'data');

        return $response;
    }

    /**
     * Test helper: Assert endpoint paginates results correctly.
     *
     * @param string $route Full API route with pagination params
     * @param int $expectedDataCount Expected number in current page
     * @param int $expectedTotal Total count across all pages
     * @param int $expectedPerPage Per-page value
     * @return \Illuminate\Testing\TestResponse
     */
    protected function assertPaginatesCorrectly(
        string $route,
        int $expectedDataCount,
        int $expectedTotal,
        int $expectedPerPage
    ): \Illuminate\Testing\TestResponse {
        $response = $this->getJson($route);

        $response->assertOk()
            ->assertJsonCount($expectedDataCount, 'data')
            ->assertJsonPath('meta.total', $expectedTotal)
            ->assertJsonPath('meta.per_page', $expectedPerPage);

        return $response;
    }

    /**
     * Test helper: Assert endpoint accepts alternative identifier.
     *
     * @param string $route Full API route using slug/code/name
     * @param int $expectedMinCount Minimum expected count (use 1 for "has results")
     * @return \Illuminate\Testing\TestResponse
     */
    protected function assertAcceptsAlternativeIdentifier(string $route, int $expectedMinCount = 1): \Illuminate\Testing\TestResponse
    {
        $response = $this->getJson($route);

        $response->assertOk();

        $count = count($response->json('data'));
        $this->assertGreaterThanOrEqual($expectedMinCount, $count);

        return $response;
    }

    /**
     * Test helper: Create multiple related entities via callback.
     *
     * @param int $count Number of entities to create
     * @param \Closure $createCallback Callback that creates one entity: function() => Model
     * @return array Created entities
     */
    protected function createMultipleEntities(int $count, \Closure $createCallback): array
    {
        $entities = [];
        for ($i = 0; $i < $count; $i++) {
            $entities[] = $createCallback();
        }
        return $entities;
    }
}

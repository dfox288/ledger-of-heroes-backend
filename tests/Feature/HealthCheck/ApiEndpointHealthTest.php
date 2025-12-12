<?php

namespace Tests\Feature\HealthCheck;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\OpenApiEndpointExtractor;
use Tests\Support\Traits\CreatesHealthCheckFixtures;
use Tests\TestCase;

/**
 * API Health Check Suite
 *
 * Smoke tests all GET endpoints discovered from api.json.
 * Verifies endpoints return 200 with valid JSON structure.
 *
 * Run: docker compose exec php ./vendor/bin/pest --testsuite=Health-Check
 */
class ApiEndpointHealthTest extends TestCase
{
    use CreatesHealthCheckFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHealthCheckFixtures();
    }

    #[DataProvider('endpointProvider')]
    public function test_endpoint_returns_valid_response(string $key, array $endpoint): void
    {
        $path = $this->substitutePathParams($endpoint['path'], $endpoint['params']);

        $response = $this->getJson("/api{$path}");

        // Must return 200
        $this->assertEquals(
            200,
            $response->status(),
            "Endpoint {$key} returned status {$response->status()}: ".$response->getContent()
        );

        // Must be valid JSON with 'data' key
        $json = $response->json();
        $this->assertIsArray($json, "Endpoint {$key} did not return JSON array");
        $this->assertArrayHasKey('data', $json, "Endpoint {$key} missing 'data' key");

        // Paginated endpoints must have 'meta' key
        if ($endpoint['paginated']) {
            $this->assertArrayHasKey('meta', $json, "Paginated endpoint {$key} missing 'meta' key");
        }
    }

    public static function endpointProvider(): array
    {
        // Static provider - load endpoints once
        $endpoints = OpenApiEndpointExtractor::getTestableEndpoints();

        return array_map(
            fn ($endpoint, $key) => [$key, $endpoint],
            $endpoints,
            array_keys($endpoints)
        );
    }
}

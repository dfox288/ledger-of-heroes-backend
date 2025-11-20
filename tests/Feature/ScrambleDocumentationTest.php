<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScrambleDocumentationTest extends TestCase
{
    protected function getOpenApiSpec(): array
    {
        // Generate fresh OpenAPI spec
        Artisan::call('scramble:export', ['--path' => 'api-test.json']);

        $specPath = base_path('api-test.json');
        $this->assertFileExists($specPath, 'Scramble failed to generate OpenAPI spec');

        $spec = json_decode(file_get_contents($specPath), true);
        $this->assertIsArray($spec, 'Generated OpenAPI spec is not valid JSON');

        // Clean up test file
        @unlink($specPath);

        return $spec;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_valid_openapi_specification()
    {
        $spec = $this->getOpenApiSpec();

        $this->assertArrayHasKey('openapi', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_documents_feat_endpoint_with_correct_response_schema()
    {
        $spec = $this->getOpenApiSpec();

        $this->assertArrayHasKey('/v1/feats', $spec['paths']);

        $getOperation = $spec['paths']['/v1/feats']['get'];
        $this->assertArrayHasKey('responses', $getOperation);
        $this->assertArrayHasKey('200', $getOperation['responses']);

        $successResponse = $getOperation['responses']['200'];
        $schema = $successResponse['content']['application/json']['schema'];

        // Should have paginated structure
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('data', $schema['properties']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_documents_class_endpoint_with_correct_response_schema()
    {
        $spec = $this->getOpenApiSpec();

        $this->assertArrayHasKey('/v1/classes', $spec['paths']);

        $getOperation = $spec['paths']['/v1/classes']['get'];
        $successResponse = $getOperation['responses']['200'];
        $schema = $successResponse['content']['application/json']['schema'];

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('data', $schema['properties']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_documents_background_endpoint_with_correct_response_schema()
    {
        $spec = $this->getOpenApiSpec();

        $this->assertArrayHasKey('/v1/backgrounds', $spec['paths']);

        $getOperation = $spec['paths']['/v1/backgrounds']['get'];
        $successResponse = $getOperation['responses']['200'];
        $schema = $successResponse['content']['application/json']['schema'];

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('data', $schema['properties']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_documents_search_endpoint_with_grouped_results()
    {
        $spec = $this->getOpenApiSpec();

        $this->assertArrayHasKey('/v1/search', $spec['paths']);

        $getOperation = $spec['paths']['/v1/search']['get'];
        $successResponse = $getOperation['responses']['200'];
        $schema = $successResponse['content']['application/json']['schema'];

        // Verify structure includes data and meta
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('data', $schema['properties']);
        $this->assertArrayHasKey('meta', $schema['properties']);

        // Verify data has all entity types
        $dataProperties = $schema['properties']['data']['properties'];
        $this->assertArrayHasKey('spells', $dataProperties);
        $this->assertArrayHasKey('items', $dataProperties);
        $this->assertArrayHasKey('races', $dataProperties);
        $this->assertArrayHasKey('classes', $dataProperties);
        $this->assertArrayHasKey('backgrounds', $dataProperties);
        $this->assertArrayHasKey('feats', $dataProperties);
    }
}

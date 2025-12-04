<?php

namespace Tests\Unit\Http\Resources\Concerns;

use App\Http\Resources\Concerns\FormatsRelatedModels;
use App\Models\ProficiencyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormatsRelatedModelsTest extends TestCase
{
    use RefreshDatabase;

    private object $resource;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an anonymous class that uses the trait
        $this->resource = new class
        {
            use FormatsRelatedModels;

            // Expose protected methods for testing
            public function test_format_entity($entity, array $fields = ['id', 'name', 'slug']): ?array
            {
                return $this->formatEntity($entity, $fields);
            }

            public function test_format_entity_with($entity, array $fields, array $computed): ?array
            {
                return $this->formatEntityWith($entity, $fields, $computed);
            }

            public function test_format_entity_with_extra($entity, array $extraFields): ?array
            {
                return $this->formatEntityWithExtra($entity, $extraFields);
            }
        };
    }

    #[Test]
    public function format_entity_returns_null_for_null_input(): void
    {
        $result = $this->resource->test_format_entity(null);

        $this->assertNull($result);
    }

    #[Test]
    public function format_entity_extracts_default_fields(): void
    {
        $profType = ProficiencyType::factory()->create();

        $result = $this->resource->test_format_entity($profType);

        $this->assertSame($profType->id, $result['id']);
        $this->assertSame($profType->name, $result['name']);
        $this->assertSame($profType->slug, $result['slug']);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function format_entity_extracts_custom_fields(): void
    {
        $profType = ProficiencyType::factory()->create([
            'category' => 'armor',
        ]);

        $result = $this->resource->test_format_entity($profType, ['id', 'name', 'category']);

        $this->assertSame($profType->id, $result['id']);
        $this->assertSame($profType->name, $result['name']);
        $this->assertSame('armor', $result['category']);
        $this->assertCount(3, $result);
        $this->assertArrayNotHasKey('slug', $result);
    }

    #[Test]
    public function format_entity_with_returns_null_for_null_input(): void
    {
        $result = $this->resource->test_format_entity_with(null, ['id'], []);

        $this->assertNull($result);
    }

    #[Test]
    public function format_entity_with_merges_static_and_computed_fields(): void
    {
        $profType = ProficiencyType::factory()->create([
            'name' => 'Light Armor',
        ]);

        $result = $this->resource->test_format_entity_with(
            $profType,
            ['id', 'name'],
            ['name_upper' => fn ($p) => strtoupper($p->name)]
        );

        $this->assertSame($profType->id, $result['id']);
        $this->assertSame('Light Armor', $result['name']);
        $this->assertSame('LIGHT ARMOR', $result['name_upper']);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function format_entity_with_extra_returns_null_for_null_input(): void
    {
        $result = $this->resource->test_format_entity_with_extra(null, ['category']);

        $this->assertNull($result);
    }

    #[Test]
    public function format_entity_with_extra_adds_to_standard_fields(): void
    {
        $profType = ProficiencyType::factory()->create([
            'category' => 'weapon',
        ]);

        $result = $this->resource->test_format_entity_with_extra($profType, ['category']);

        $this->assertSame($profType->id, $result['id']);
        $this->assertSame($profType->name, $result['name']);
        $this->assertSame($profType->slug, $result['slug']);
        $this->assertSame('weapon', $result['category']);
        $this->assertCount(4, $result);
    }
}

<?php

namespace Tests\Unit\Exceptions\Lookup;

use App\Exceptions\Lookup\EntityNotFoundException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class EntityNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function it_constructs_with_entity_details(): void
    {
        $exception = new EntityNotFoundException(
            entityType: 'SpellSchool',
            identifier: 'INVALID',
            column: 'code'
        );

        $this->assertEquals(404, $exception->getCode());
        $this->assertStringContainsString('SpellSchool not found', $exception->getMessage());
        $this->assertStringContainsString('INVALID', $exception->getMessage());
        $this->assertStringContainsString('code', $exception->getMessage());
    }

    #[Test]
    public function it_defaults_to_id_column(): void
    {
        $exception = new EntityNotFoundException(
            entityType: 'Source',
            identifier: '999'
        );

        $this->assertEquals('id', $exception->column);
        $this->assertStringContainsString('id', $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $exception = new EntityNotFoundException(
            entityType: 'SpellSchool',
            identifier: 'INVALID',
            column: 'code'
        );

        $request = Request::create('/api/v1/lookup', 'GET');
        $response = $exception->render($request);

        $this->assertEquals(404, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('SpellSchool not found', $data['message']);
        $this->assertEquals('INVALID', $data['identifier']);
        $this->assertEquals('code', $data['search_column']);
    }

    #[Test]
    public function it_exposes_details_as_public_properties(): void
    {
        $exception = new EntityNotFoundException(
            entityType: 'Source',
            identifier: 'PHB',
            column: 'code'
        );

        $this->assertEquals('Source', $exception->entityType);
        $this->assertEquals('PHB', $exception->identifier);
        $this->assertEquals('code', $exception->column);
    }
}

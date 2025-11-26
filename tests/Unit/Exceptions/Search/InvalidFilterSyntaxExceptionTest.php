<?php

namespace Tests\Unit\Exceptions\Search;

use App\Exceptions\Search\InvalidFilterSyntaxException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class InvalidFilterSyntaxExceptionTest extends TestCase
{
    #[Test]
    public function it_constructs_with_filter_and_message(): void
    {
        $exception = new InvalidFilterSyntaxException(
            filter: 'invalid_field = value',
            meilisearchMessage: 'Attribute `invalid_field` is not filterable'
        );

        $this->assertEquals(422, $exception->getCode());
        $this->assertStringContainsString('Invalid filter syntax', $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $exception = new InvalidFilterSyntaxException(
            filter: 'invalid_field = value',
            meilisearchMessage: 'Attribute `invalid_field` is not filterable'
        );

        $request = Request::create('/api/v1/spells', 'GET');
        $response = $exception->render($request);

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('Invalid filter syntax', $data['message']);
        $this->assertEquals('invalid_field = value', $data['filter']);
        $this->assertEquals('Attribute `invalid_field` is not filterable', $data['error']);
        $this->assertArrayHasKey('documentation', $data);
    }

    #[Test]
    public function it_preserves_previous_exception(): void
    {
        $previous = new \Exception('Original error');

        $exception = new InvalidFilterSyntaxException(
            filter: 'test = 1',
            meilisearchMessage: 'Test error',
            previous: $previous
        );

        $this->assertSame($previous, $exception->getPrevious());
    }
}

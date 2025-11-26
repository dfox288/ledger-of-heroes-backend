<?php

namespace Tests\Unit\Exceptions\Import;

use App\Exceptions\Import\FileNotFoundException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class FileNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function it_constructs_with_file_path(): void
    {
        $exception = new FileNotFoundException('/path/to/missing/file.xml');

        $this->assertEquals(404, $exception->getCode());
        $this->assertStringContainsString('Import file not found', $exception->getMessage());
        $this->assertStringContainsString('/path/to/missing/file.xml', $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response_for_api(): void
    {
        $exception = new FileNotFoundException('/path/to/missing/file.xml');

        $request = Request::create('/api/import', 'POST');
        $response = $exception->render($request);

        $this->assertEquals(404, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('Import file not found', $data['message']);
        $this->assertEquals('/path/to/missing/file.xml', $data['file_path']);
    }

    #[Test]
    public function it_exposes_file_path_as_public_property(): void
    {
        $filePath = '/path/to/missing/file.xml';
        $exception = new FileNotFoundException($filePath);

        $this->assertEquals($filePath, $exception->filePath);
    }
}

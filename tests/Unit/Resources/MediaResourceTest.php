<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\MediaResource;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class MediaResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
    }

    /**
     * Create a temp file for media library additions.
     */
    private function createTempFile(string $filename = 'test.jpg'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'fake image content for testing');
        $newPath = $tempFile.'_'.$filename;
        rename($tempFile, $newPath);

        return $newPath;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_transforms_media_with_basic_fields(): void
    {
        $character = Character::factory()->create();

        $media = $character->addMedia($this->createTempFile('portrait.jpg'))
            ->toMediaCollection('portrait');

        $resource = new MediaResource($media);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('collection', $array);
        $this->assertArrayHasKey('file_name', $array);
        $this->assertArrayHasKey('mime_type', $array);
        $this->assertArrayHasKey('size', $array);
        $this->assertArrayHasKey('urls', $array);
        $this->assertArrayHasKey('created_at', $array);

        $this->assertEquals($media->id, $array['id']);
        $this->assertEquals('portrait', $array['collection']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_urls_array_with_original(): void
    {
        $character = Character::factory()->create();

        $media = $character->addMedia($this->createTempFile('portrait.jpg'))
            ->toMediaCollection('portrait');

        $resource = new MediaResource($media);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('urls', $array);
        $this->assertArrayHasKey('original', $array['urls']);
        $this->assertNotNull($array['urls']['original']);
        $this->assertIsString($array['urls']['original']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_null_thumb_when_conversion_does_not_exist(): void
    {
        $character = Character::factory()->create();

        $media = $character->addMedia($this->createTempFile('portrait.jpg'))
            ->toMediaCollection('portrait');

        $resource = new MediaResource($media);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('thumb', $array['urls']);
        $this->assertNull($array['urls']['thumb']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_null_medium_when_conversion_does_not_exist(): void
    {
        $character = Character::factory()->create();

        $media = $character->addMedia($this->createTempFile('portrait.jpg'))
            ->toMediaCollection('portrait');

        $resource = new MediaResource($media);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('medium', $array['urls']);
        $this->assertNull($array['urls']['medium']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_formats_created_at_as_iso8601(): void
    {
        $character = Character::factory()->create();

        $media = $character->addMedia($this->createTempFile('portrait.jpg'))
            ->toMediaCollection('portrait');

        $resource = new MediaResource($media);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('created_at', $array);
        $this->assertNotNull($array['created_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['created_at']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_file_metadata(): void
    {
        $character = Character::factory()->create();

        $media = $character->addMedia($this->createTempFile('test-portrait.jpg'))
            ->toMediaCollection('portrait');

        $resource = new MediaResource($media);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('file_name', $array);
        $this->assertArrayHasKey('mime_type', $array);
        $this->assertArrayHasKey('size', $array);
        $this->assertIsString($array['file_name']);
        $this->assertIsInt($array['size']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_transforms_media_from_different_collections(): void
    {
        $character = Character::factory()->create();

        $portraitMedia = $character->addMedia($this->createTempFile('portrait.jpg'))
            ->toMediaCollection('portrait');

        $tokenMedia = $character->addMedia($this->createTempFile('token.png'))
            ->toMediaCollection('token');

        $portraitResource = new MediaResource($portraitMedia);
        $portraitArray = $portraitResource->toArray(request());

        $tokenResource = new MediaResource($tokenMedia);
        $tokenArray = $tokenResource->toArray(request());

        $this->assertEquals('portrait', $portraitArray['collection']);
        $this->assertEquals('token', $tokenArray['collection']);
    }
}

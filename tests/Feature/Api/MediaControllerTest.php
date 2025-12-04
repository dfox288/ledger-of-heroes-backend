<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Fake the queue so media conversions don't run (they fail on fake files)
        Queue::fake();
    }

    /**
     * Create a temp file for direct media library additions.
     * UploadedFile::fake() doesn't work well with addMedia() since it creates empty files.
     */
    private function createTempFile(string $filename = 'test.jpg'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'fake image content for testing');
        $newPath = $tempFile.'_'.$filename;
        rename($tempFile, $newPath);

        return $newPath;
    }

    #[Test]
    public function it_uploads_portrait_to_character(): void
    {
        $character = Character::factory()->create();
        $file = UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg');

        $response = $this->postJson("/api/v1/characters/{$character->id}/media/portrait", [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'collection',
                    'file_name',
                    'mime_type',
                    'size',
                    'urls' => ['original', 'thumb', 'medium'],
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('media', [
            'model_type' => Character::class,
            'model_id' => $character->id,
            'collection_name' => 'portrait',
        ]);
    }

    #[Test]
    public function it_lists_media_in_collection(): void
    {
        $character = Character::factory()->create();

        $character->addMedia($this->createTempFile('portrait.jpg'))
            ->toMediaCollection('portrait');

        $response = $this->getJson("/api/v1/characters/{$character->id}/media/portrait");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_deletes_portrait_collection(): void
    {
        $character = Character::factory()->create();

        $character->addMedia($this->createTempFile('portrait.jpg'))
            ->toMediaCollection('portrait');

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/media/portrait");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('media', [
            'model_id' => $character->id,
            'collection_name' => 'portrait',
        ]);
    }

    #[Test]
    public function it_deletes_specific_media_by_id(): void
    {
        $character = Character::factory()->create();

        $media = $character->addMedia($this->createTempFile('portrait.jpg'))
            ->toMediaCollection('portrait');

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/media/portrait/{$media->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    #[Test]
    public function it_rejects_invalid_model_type(): void
    {
        $response = $this->postJson('/api/v1/invalid/1/media/portrait', [
            'file' => UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function it_rejects_invalid_collection(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/media/invalid", [
            'file' => UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_rejects_file_exceeding_max_size(): void
    {
        $character = Character::factory()->create();
        // Create file larger than 2MB (2048 KB)
        $file = UploadedFile::fake()->create('large.jpg', 3000, 'image/jpeg');

        $response = $this->postJson("/api/v1/characters/{$character->id}/media/portrait", [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    #[Test]
    public function it_rejects_invalid_mime_type(): void
    {
        $character = Character::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson("/api/v1/characters/{$character->id}/media/portrait", [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->postJson('/api/v1/characters/99999/media/portrait', [
            'file' => UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function it_replaces_existing_portrait_on_single_file_collection(): void
    {
        $character = Character::factory()->create();

        // Upload first portrait
        $character->addMedia($this->createTempFile('first.jpg'))
            ->toMediaCollection('portrait');

        // Upload second portrait via API
        $response = $this->postJson("/api/v1/characters/{$character->id}/media/portrait", [
            'file' => UploadedFile::fake()->create('second.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(201);

        // Should only have one media item
        $this->assertEquals(1, $character->fresh()->getMedia('portrait')->count());
    }
}

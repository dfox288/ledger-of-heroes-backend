<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterResourcePortraitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Fake the queue so media conversions don't run
        Queue::fake();
    }

    /**
     * Create a temp file for direct media library additions.
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
    public function it_includes_uploaded_portrait_in_character_response(): void
    {
        $character = Character::factory()->create();

        $character->addMedia($this->createTempFile('portrait.jpg'))
            ->toMediaCollection('portrait');

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'portrait' => [
                        'original',
                        'thumb',
                        'medium',
                        'is_uploaded',
                    ],
                ],
            ])
            ->assertJsonPath('data.portrait.is_uploaded', true);
    }

    #[Test]
    public function it_includes_external_url_portrait_in_character_response(): void
    {
        $character = Character::factory()->create([
            'portrait_url' => 'https://example.com/my-portrait.jpg',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.portrait.original', 'https://example.com/my-portrait.jpg')
            ->assertJsonPath('data.portrait.thumb', null)
            ->assertJsonPath('data.portrait.medium', null)
            ->assertJsonPath('data.portrait.is_uploaded', false);
    }

    #[Test]
    public function it_returns_null_portrait_when_none_set(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.portrait', null);
    }

    #[Test]
    public function uploaded_portrait_takes_precedence_over_external_url(): void
    {
        $character = Character::factory()->create([
            'portrait_url' => 'https://example.com/external.jpg',
        ]);

        $character->addMedia($this->createTempFile('uploaded.jpg'))
            ->toMediaCollection('portrait');

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.portrait.is_uploaded', true);
    }

    #[Test]
    public function it_can_set_portrait_url_via_patch(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'portrait_url' => 'https://example.com/new-portrait.jpg',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(
            'https://example.com/new-portrait.jpg',
            $character->fresh()->portrait_url
        );
    }

    #[Test]
    public function it_validates_portrait_url_format(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'portrait_url' => 'not-a-valid-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('portrait_url');
    }
}

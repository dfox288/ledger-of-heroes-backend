<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\IncompleteCharacterException;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class IncompleteCharacterExceptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_constructs_with_character(): void
    {
        // Create incomplete character (no race/background set)
        $character = Character::factory()->create([
            'name' => 'Incomplete Hero',
            'race_id' => null,
        ]);

        $exception = new IncompleteCharacterException($character);

        $this->assertEquals('Character must be complete before leveling up.', $exception->getMessage());
        $this->assertSame($character, $exception->character);
    }

    #[Test]
    public function it_accepts_custom_message(): void
    {
        $character = Character::factory()->create();
        $customMessage = 'Please complete character creation first.';

        $exception = new IncompleteCharacterException($character, $customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        // Create incomplete character (no race set = is_complete false)
        $character = Character::factory()->create([
            'name' => 'Unfinished Character',
            'race_id' => null,
        ]);

        $exception = new IncompleteCharacterException($character);
        $response = $exception->render();

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Character must be complete before leveling up.', $data['message']);
        $this->assertEquals($character->id, $data['character_id']);
        // validation_status is computed array with is_complete and missing keys
        $this->assertIsArray($data['validation_status']);
        $this->assertArrayHasKey('is_complete', $data['validation_status']);
        $this->assertFalse($data['validation_status']['is_complete']);
    }
}

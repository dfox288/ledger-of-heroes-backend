<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\MaxLevelReachedException;
use App\Models\Character;
use App\Models\CharacterClassPivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class MaxLevelReachedExceptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_constructs_with_character(): void
    {
        $character = Character::factory()->create(['name' => 'Epic Hero']);
        CharacterClassPivot::factory()->level(20)->create(['character_id' => $character->id]);

        $exception = new MaxLevelReachedException($character);

        $this->assertSame($character, $exception->character);
    }

    #[Test]
    public function it_generates_descriptive_message(): void
    {
        $character = Character::factory()->create(['name' => 'Maximum Power']);
        CharacterClassPivot::factory()->level(20)->create(['character_id' => $character->id]);

        $exception = new MaxLevelReachedException($character);

        $this->assertStringContainsString('Maximum Power', $exception->getMessage());
        $this->assertStringContainsString((string) $character->id, $exception->getMessage());
        $this->assertStringContainsString('maximum level (20)', $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $character = Character::factory()->create(['name' => 'Godlike']);
        CharacterClassPivot::factory()->level(20)->create(['character_id' => $character->id]);
        $character->refresh(); // Refresh to get the computed total_level

        $exception = new MaxLevelReachedException($character);
        $response = $exception->render();

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertStringContainsString('maximum level (20)', $data['message']);
        $this->assertEquals($character->id, $data['character_id']);
        $this->assertEquals(20, $data['current_level']);
    }
}

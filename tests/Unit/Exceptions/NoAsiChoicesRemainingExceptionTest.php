<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\NoAsiChoicesRemainingException;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class NoAsiChoicesRemainingExceptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_constructs_with_character(): void
    {
        $character = Character::factory()->create([
            'name' => 'No Choices Left',
            'asi_choices_remaining' => 0,
        ]);

        $exception = new NoAsiChoicesRemainingException($character);

        $this->assertEquals('No ASI choices remaining. Level up to gain more.', $exception->getMessage());
        $this->assertSame($character, $exception->character);
    }

    #[Test]
    public function it_accepts_custom_message(): void
    {
        $character = Character::factory()->create(['asi_choices_remaining' => 0]);
        $customMessage = 'You need to level up to make more ability score choices.';

        $exception = new NoAsiChoicesRemainingException($character, $customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $character = Character::factory()->create([
            'name' => 'Maxed Out',
            'asi_choices_remaining' => 0,
        ]);

        $exception = new NoAsiChoicesRemainingException($character);
        $response = $exception->render();

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('No ASI choices remaining. Level up to gain more.', $data['message']);
        $this->assertEquals($character->id, $data['character_id']);
        $this->assertEquals(0, $data['asi_choices_remaining']);
    }
}

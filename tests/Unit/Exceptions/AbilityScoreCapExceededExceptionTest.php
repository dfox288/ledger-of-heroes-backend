<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\AbilityScoreCapExceededException;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class AbilityScoreCapExceededExceptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_constructs_with_character_and_ability_details(): void
    {
        $character = Character::factory()->create(['name' => 'Strong Fighter']);

        $exception = new AbilityScoreCapExceededException(
            character: $character,
            abilityCode: 'str',
            currentValue: 18,
            attemptedIncrease: 3
        );

        $this->assertEquals('Ability score cannot exceed 20.', $exception->getMessage());
        $this->assertSame($character, $exception->character);
        $this->assertEquals('str', $exception->abilityCode);
        $this->assertEquals(18, $exception->currentValue);
        $this->assertEquals(3, $exception->attemptedIncrease);
    }

    #[Test]
    public function it_accepts_custom_message(): void
    {
        $character = Character::factory()->create();
        $customMessage = 'You cannot increase this ability further.';

        $exception = new AbilityScoreCapExceededException(
            character: $character,
            abilityCode: 'dex',
            currentValue: 19,
            attemptedIncrease: 2,
            message: $customMessage
        );

        $this->assertEquals($customMessage, $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $character = Character::factory()->create(['name' => 'Wise Wizard']);

        $exception = new AbilityScoreCapExceededException(
            character: $character,
            abilityCode: 'int',
            currentValue: 17,
            attemptedIncrease: 4
        );

        $response = $exception->render();

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Ability score cannot exceed 20.', $data['message']);
        $this->assertEquals($character->id, $data['character_id']);
        $this->assertEquals('int', $data['ability']);
        $this->assertEquals(17, $data['current_value']);
        $this->assertEquals(4, $data['attempted_increase']);
        $this->assertEquals(21, $data['would_be']);
        $this->assertEquals(20, $data['maximum']);
    }

    #[Test]
    public function it_handles_already_at_cap_edge_case(): void
    {
        $character = Character::factory()->create(['name' => 'Maxed Out']);

        $exception = new AbilityScoreCapExceededException(
            character: $character,
            abilityCode: 'str',
            currentValue: 20,
            attemptedIncrease: 1
        );

        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertEquals(20, $data['current_value']);
        $this->assertEquals(1, $data['attempted_increase']);
        $this->assertEquals(21, $data['would_be']);
    }
}

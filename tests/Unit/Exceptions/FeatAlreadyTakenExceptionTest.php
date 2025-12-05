<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\FeatAlreadyTakenException;
use App\Models\Character;
use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class FeatAlreadyTakenExceptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_constructs_with_character_and_feat(): void
    {
        $character = Character::factory()->create(['name' => 'Experienced Fighter']);
        $feat = Feat::factory()->create(['name' => 'Alert']);

        $exception = new FeatAlreadyTakenException($character, $feat);

        $this->assertEquals('Character has already taken this feat.', $exception->getMessage());
        $this->assertSame($character, $exception->character);
        $this->assertSame($feat, $exception->feat);
    }

    #[Test]
    public function it_accepts_custom_message(): void
    {
        $character = Character::factory()->create();
        $feat = Feat::factory()->create();
        $customMessage = 'This feat can only be taken once.';

        $exception = new FeatAlreadyTakenException($character, $feat, $customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $character = Character::factory()->create(['name' => 'Veteran']);
        $feat = Feat::factory()->create(['name' => 'Lucky']);

        $exception = new FeatAlreadyTakenException($character, $feat);
        $response = $exception->render();

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Character has already taken this feat.', $data['message']);
        $this->assertEquals($character->id, $data['character_id']);
        $this->assertEquals($feat->id, $data['feat_id']);
        $this->assertEquals('Lucky', $data['feat_name']);
    }
}

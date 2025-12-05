<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\AbilityChoiceRequiredException;
use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class AbilityChoiceRequiredExceptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_constructs_with_feat_and_allowed_abilities(): void
    {
        $feat = Feat::factory()->create(['name' => 'Resilient']);
        $allowedAbilities = ['str', 'dex', 'con', 'int', 'wis', 'cha'];

        $exception = new AbilityChoiceRequiredException($feat, $allowedAbilities);

        $this->assertEquals('This feat requires choosing an ability score to increase.', $exception->getMessage());
        $this->assertSame($feat, $exception->feat);
        $this->assertEquals($allowedAbilities, $exception->allowedAbilities);
    }

    #[Test]
    public function it_accepts_custom_message(): void
    {
        $feat = Feat::factory()->create();
        $allowedAbilities = ['str', 'con'];
        $customMessage = 'Please select an ability to improve.';

        $exception = new AbilityChoiceRequiredException($feat, $allowedAbilities, $customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $feat = Feat::factory()->create(['name' => 'Resilient']);
        $allowedAbilities = ['str', 'dex', 'con'];

        $exception = new AbilityChoiceRequiredException($feat, $allowedAbilities);
        $response = $exception->render();

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('This feat requires choosing an ability score to increase.', $data['message']);
        $this->assertEquals($feat->id, $data['feat_id']);
        $this->assertEquals('Resilient', $data['feat_name']);
        $this->assertEquals(['str', 'dex', 'con'], $data['allowed_abilities']);
    }
}

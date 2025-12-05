<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\PrerequisitesNotMetException;
use App\Models\Character;
use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class PrerequisitesNotMetExceptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_constructs_with_character_feat_and_prerequisites(): void
    {
        $character = Character::factory()->create(['name' => 'Weak Character']);
        $feat = Feat::factory()->create(['name' => 'Heavy Armor Master']);
        $unmetPrerequisites = [
            ['type' => 'proficiency', 'requirement' => 'Heavy Armor', 'current' => null],
        ];

        $exception = new PrerequisitesNotMetException($character, $feat, $unmetPrerequisites);

        $this->assertEquals('Character does not meet feat prerequisites.', $exception->getMessage());
        $this->assertSame($character, $exception->character);
        $this->assertSame($feat, $exception->feat);
        $this->assertEquals($unmetPrerequisites, $exception->unmetPrerequisites);
    }

    #[Test]
    public function it_accepts_custom_message(): void
    {
        $character = Character::factory()->create();
        $feat = Feat::factory()->create();
        $customMessage = 'Prerequisites not satisfied for this feat.';

        $exception = new PrerequisitesNotMetException($character, $feat, [], $customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $character = Character::factory()->create(['name' => 'Novice']);
        $feat = Feat::factory()->create(['name' => 'Mage Slayer']);
        $unmetPrerequisites = [
            ['type' => 'ability', 'requirement' => 'Dexterity 13', 'current' => 10],
            ['type' => 'level', 'requirement' => 'Level 4', 'current' => 2],
        ];

        $exception = new PrerequisitesNotMetException($character, $feat, $unmetPrerequisites);
        $response = $exception->render();

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Character does not meet feat prerequisites.', $data['message']);
        $this->assertEquals($character->id, $data['character_id']);
        $this->assertEquals($feat->id, $data['feat_id']);
        $this->assertEquals('Mage Slayer', $data['feat_name']);
        $this->assertCount(2, $data['unmet_prerequisites']);
        $this->assertEquals('ability', $data['unmet_prerequisites'][0]['type']);
        $this->assertEquals('Dexterity 13', $data['unmet_prerequisites'][0]['requirement']);
        $this->assertEquals(10, $data['unmet_prerequisites'][0]['current']);
    }
}

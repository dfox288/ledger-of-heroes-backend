<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\SubclassLevelRequirementException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-pure')]
class SubclassLevelRequirementExceptionTest extends TestCase
{
    protected $seed = false;

    #[Test]
    public function it_constructs_with_class_and_level_details(): void
    {
        $exception = new SubclassLevelRequirementException(
            className: 'Fighter',
            currentLevel: 2
        );

        $this->assertEquals('Fighter', $exception->className);
        $this->assertEquals(2, $exception->currentLevel);
        $this->assertEquals(3, $exception->requiredLevel);
    }

    #[Test]
    public function it_accepts_custom_required_level(): void
    {
        $exception = new SubclassLevelRequirementException(
            className: 'Cleric',
            currentLevel: 1,
            requiredLevel: 1
        );

        $this->assertEquals(1, $exception->requiredLevel);
    }

    #[Test]
    public function it_generates_descriptive_message(): void
    {
        $exception = new SubclassLevelRequirementException(
            className: 'Wizard',
            currentLevel: 1
        );

        $this->assertStringContainsString('Cannot set subclass for Wizard', $exception->getMessage());
        $this->assertStringContainsString('at least level 3', $exception->getMessage());
        $this->assertStringContainsString('currently level 1', $exception->getMessage());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $exception = new SubclassLevelRequirementException(
            className: 'Rogue',
            currentLevel: 2,
            requiredLevel: 3
        );

        $response = $exception->render();

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertStringContainsString('Cannot set subclass for Rogue', $data['message']);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('subclass_id', $data['errors']);
        $this->assertEquals(2, $data['current_level']);
        $this->assertEquals(3, $data['required_level']);
    }
}

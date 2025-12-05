<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\InvalidSubclassException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-pure')]
class InvalidSubclassExceptionTest extends TestCase
{
    protected $seed = false;

    #[Test]
    public function it_constructs_with_subclass_and_class_names(): void
    {
        $exception = new InvalidSubclassException(
            subclassName: 'Champion',
            className: 'Wizard'
        );

        $this->assertEquals('Champion', $exception->subclassName);
        $this->assertEquals('Wizard', $exception->className);
    }

    #[Test]
    public function it_generates_descriptive_message(): void
    {
        $exception = new InvalidSubclassException(
            subclassName: 'Path of the Berserker',
            className: 'Rogue'
        );

        $this->assertEquals(
            "Subclass 'Path of the Berserker' does not belong to class 'Rogue'.",
            $exception->getMessage()
        );
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $exception = new InvalidSubclassException(
            subclassName: 'Circle of the Moon',
            className: 'Fighter'
        );

        $response = $exception->render();

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals(
            "Subclass 'Circle of the Moon' does not belong to class 'Fighter'.",
            $data['message']
        );
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('subclass_id', $data['errors']);
        $this->assertEquals(
            ["Subclass 'Circle of the Moon' does not belong to class 'Fighter'."],
            $data['errors']['subclass_id']
        );
    }
}

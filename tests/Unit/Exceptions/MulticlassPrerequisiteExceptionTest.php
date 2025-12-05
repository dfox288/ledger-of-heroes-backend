<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\MulticlassPrerequisiteException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-pure')]
class MulticlassPrerequisiteExceptionTest extends TestCase
{
    protected $seed = false;

    #[Test]
    public function it_constructs_with_errors_array(): void
    {
        $errors = [
            'Strength must be at least 13',
            'Charisma must be at least 13',
        ];

        $exception = new MulticlassPrerequisiteException($errors);

        $this->assertEquals($errors, $exception->errors);
        $this->assertNull($exception->characterId);
        $this->assertNull($exception->characterName);
    }

    #[Test]
    public function it_constructs_with_character_details(): void
    {
        $errors = ['Dexterity must be at least 13'];

        $exception = new MulticlassPrerequisiteException(
            errors: $errors,
            characterId: 42,
            characterName: 'Weak Fighter'
        );

        $this->assertEquals($errors, $exception->errors);
        $this->assertEquals(42, $exception->characterId);
        $this->assertEquals('Weak Fighter', $exception->characterName);
    }

    #[Test]
    public function it_generates_generic_message_without_character(): void
    {
        $exception = new MulticlassPrerequisiteException(['Some error']);

        $this->assertEquals('Character does not meet multiclass prerequisites.', $exception->getMessage());
    }

    #[Test]
    public function it_generates_specific_message_with_character(): void
    {
        $exception = new MulticlassPrerequisiteException(
            errors: ['Some error'],
            characterId: 99,
            characterName: 'Aspiring Multiclasser'
        );

        $this->assertStringContainsString('Aspiring Multiclasser', $exception->getMessage());
        $this->assertStringContainsString('99', $exception->getMessage());
        $this->assertStringContainsString('does not meet multiclass prerequisites', $exception->getMessage());
    }
}

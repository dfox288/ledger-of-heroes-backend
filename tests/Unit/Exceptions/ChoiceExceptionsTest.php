<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ChoiceNotFoundException;
use App\Exceptions\ChoiceNotUndoableException;
use App\Exceptions\InvalidChoiceException;
use App\Exceptions\InvalidSelectionException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-pure')]
class ChoiceExceptionsTest extends TestCase
{
    protected $seed = false;

    #[Test]
    public function invalid_choice_exception_constructs_with_choice_id(): void
    {
        $exception = new InvalidChoiceException(
            choiceId: 'choice-123'
        );

        $this->assertEquals('choice-123', $exception->choiceId);
        $this->assertEquals('Invalid choice', $exception->getMessage());
    }

    #[Test]
    public function invalid_choice_exception_accepts_custom_message(): void
    {
        $exception = new InvalidChoiceException(
            choiceId: 'choice-456',
            message: 'This choice is not valid for your character'
        );

        $this->assertEquals('This choice is not valid for your character', $exception->getMessage());
    }

    #[Test]
    public function invalid_choice_exception_renders_422_response(): void
    {
        $exception = new InvalidChoiceException(
            choiceId: 'choice-789'
        );

        $response = $exception->render(request());

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Invalid choice', $data['message']);
        $this->assertEquals('choice-789', $data['choice_id']);
    }

    #[Test]
    public function choice_not_found_exception_constructs_with_choice_id(): void
    {
        $exception = new ChoiceNotFoundException(
            choiceId: 'missing-choice'
        );

        $this->assertEquals('missing-choice', $exception->choiceId);
        $this->assertEquals('Choice not found', $exception->getMessage());
    }

    #[Test]
    public function choice_not_found_exception_accepts_custom_message(): void
    {
        $exception = new ChoiceNotFoundException(
            choiceId: 'missing-choice',
            message: 'The specified choice does not exist'
        );

        $this->assertEquals('The specified choice does not exist', $exception->getMessage());
    }

    #[Test]
    public function choice_not_found_exception_renders_404_response(): void
    {
        $exception = new ChoiceNotFoundException(
            choiceId: 'nonexistent-choice'
        );

        $response = $exception->render(request());

        $this->assertEquals(404, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Choice not found', $data['message']);
        $this->assertEquals('nonexistent-choice', $data['choice_id']);
    }

    #[Test]
    public function choice_not_undoable_exception_constructs_with_choice_id(): void
    {
        $exception = new ChoiceNotUndoableException(
            choiceId: 'permanent-choice'
        );

        $this->assertEquals('permanent-choice', $exception->choiceId);
        $this->assertEquals('This choice cannot be undone', $exception->getMessage());
    }

    #[Test]
    public function choice_not_undoable_exception_accepts_custom_message(): void
    {
        $exception = new ChoiceNotUndoableException(
            choiceId: 'locked-choice',
            message: 'This choice is permanent and cannot be changed'
        );

        $this->assertEquals('This choice is permanent and cannot be changed', $exception->getMessage());
    }

    #[Test]
    public function choice_not_undoable_exception_renders_422_response(): void
    {
        $exception = new ChoiceNotUndoableException(
            choiceId: 'immutable-choice'
        );

        $response = $exception->render(request());

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('This choice cannot be undone', $data['message']);
        $this->assertEquals('immutable-choice', $data['choice_id']);
    }

    #[Test]
    public function invalid_selection_exception_constructs_with_choice_id_and_selection(): void
    {
        $exception = new InvalidSelectionException(
            choiceId: 'skill-choice',
            selection: 'invalid-skill'
        );

        $this->assertEquals('skill-choice', $exception->choiceId);
        $this->assertEquals('invalid-skill', $exception->selection);
        $this->assertEquals('Invalid selection for choice', $exception->getMessage());
    }

    #[Test]
    public function invalid_selection_exception_accepts_custom_message(): void
    {
        $exception = new InvalidSelectionException(
            choiceId: 'spell-choice',
            selection: 'fireball',
            message: 'This spell is not available for your class'
        );

        $this->assertEquals('This spell is not available for your class', $exception->getMessage());
    }

    #[Test]
    public function invalid_selection_exception_renders_422_response(): void
    {
        $exception = new InvalidSelectionException(
            choiceId: 'feat-choice',
            selection: 'magic-initiate'
        );

        $response = $exception->render(request());

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Invalid selection for choice', $data['message']);
        $this->assertEquals('feat-choice', $data['choice_id']);
        $this->assertEquals('magic-initiate', $data['selection']);
    }
}

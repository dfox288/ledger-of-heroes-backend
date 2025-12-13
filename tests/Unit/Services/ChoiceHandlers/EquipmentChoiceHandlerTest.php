<?php

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Services\ChoiceHandlers\EquipmentChoiceHandler;
use App\Services\EquipmentManagerService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->equipmentService = Mockery::mock(EquipmentManagerService::class);
    $this->handler = new EquipmentChoiceHandler($this->equipmentService);
    $this->character = Mockery::mock(Character::class);
});

afterEach(function () {
    Mockery::close();
});

it('returns correct type', function () {
    expect($this->handler->getType())->toBe('equipment');
});

it('returns empty collection for level 2+ character', function () {
    // Mock character classes relationship - level 2 character
    $characterClasses = new EloquentCollection([
        (object) ['is_primary' => true, 'level' => 2, 'character_class_id' => 5],
    ]);

    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toBeInstanceOf(Collection::class)
        ->and($choices)->toHaveCount(0);
});

it('returns empty collection when character has no class', function () {
    $characterClasses = new EloquentCollection([]);

    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(0);
});

// Note: getChoices() and resolve() are thoroughly tested in EquipmentChoiceReplacementTest
// which uses real database interactions instead of mocks. See:
// - tests/Feature/Services/ChoiceHandlers/EquipmentChoiceReplacementTest.php
// The integration tests cover: single/multiple item resolution, replacement behavior,
// category choices, pack contents, gold mode, and full database flows.

it('throws exception when selected option is invalid', function () {
    $choice = new PendingChoice(
        id: 'equipment|class|phb:rogue|1|equipment_choice_1',
        type: 'equipment',
        subtype: null,
        source: 'class',
        sourceName: 'Fighter',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [
            ['option' => 'a', 'items' => [['id' => 123, 'name' => 'Chain Mail', 'slug' => 'chain-mail', 'quantity' => 1]]],
            ['option' => 'b', 'items' => [['id' => 456, 'name' => 'Leather Armor', 'slug' => 'leather-armor', 'quantity' => 1]]],
        ],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'equipment_choice_1']
    );

    expect(fn () => $this->handler->resolve($this->character, $choice, ['selected' => 'z']))
        ->toThrow(InvalidSelectionException::class);
});

it('throws exception when selection is empty', function () {
    $choice = new PendingChoice(
        id: 'equipment|class|phb:rogue|1|equipment_choice_1',
        type: 'equipment',
        subtype: null,
        source: 'class',
        sourceName: 'Fighter',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [
            ['option' => 'a', 'items' => []],
        ],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'equipment_choice_1']
    );

    expect(fn () => $this->handler->resolve($this->character, $choice, []))
        ->toThrow(InvalidSelectionException::class);
});

it('returns false for canUndo when character is not level 1', function () {
    $choice = new PendingChoice(
        id: 'equipment|class|phb:rogue|1|equipment_choice_1',
        type: 'equipment',
        subtype: null,
        source: 'class',
        sourceName: 'Fighter',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 0,
        selected: ['a'],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'equipment_choice_1']
    );

    // Mock level 2 character
    $characterClasses = new EloquentCollection([
        (object) ['is_primary' => true, 'level' => 2],
    ]);

    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);

    expect($this->handler->canUndo($this->character, $choice))->toBeFalse();
});

it('returns true for canUndo when character is level 1', function () {
    $choice = new PendingChoice(
        id: 'equipment|class|phb:rogue|1|equipment_choice_1',
        type: 'equipment',
        subtype: null,
        source: 'class',
        sourceName: 'Fighter',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 0,
        selected: ['a'],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'equipment_choice_1']
    );

    // Mock level 1 character
    $characterClasses = new EloquentCollection([
        (object) ['is_primary' => true, 'level' => 1],
    ]);

    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);

    expect($this->handler->canUndo($this->character, $choice))->toBeTrue();
});

it('undoes choice by deleting CharacterEquipment records', function () {
    $choice = new PendingChoice(
        id: 'equipment|class|phb:rogue|1|equipment_choice_1',
        type: 'equipment',
        subtype: null,
        source: 'class',
        sourceName: 'Fighter',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 0,
        selected: ['a'],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'equipment_choice_1']
    );

    // Mock equipment relationship - uses whereJsonContains for deletion
    $equipmentQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $equipmentQuery->shouldReceive('whereJsonContains')
        ->with('custom_description->source', 'class')
        ->andReturnSelf();
    $equipmentQuery->shouldReceive('whereJsonContains')
        ->with('custom_description->choice_group', 'equipment_choice_1')
        ->andReturnSelf();
    $equipmentQuery->shouldReceive('delete')
        ->once()
        ->andReturn(1);

    $this->character->shouldReceive('equipment')
        ->once()
        ->andReturn($equipmentQuery);

    $this->character->shouldReceive('load')
        ->once()
        ->with('equipment')
        ->andReturnSelf();

    $this->handler->undo($this->character, $choice);
});

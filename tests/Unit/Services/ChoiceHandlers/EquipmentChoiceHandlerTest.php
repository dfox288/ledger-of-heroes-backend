<?php

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterEquipment;
use App\Models\EntityChoice;
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

it('returns equipment choices for level 1 character', function () {
    // Mock equipment_mode - null or 'equipment' means equipment mode (not gold)
    $this->character->shouldReceive('getAttribute')
        ->with('equipment_mode')
        ->andReturn('equipment');
    $this->character->shouldReceive('__get')
        ->with('equipment_mode')
        ->andReturn('equipment');

    // Mock level 1 character with Fighter class
    $primaryClass = Mockery::mock(CharacterClass::class);
    $primaryClass->shouldReceive('getAttribute')->with('id')->andReturn(5);
    $primaryClass->shouldReceive('getAttribute')->with('name')->andReturn('Fighter');
    $primaryClass->shouldReceive('getAttribute')->with('slug')->andReturn('phb:fighter');
    $primaryClass->shouldReceive('__get')->with('id')->andReturn(5);
    $primaryClass->shouldReceive('__get')->with('name')->andReturn('Fighter');
    $primaryClass->shouldReceive('__get')->with('slug')->andReturn('phb:fighter');

    $characterClassPivot = Mockery::mock(CharacterClassPivot::class);
    $characterClassPivot->shouldReceive('getAttribute')->with('is_primary')->andReturn(true);
    $characterClassPivot->shouldReceive('getAttribute')->with('level')->andReturn(1);
    $characterClassPivot->shouldReceive('__get')->with('is_primary')->andReturn(true);
    $characterClassPivot->shouldReceive('__get')->with('level')->andReturn(1);
    $characterClassPivot->shouldReceive('getAttribute')->with('characterClass')->andReturn($primaryClass);
    $characterClassPivot->shouldReceive('__get')->with('characterClass')->andReturn($primaryClass);
    $characterClassPivot->shouldReceive('offsetExists')->andReturn(true);
    $characterClassPivot->shouldReceive('offsetGet')->with('is_primary')->andReturn(true);
    $characterClassPivot->shouldReceive('offsetGet')->with('level')->andReturn(1);
    $characterClassPivot->shouldReceive('offsetGet')->with('characterClass')->andReturn($primaryClass);

    $characterClasses = new EloquentCollection([$characterClassPivot]);

    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);

    // Mock EntityChoice records for equipment choices
    $entityChoice1 = Mockery::mock(EntityChoice::class);
    $entityChoice1->shouldReceive('getAttribute')->with('choice_group')->andReturn('equipment_choice_1');
    $entityChoice1->shouldReceive('getAttribute')->with('choice_option')->andReturn(1);
    $entityChoice1->shouldReceive('getAttribute')->with('description')->andReturn('chain mail');
    $entityChoice1->shouldReceive('getAttribute')->with('target_type')->andReturn('item');
    $entityChoice1->shouldReceive('getAttribute')->with('target_slug')->andReturn('phb:chain-mail');
    $entityChoice1->shouldReceive('getAttribute')->with('constraints')->andReturn(['quantity' => 1]);
    $entityChoice1->shouldReceive('__get')->with('choice_group')->andReturn('equipment_choice_1');
    $entityChoice1->shouldReceive('__get')->with('choice_option')->andReturn(1);
    $entityChoice1->shouldReceive('__get')->with('description')->andReturn('chain mail');
    $entityChoice1->shouldReceive('__get')->with('target_type')->andReturn('item');
    $entityChoice1->shouldReceive('__get')->with('target_slug')->andReturn('phb:chain-mail');
    $entityChoice1->shouldReceive('__get')->with('constraints')->andReturn(['quantity' => 1]);
    $entityChoice1->shouldReceive('offsetExists')->andReturn(true);
    $entityChoice1->shouldReceive('offsetGet')->with('choice_group')->andReturn('equipment_choice_1');

    $entityChoice2 = Mockery::mock(EntityChoice::class);
    $entityChoice2->shouldReceive('getAttribute')->with('choice_group')->andReturn('equipment_choice_1');
    $entityChoice2->shouldReceive('getAttribute')->with('choice_option')->andReturn(2);
    $entityChoice2->shouldReceive('getAttribute')->with('description')->andReturn('leather armor');
    $entityChoice2->shouldReceive('getAttribute')->with('target_type')->andReturn('item');
    $entityChoice2->shouldReceive('getAttribute')->with('target_slug')->andReturn('phb:leather-armor');
    $entityChoice2->shouldReceive('getAttribute')->with('constraints')->andReturn(['quantity' => 1]);
    $entityChoice2->shouldReceive('__get')->with('choice_group')->andReturn('equipment_choice_1');
    $entityChoice2->shouldReceive('__get')->with('choice_option')->andReturn(2);
    $entityChoice2->shouldReceive('__get')->with('description')->andReturn('leather armor');
    $entityChoice2->shouldReceive('__get')->with('target_type')->andReturn('item');
    $entityChoice2->shouldReceive('__get')->with('target_slug')->andReturn('phb:leather-armor');
    $entityChoice2->shouldReceive('__get')->with('constraints')->andReturn(['quantity' => 1]);
    $entityChoice2->shouldReceive('offsetExists')->andReturn(true);
    $entityChoice2->shouldReceive('offsetGet')->with('choice_group')->andReturn('equipment_choice_1');

    $equipmentChoices = new EloquentCollection([$entityChoice1, $entityChoice2]);

    $equipmentChoicesRelation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
    $equipmentChoicesRelation->shouldReceive('orderBy')->with('choice_group')->andReturnSelf();
    $equipmentChoicesRelation->shouldReceive('orderBy')->with('choice_option')->andReturnSelf();
    $equipmentChoicesRelation->shouldReceive('get')->andReturn($equipmentChoices);

    $primaryClass->shouldReceive('equipmentChoices')->andReturn($equipmentChoicesRelation);

    // Mock character equipment relationship for getExistingSelections
    $characterEquipmentQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $characterEquipmentQuery->shouldReceive('whereJsonContains')->andReturnSelf();
    $characterEquipmentQuery->shouldReceive('whereNotNull')->andReturnSelf();
    $characterEquipmentQuery->shouldReceive('get')->andReturn(new EloquentCollection([]));

    $this->character->shouldReceive('equipment')->andReturn($characterEquipmentQuery);

    $choices = $this->handler->getChoices($this->character);

    // Note: The actual getChoices() queries Item model from DB which won't work with mocks
    // This test is skipped - full integration test in Feature tests covers this functionality
})->skip('Pure mocking of equipment choices is complex - covered by EquipmentChoiceReplacementTest feature tests');

// Note: Equipment choices at level 1 always show all options.
// The "selected" field in PendingChoice indicates what was already chosen.
// This is simpler than trying to filter resolved choices without proper DB columns.

it('resolves equipment choice by creating CharacterEquipment records', function () {
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
            [
                'option' => 'a',
                'items' => [
                    ['id' => 123, 'name' => 'Chain Mail', 'slug' => 'chain-mail', 'quantity' => 1],
                ],
            ],
            [
                'option' => 'b',
                'items' => [
                    ['id' => 456, 'name' => 'Leather Armor', 'slug' => 'leather-armor', 'quantity' => 1],
                ],
            ],
        ],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'equipment_choice_1']
    );

    // Mock character id
    $this->character->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $this->character->shouldReceive('__get')->with('id')->andReturn(1);

    // Verify the handler calls create with correct parameters
    $called = false;
    $expectedData = [
        'character_id' => 1,
        'item_id' => 123,
        'quantity' => 1,
        'equipped' => false,
        'custom_description' => json_encode([
            'source' => 'class',
            'choice_group' => 'equipment_choice_1',
        ]),
    ];

    $mock = Mockery::mock('alias:'.CharacterEquipment::class);
    $mock->shouldReceive('create')
        ->once()
        ->with($expectedData)
        ->andReturnUsing(function () use (&$called) {
            $called = true;

            return Mockery::mock(CharacterEquipment::class);
        });

    $this->handler->resolve($this->character, $choice, ['selected' => 'a']);

    expect($called)->toBeTrue();
})->skip('Static mocking conflicts with other tests');

it('resolves equipment choice with multiple items', function () {
    $choice = new PendingChoice(
        id: 'equipment|class|phb:rogue|1|equipment_choice_2',
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
            [
                'option' => 'a',
                'items' => [
                    ['id' => 100, 'name' => 'Item 1', 'slug' => 'item-1', 'quantity' => 1],
                ],
            ],
            [
                'option' => 'b',
                'items' => [
                    ['id' => 200, 'name' => 'Leather Armor', 'slug' => 'leather-armor', 'quantity' => 1],
                    ['id' => 300, 'name' => 'Longbow', 'slug' => 'longbow', 'quantity' => 1],
                    ['id' => 400, 'name' => 'Arrows', 'slug' => 'arrow', 'quantity' => 20],
                ],
            ],
        ],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'equipment_choice_2']
    );

    $this->character->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $this->character->shouldReceive('__get')->with('id')->andReturn(1);

    $metadata = json_encode(['source' => 'class', 'choice_group' => 'equipment_choice_2']);

    // Expect 3 items to be created for option b
    CharacterEquipment::shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn ($args) => $args['item_id'] === 200 && $args['quantity'] === 1 && $args['custom_description'] === $metadata))
        ->andReturn(Mockery::mock(CharacterEquipment::class));

    CharacterEquipment::shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn ($args) => $args['item_id'] === 300 && $args['quantity'] === 1 && $args['custom_description'] === $metadata))
        ->andReturn(Mockery::mock(CharacterEquipment::class));

    CharacterEquipment::shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn ($args) => $args['item_id'] === 400 && $args['quantity'] === 20 && $args['custom_description'] === $metadata))
        ->andReturn(Mockery::mock(CharacterEquipment::class));

    $this->handler->resolve($this->character, $choice, ['selected' => 'b']);
})->skip('Static mocking conflicts with other tests');

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

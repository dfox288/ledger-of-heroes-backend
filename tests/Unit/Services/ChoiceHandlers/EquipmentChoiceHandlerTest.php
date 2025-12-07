<?php

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterEquipment;
use App\Models\EntityItem;
use App\Models\EquipmentChoiceItem;
use App\Services\ChoiceHandlers\EquipmentChoiceHandler;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->handler = new EquipmentChoiceHandler;
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
    // Mock level 1 character with Fighter class
    $primaryClass = Mockery::mock(CharacterClass::class);
    $primaryClass->shouldReceive('getAttribute')->with('id')->andReturn(5);
    $primaryClass->shouldReceive('getAttribute')->with('name')->andReturn('Fighter');
    $primaryClass->shouldReceive('getAttribute')->with('full_slug')->andReturn('phb:fighter');
    $primaryClass->shouldReceive('__get')->with('id')->andReturn(5);
    $primaryClass->shouldReceive('__get')->with('name')->andReturn('Fighter');
    $primaryClass->shouldReceive('__get')->with('full_slug')->andReturn('phb:fighter');

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

    // Mock equipment relationship on the class
    // Items need contents collection for pack content support
    $emptyContents = new EloquentCollection([]);

    // Use stdClass with dynamic properties for items (simpler than Mockery for property access)
    $item1 = new \stdClass;
    $item1->id = 123;
    $item1->name = 'Chain Mail';
    $item1->slug = 'chain-mail';
    $item1->full_slug = 'phb:chain-mail';
    $item1->contents = $emptyContents;

    $item2 = new \stdClass;
    $item2->id = 456;
    $item2->name = 'Leather Armor';
    $item2->slug = 'leather-armor';
    $item2->full_slug = 'phb:leather-armor';
    $item2->contents = $emptyContents;

    $choice1Item1 = Mockery::mock(EquipmentChoiceItem::class);
    $choice1Item1->shouldReceive('getAttribute')->with('item_id')->andReturn(123);
    $choice1Item1->shouldReceive('getAttribute')->with('item')->andReturn($item1);
    $choice1Item1->shouldReceive('getAttribute')->with('quantity')->andReturn(1);
    $choice1Item1->shouldReceive('getAttribute')->with('proficiency_type_id')->andReturn(null);
    $choice1Item1->shouldReceive('getAttribute')->with('proficiencyType')->andReturn(null);
    $choice1Item1->shouldReceive('__get')->with('item_id')->andReturn(123);
    $choice1Item1->shouldReceive('__get')->with('item')->andReturn($item1);
    $choice1Item1->shouldReceive('__get')->with('quantity')->andReturn(1);
    $choice1Item1->shouldReceive('__get')->with('proficiency_type_id')->andReturn(null);
    $choice1Item1->shouldReceive('__get')->with('proficiencyType')->andReturn(null);

    $choice1Item2 = Mockery::mock(EquipmentChoiceItem::class);
    $choice1Item2->shouldReceive('getAttribute')->with('item_id')->andReturn(456);
    $choice1Item2->shouldReceive('getAttribute')->with('item')->andReturn($item2);
    $choice1Item2->shouldReceive('getAttribute')->with('quantity')->andReturn(1);
    $choice1Item2->shouldReceive('getAttribute')->with('proficiency_type_id')->andReturn(null);
    $choice1Item2->shouldReceive('getAttribute')->with('proficiencyType')->andReturn(null);
    $choice1Item2->shouldReceive('__get')->with('item_id')->andReturn(456);
    $choice1Item2->shouldReceive('__get')->with('item')->andReturn($item2);
    $choice1Item2->shouldReceive('__get')->with('quantity')->andReturn(1);
    $choice1Item2->shouldReceive('__get')->with('proficiency_type_id')->andReturn(null);
    $choice1Item2->shouldReceive('__get')->with('proficiencyType')->andReturn(null);

    $entityItem1 = Mockery::mock(EntityItem::class);
    $entityItem1->shouldReceive('getAttribute')->with('is_choice')->andReturn(true);
    $entityItem1->shouldReceive('getAttribute')->with('choice_group')->andReturn('equipment_choice_1');
    $entityItem1->shouldReceive('getAttribute')->with('choice_option')->andReturn(1);
    $entityItem1->shouldReceive('getAttribute')->with('quantity')->andReturn(1);
    $entityItem1->shouldReceive('getAttribute')->with('description')->andReturn('chain mail');
    $entityItem1->shouldReceive('getAttribute')->with('choiceItems')->andReturn(new EloquentCollection([$choice1Item1]));
    $entityItem1->shouldReceive('__get')->with('is_choice')->andReturn(true);
    $entityItem1->shouldReceive('__get')->with('choice_group')->andReturn('equipment_choice_1');
    $entityItem1->shouldReceive('__get')->with('choice_option')->andReturn(1);
    $entityItem1->shouldReceive('__get')->with('quantity')->andReturn(1);
    $entityItem1->shouldReceive('__get')->with('description')->andReturn('chain mail');
    $entityItem1->shouldReceive('__get')->with('choiceItems')->andReturn(new EloquentCollection([$choice1Item1]));
    $entityItem1->shouldReceive('offsetExists')->andReturn(true);
    $entityItem1->shouldReceive('offsetGet')->with('choice_group')->andReturn('equipment_choice_1');
    $entityItem1->shouldReceive('offsetGet')->with('choice_option')->andReturn(1);
    $entityItem1->shouldReceive('offsetGet')->with('quantity')->andReturn(1);
    $entityItem1->shouldReceive('offsetGet')->with('description')->andReturn('chain mail');
    $entityItem1->shouldReceive('offsetGet')->with('choiceItems')->andReturn(new EloquentCollection([$choice1Item1]));

    $entityItem2 = Mockery::mock(EntityItem::class);
    $entityItem2->shouldReceive('getAttribute')->with('is_choice')->andReturn(true);
    $entityItem2->shouldReceive('getAttribute')->with('choice_group')->andReturn('equipment_choice_1');
    $entityItem2->shouldReceive('getAttribute')->with('choice_option')->andReturn(2);
    $entityItem2->shouldReceive('getAttribute')->with('quantity')->andReturn(1);
    $entityItem2->shouldReceive('getAttribute')->with('description')->andReturn('leather armor, longbow, and arrows (20)');
    $entityItem2->shouldReceive('getAttribute')->with('choiceItems')->andReturn(new EloquentCollection([$choice1Item2]));
    $entityItem2->shouldReceive('__get')->with('is_choice')->andReturn(true);
    $entityItem2->shouldReceive('__get')->with('choice_group')->andReturn('equipment_choice_1');
    $entityItem2->shouldReceive('__get')->with('choice_option')->andReturn(2);
    $entityItem2->shouldReceive('__get')->with('quantity')->andReturn(1);
    $entityItem2->shouldReceive('__get')->with('description')->andReturn('leather armor, longbow, and arrows (20)');
    $entityItem2->shouldReceive('__get')->with('choiceItems')->andReturn(new EloquentCollection([$choice1Item2]));
    $entityItem2->shouldReceive('offsetExists')->andReturn(true);
    $entityItem2->shouldReceive('offsetGet')->with('choice_group')->andReturn('equipment_choice_1');
    $entityItem2->shouldReceive('offsetGet')->with('choice_option')->andReturn(2);
    $entityItem2->shouldReceive('offsetGet')->with('quantity')->andReturn(1);
    $entityItem2->shouldReceive('offsetGet')->with('description')->andReturn('leather armor, longbow, and arrows (20)');
    $entityItem2->shouldReceive('offsetGet')->with('choiceItems')->andReturn(new EloquentCollection([$choice1Item2]));

    $equipment = new EloquentCollection([$entityItem1, $entityItem2]);

    $equipmentRelation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
    $equipmentRelation->shouldReceive('with')->with([
        'choiceItems.item.contents.item',
        'choiceItems.proficiencyType',
    ])->andReturnSelf();
    $equipmentRelation->shouldReceive('where')->with('is_choice', true)->andReturnSelf();
    $equipmentRelation->shouldReceive('orderBy')->with('choice_group')->andReturnSelf();
    $equipmentRelation->shouldReceive('orderBy')->with('choice_option')->andReturnSelf();
    $equipmentRelation->shouldReceive('get')->andReturn($equipment);

    $primaryClass->shouldReceive('equipment')->andReturn($equipmentRelation);

    // Mock character equipment relationship for getExistingSelections
    $characterEquipmentQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $characterEquipmentQuery->shouldReceive('whereJsonContains')->andReturnSelf();
    $characterEquipmentQuery->shouldReceive('whereNotNull')->andReturnSelf();
    $characterEquipmentQuery->shouldReceive('get')->andReturn(new EloquentCollection([]));

    $this->character->shouldReceive('equipment')->andReturn($characterEquipmentQuery);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->toBeInstanceOf(PendingChoice::class)
        ->first()->type->toBe('equipment')
        ->first()->subtype->toBeNull()
        ->first()->source->toBe('class')
        ->first()->sourceName->toBe('Fighter')
        ->first()->levelGranted->toBe(1)
        ->first()->quantity->toBe(1)
        ->first()->remaining->toBe(1)
        ->first()->options->toBeArray()
        ->first()->options->toHaveCount(2);

    $firstChoice = $choices->first();
    expect($firstChoice->options[0])
        ->toHaveKey('option', 'a')
        ->toHaveKey('label', 'chain mail')
        ->toHaveKey('items')
        ->and($firstChoice->options[0]['items'])->toHaveCount(1)
        ->and($firstChoice->options[0]['items'][0])->toMatchArray([
            'id' => 123,
            'name' => 'Chain Mail',
            'slug' => 'chain-mail',
            'full_slug' => 'phb:chain-mail',
            'quantity' => 1,
        ]);

    expect($firstChoice->options[1])
        ->toHaveKey('option', 'b')
        ->toHaveKey('label', 'leather armor, longbow, and arrows (20)')
        ->toHaveKey('items')
        ->and($firstChoice->options[1]['items'])->toHaveCount(1)
        ->and($firstChoice->options[1]['items'][0])->toHaveKey('full_slug', 'phb:leather-armor');
});

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

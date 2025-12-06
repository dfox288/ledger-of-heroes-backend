<?php

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\FeatureSelection;
use App\Services\ChoiceHandlers\OptionalFeatureChoiceHandler;

beforeEach(function () {
    $this->handler = new OptionalFeatureChoiceHandler;
    $this->character = Mockery::mock(Character::class);
});

afterEach(function () {
    Mockery::close();
});

it('returns correct type', function () {
    expect($this->handler->getType())->toBe('optional_feature');
});

it('generates choices for Warlock level 2 invocations', function () {
    // Mock character class
    $warlockClass = (object) ['id' => 101, 'name' => 'Warlock', 'slug' => 'warlock', 'level' => 2];
    $characterClass = (object) [
        'class_id' => 101,
        'level' => 2,
        'characterClass' => $warlockClass,
        'subclass' => null,
    ];

    // Mock character classes relationship
    $characterClassesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $characterClassesQuery->shouldReceive('with')
        ->with(['characterClass.counters', 'subclass.counters'])
        ->andReturnSelf();
    $characterClassesQuery->shouldReceive('get')
        ->andReturn(collect([$characterClass]));

    $this->character->shouldReceive('characterClasses')
        ->andReturn($characterClassesQuery);

    // Mock class with counters
    $counter = (object) [
        'counter_name' => 'Eldritch Invocations Known',
        'level' => 2,
        'counter_value' => 2,
    ];
    $warlockClass->counters = collect([$counter]);

    // Mock feature selections
    $featureSelectionsQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $featureSelectionsQuery->shouldReceive('with')
        ->with('optionalFeature')
        ->andReturnSelf();
    $featureSelectionsQuery->shouldReceive('get')
        ->andReturn(collect([]));

    $this->character->shouldReceive('featureSelections')
        ->andReturn($featureSelectionsQuery);

    $this->character->shouldReceive('__get')
        ->with('total_level')
        ->andReturn(2);
    $this->character->shouldReceive('getAttribute')
        ->with('total_level')
        ->andReturn(2);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->toBeInstanceOf(PendingChoice::class)
        ->first()->type->toBe('optional_feature')
        ->first()->subtype->toBe('eldritch_invocation')
        ->first()->source->toBe('class')
        ->first()->sourceName->toBe('Warlock')
        ->first()->quantity->toBe(2)
        ->first()->remaining->toBe(2)
        ->first()->selected->toBe([])
        ->first()->optionsEndpoint->toContain('/api/v1/optional-features?feature_type=eldritch_invocation');
});

it('generates choices for Sorcerer level 3 metamagic', function () {
    // Mock character class
    $sorcererClass = (object) ['id' => 99, 'name' => 'Sorcerer', 'slug' => 'sorcerer', 'level' => 3];
    $characterClass = (object) [
        'class_id' => 99,
        'level' => 3,
        'characterClass' => $sorcererClass,
        'subclass' => null,
    ];

    // Mock character classes relationship
    $characterClassesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $characterClassesQuery->shouldReceive('with')
        ->with(['characterClass.counters', 'subclass.counters'])
        ->andReturnSelf();
    $characterClassesQuery->shouldReceive('get')
        ->andReturn(collect([$characterClass]));

    $this->character->shouldReceive('characterClasses')
        ->andReturn($characterClassesQuery);

    // Mock class with counters
    $counter = (object) [
        'counter_name' => 'Metamagic Known',
        'level' => 3,
        'counter_value' => 2,
    ];
    $sorcererClass->counters = collect([$counter]);

    // Mock feature selections
    $featureSelectionsQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $featureSelectionsQuery->shouldReceive('with')
        ->with('optionalFeature')
        ->andReturnSelf();
    $featureSelectionsQuery->shouldReceive('get')
        ->andReturn(collect([]));

    $this->character->shouldReceive('featureSelections')
        ->andReturn($featureSelectionsQuery);

    $this->character->shouldReceive('__get')
        ->with('total_level')
        ->andReturn(3);
    $this->character->shouldReceive('getAttribute')
        ->with('total_level')
        ->andReturn(3);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->type->toBe('optional_feature')
        ->first()->subtype->toBe('metamagic')
        ->first()->sourceName->toBe('Sorcerer')
        ->first()->quantity->toBe(2)
        ->first()->remaining->toBe(2)
        ->first()->optionsEndpoint->toContain('/api/v1/optional-features?feature_type=metamagic');
});

it('generates choices for Battle Master level 3 maneuvers', function () {
    // Mock character class with subclass
    $fighterClass = (object) ['id' => 88, 'name' => 'Fighter', 'slug' => 'fighter', 'level' => 3];
    $subclass = (object) ['name' => 'Battle Master', 'counters' => collect([])];
    $characterClass = (object) [
        'class_id' => 88,
        'level' => 3,
        'characterClass' => $fighterClass,
        'subclass' => $subclass,
    ];

    // Mock character classes relationship
    $characterClassesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $characterClassesQuery->shouldReceive('with')
        ->with(['characterClass.counters', 'subclass.counters'])
        ->andReturnSelf();
    $characterClassesQuery->shouldReceive('get')
        ->andReturn(collect([$characterClass]));

    $this->character->shouldReceive('characterClasses')
        ->andReturn($characterClassesQuery);

    // Mock class with counters
    $counter = (object) [
        'counter_name' => 'Maneuvers Known',
        'level' => 3,
        'counter_value' => 3,
    ];
    $fighterClass->counters = collect([$counter]);

    // Mock feature selections
    $featureSelectionsQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $featureSelectionsQuery->shouldReceive('with')
        ->with('optionalFeature')
        ->andReturnSelf();
    $featureSelectionsQuery->shouldReceive('get')
        ->andReturn(collect([]));

    $this->character->shouldReceive('featureSelections')
        ->andReturn($featureSelectionsQuery);

    $this->character->shouldReceive('__get')
        ->with('total_level')
        ->andReturn(3);
    $this->character->shouldReceive('getAttribute')
        ->with('total_level')
        ->andReturn(3);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->type->toBe('optional_feature')
        ->first()->subtype->toBe('maneuver')
        ->first()->sourceName->toBe('Fighter')
        ->first()->quantity->toBe(3)
        ->first()->remaining->toBe(3)
        ->first()->optionsEndpoint->toContain('/api/v1/optional-features?feature_type=maneuver');
});

it('calculates remaining choices when some features are selected', function () {
    // Mock character class
    $warlockClass = (object) ['id' => 101, 'name' => 'Warlock', 'slug' => 'warlock', 'level' => 5];
    $characterClass = (object) [
        'class_id' => 101,
        'level' => 5,
        'characterClass' => $warlockClass,
        'subclass' => null,
    ];

    // Mock character classes relationship
    $characterClassesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $characterClassesQuery->shouldReceive('with')
        ->with(['characterClass.counters', 'subclass.counters'])
        ->andReturnSelf();
    $characterClassesQuery->shouldReceive('get')
        ->andReturn(collect([$characterClass]));

    $this->character->shouldReceive('characterClasses')
        ->andReturn($characterClassesQuery);

    // Mock class with counters (level 5 gets 3 invocations)
    $counter = (object) [
        'counter_name' => 'Eldritch Invocations Known',
        'level' => 5,
        'counter_value' => 3,
    ];
    $warlockClass->counters = collect([$counter]);

    // Mock feature selections (2 already selected)
    $invocation1 = (object) ['feature_type' => \App\Enums\OptionalFeatureType::ELDRITCH_INVOCATION];
    $invocation2 = (object) ['feature_type' => \App\Enums\OptionalFeatureType::ELDRITCH_INVOCATION];
    $selection1 = (object) ['optionalFeature' => $invocation1];
    $selection2 = (object) ['optionalFeature' => $invocation2];

    $featureSelectionsQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $featureSelectionsQuery->shouldReceive('with')
        ->with('optionalFeature')
        ->andReturnSelf();
    $featureSelectionsQuery->shouldReceive('get')
        ->andReturn(collect([$selection1, $selection2]));

    $this->character->shouldReceive('featureSelections')
        ->andReturn($featureSelectionsQuery);

    $this->character->shouldReceive('__get')
        ->with('total_level')
        ->andReturn(5);
    $this->character->shouldReceive('getAttribute')
        ->with('total_level')
        ->andReturn(5);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->quantity->toBe(3)
        ->first()->remaining->toBe(1);
});

it('resolves choice by creating FeatureSelection record', function () {
    $choice = new PendingChoice(
        id: 'optional_feature:class:101:2:eldritch_invocation_1',
        type: 'optional_feature',
        subtype: 'eldritch_invocation',
        source: 'class',
        sourceName: 'Warlock',
        levelGranted: 2,
        required: true,
        quantity: 2,
        remaining: 2,
        selected: [],
        options: [],
        optionsEndpoint: '/api/v1/optional-features?feature_type=eldritch_invocation',
        metadata: ['class_id' => 101]
    );

    // Mock character
    $this->character->shouldReceive('__get')
        ->with('total_level')
        ->andReturn(2);
    $this->character->shouldReceive('getAttribute')
        ->with('total_level')
        ->andReturn(2);
    $this->character->shouldReceive('__get')
        ->with('id')
        ->andReturn(1);
    $this->character->shouldReceive('getAttribute')
        ->with('id')
        ->andReturn(1);

    // Mock FeatureSelection relationship
    $featureSelectionsQuery = Mockery::mock();
    $featureSelectionsQuery->shouldReceive('create')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['optional_feature_id'] === 42
                && $data['class_id'] === 101
                && $data['level_acquired'] === 2;
        }))
        ->andReturn((object) ['id' => 999]);

    $this->character->shouldReceive('featureSelections')
        ->andReturn($featureSelectionsQuery);

    $this->handler->resolve($this->character, $choice, ['optional_feature_id' => 42]);
})->skip('Requires database for OptionalFeature validation');

it('throws exception when optional_feature_id is missing', function () {
    $choice = new PendingChoice(
        id: 'optional_feature:class:101:2:eldritch_invocation_1',
        type: 'optional_feature',
        subtype: 'eldritch_invocation',
        source: 'class',
        sourceName: 'Warlock',
        levelGranted: 2,
        required: true,
        quantity: 2,
        remaining: 2,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: ['class_id' => 101]
    );

    expect(fn () => $this->handler->resolve($this->character, $choice, []))
        ->toThrow(InvalidSelectionException::class);
});

it('returns true for canUndo', function () {
    $choice = new PendingChoice(
        id: 'optional_feature:class:101:2:eldritch_invocation_1',
        type: 'optional_feature',
        subtype: 'eldritch_invocation',
        source: 'class',
        sourceName: 'Warlock',
        levelGranted: 2,
        required: true,
        quantity: 2,
        remaining: 0,
        selected: [1, 2],
        options: [],
        optionsEndpoint: null,
        metadata: ['class_id' => 101]
    );

    expect($this->handler->canUndo($this->character, $choice))->toBeTrue();
});

it('undoes choice by deleting FeatureSelection record', function () {
    $choice = new PendingChoice(
        id: 'optional_feature:class:101:2:eldritch_invocation_1',
        type: 'optional_feature',
        subtype: 'eldritch_invocation',
        source: 'class',
        sourceName: 'Warlock',
        levelGranted: 2,
        required: true,
        quantity: 2,
        remaining: 0,
        selected: [1],
        options: [],
        optionsEndpoint: null,
        metadata: ['optional_feature_id' => 42]
    );

    // Mock featureSelections relationship
    $featureSelectionsQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $featureSelectionsQuery->shouldReceive('where')
        ->with('optional_feature_id', 42)
        ->andReturnSelf();
    $featureSelectionsQuery->shouldReceive('delete')
        ->once()
        ->andReturn(1);

    $this->character->shouldReceive('featureSelections')
        ->once()
        ->andReturn($featureSelectionsQuery);

    $this->character->shouldReceive('load')
        ->once()
        ->with('featureSelections')
        ->andReturnSelf();

    $this->handler->undo($this->character, $choice);
});

it('returns empty collection when character has no classes', function () {
    // Mock character classes relationship
    $characterClassesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $characterClassesQuery->shouldReceive('with')
        ->with(['characterClass.counters', 'subclass.counters'])
        ->andReturnSelf();
    $characterClassesQuery->shouldReceive('get')
        ->andReturn(collect([]));

    $this->character->shouldReceive('characterClasses')
        ->andReturn($characterClassesQuery);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(0);
});

it('skips counter when level requirement not met', function () {
    // Mock character class at level 1
    $warlockClass = (object) ['id' => 101, 'name' => 'Warlock', 'slug' => 'warlock', 'level' => 1];
    $characterClass = (object) [
        'class_id' => 101,
        'level' => 1,
        'characterClass' => $warlockClass,
        'subclass' => null,
    ];

    // Mock character classes relationship
    $characterClassesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $characterClassesQuery->shouldReceive('with')
        ->with(['characterClass.counters', 'subclass.counters'])
        ->andReturnSelf();
    $characterClassesQuery->shouldReceive('get')
        ->andReturn(collect([$characterClass]));

    $this->character->shouldReceive('characterClasses')
        ->andReturn($characterClassesQuery);

    // Mock class with counter at level 2 (character is level 1)
    $counter = (object) [
        'counter_name' => 'Eldritch Invocations Known',
        'level' => 2,
        'counter_value' => 2,
    ];
    $warlockClass->counters = collect([$counter]);

    $choices = $this->handler->getChoices($this->character);

    // Should not generate choices since character level (1) < counter level (2)
    expect($choices)->toHaveCount(0);
});

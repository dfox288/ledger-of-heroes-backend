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

// Skipped: This test checked optionsEndpoint which is now null (#622 fix).
// Inline options behavior is tested in OptionalFeatureInlineOptionsTest.php
it('generates choices for Warlock level 2 invocations', function () {})->skip('Replaced by integration tests in OptionalFeatureInlineOptionsTest.php (#622)');

// Skipped: This test checked optionsEndpoint which is now null (#622 fix).
it('generates choices for Sorcerer level 3 metamagic', function () {})->skip('Replaced by integration tests in OptionalFeatureInlineOptionsTest.php (#622)');

// Skipped: This test checked optionsEndpoint which is now null (#622 fix).
it('generates choices for Battle Master level 3 maneuvers', function () {})->skip('Replaced by integration tests in OptionalFeatureInlineOptionsTest.php (#622)');

// Skipped: This test calls getChoices() which now queries the database via buildInlineOptions().
// Remaining calculation is tested in OptionalFeatureInlineOptionsTest.php
it('calculates remaining choices when some features are selected', function () {})->skip('Replaced by integration tests in OptionalFeatureInlineOptionsTest.php (#622)');

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
        metadata: ['optional_feature_slug' => 'phb2014:agonizing-blast']
    );

    // Mock featureSelections relationship
    $featureSelectionsQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $featureSelectionsQuery->shouldReceive('where')
        ->with('optional_feature_slug', 'phb2014:agonizing-blast')
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
    $warlockClass = (object) ['id' => 101, 'name' => 'Warlock', 'slug' => 'phb2014:warlock', 'level' => 1];
    $characterClass = (object) [
        'class_id' => 101,
        'class_slug' => 'phb2014:warlock',
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

<?php

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterProficiency;
use App\Services\ChoiceHandlers\ExpertiseChoiceHandler;

beforeEach(function () {
    $this->handler = new ExpertiseChoiceHandler;
    $this->character = Mockery::mock(Character::class);
});

afterEach(function () {
    Mockery::close();
});

it('returns correct type', function () {
    expect($this->handler->getType())->toBe('expertise');
});

it('returns no choices when character has no classes', function () {
    // Mock character with no classes
    $characterClasses = collect();

    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(0);
});

it('returns expertise choices for Rogue level 1', function () {
    // Mock Rogue level 1 with full_slug
    $class = (object) ['id' => 5, 'slug' => 'rogue', 'full_slug' => 'phb:rogue', 'name' => 'Rogue'];
    $characterClass = (object) ['level' => 1, 'characterClass' => $class, 'is_primary' => true];
    $characterClasses = collect([$characterClass]);

    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);

    // Mock proficiencies without expertise - using skill_slug instead of skill_id
    $proficiency1 = (object) ['id' => 1, 'skill_slug' => 'phb:acrobatics', 'proficiency_type_slug' => null, 'expertise' => false, 'skill' => (object) ['slug' => 'acrobatics', 'name' => 'Acrobatics']];
    $proficiency2 = (object) ['id' => 2, 'skill_slug' => 'phb:stealth', 'proficiency_type_slug' => null, 'expertise' => false, 'skill' => (object) ['slug' => 'stealth', 'name' => 'Stealth']];
    $proficiency3 = (object) ['id' => 3, 'skill_slug' => 'phb:perception', 'proficiency_type_slug' => null, 'expertise' => false, 'skill' => (object) ['slug' => 'perception', 'name' => 'Perception']];
    $proficiency4 = (object) ['id' => 4, 'skill_slug' => null, 'proficiency_type_slug' => 'phb:thieves-tools', 'expertise' => false, 'proficiencyType' => (object) ['slug' => 'thieves-tools', 'name' => "Thieves' Tools"]];

    $proficiencies = collect([$proficiency1, $proficiency2, $proficiency3, $proficiency4]);

    // Mock existing expertise selections (source = class, choice_group = expertise_1)
    $expertiseProficienciesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $expertiseProficienciesQuery->shouldReceive('where')
        ->with('source', 'class')
        ->andReturnSelf();
    $expertiseProficienciesQuery->shouldReceive('where')
        ->with('choice_group', 'expertise_1')
        ->andReturnSelf();
    $expertiseProficienciesQuery->shouldReceive('get')
        ->andReturn(collect()); // No selections yet

    // Mock proficiencies query for getting all proficiencies
    $proficienciesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $proficienciesQuery->shouldReceive('get')
        ->andReturn($proficiencies);

    // Set up proficiencies() to return different query builders
    $this->character->shouldReceive('proficiencies')
        ->andReturn($expertiseProficienciesQuery, $proficienciesQuery);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->toBeInstanceOf(PendingChoice::class)
        ->first()->type->toBe('expertise')
        ->first()->subtype->toBeNull()
        ->first()->source->toBe('class')
        ->first()->sourceName->toBe('Rogue')
        ->first()->levelGranted->toBe(1)
        ->first()->quantity->toBe(2)
        ->first()->remaining->toBe(2)
        ->first()->selected->toBe([])
        ->first()->options->toHaveCount(4); // 3 skills + thieves' tools
});

it('returns expertise choices for Rogue level 6 (second set)', function () {
    // Mock Rogue level 6
    $class = (object) ['id' => 5, 'slug' => 'rogue', 'full_slug' => 'phb:rogue', 'name' => 'Rogue'];
    $characterClass = (object) ['level' => 6, 'characterClass' => $class, 'is_primary' => true];
    $characterClasses = collect([$characterClass]);

    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);

    // Mock proficiencies: 2 with expertise, 2 without
    $proficiency1 = (object) ['id' => 1, 'skill_slug' => 'phb:acrobatics', 'proficiency_type_slug' => null, 'expertise' => true, 'skill' => (object) ['slug' => 'acrobatics', 'name' => 'Acrobatics']];
    $proficiency2 = (object) ['id' => 2, 'skill_slug' => 'phb:stealth', 'proficiency_type_slug' => null, 'expertise' => true, 'skill' => (object) ['slug' => 'stealth', 'name' => 'Stealth']];
    $proficiency3 = (object) ['id' => 3, 'skill_slug' => 'phb:perception', 'proficiency_type_slug' => null, 'expertise' => false, 'skill' => (object) ['slug' => 'perception', 'name' => 'Perception']];
    $proficiency4 = (object) ['id' => 4, 'skill_slug' => 'phb:investigation', 'proficiency_type_slug' => null, 'expertise' => false, 'skill' => (object) ['slug' => 'investigation', 'name' => 'Investigation']];

    $proficiencies = collect([$proficiency1, $proficiency2, $proficiency3, $proficiency4]);

    // Mock expertise_1 selections (level 1 choices)
    $expertise1Query = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $expertise1Query->shouldReceive('where')
        ->with('source', 'class')
        ->andReturnSelf();
    $expertise1Query->shouldReceive('where')
        ->with('choice_group', 'expertise_1')
        ->andReturnSelf();
    $expertise1Query->shouldReceive('get')
        ->andReturn(collect([$proficiency1, $proficiency2])); // 2 selected at level 1

    // Mock expertise_6 selections (level 6 choices)
    $expertise6Query = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $expertise6Query->shouldReceive('where')
        ->with('source', 'class')
        ->andReturnSelf();
    $expertise6Query->shouldReceive('where')
        ->with('choice_group', 'expertise_6')
        ->andReturnSelf();
    $expertise6Query->shouldReceive('get')
        ->andReturn(collect()); // No selections yet at level 6

    // Mock proficiencies query for getting all proficiencies
    $proficienciesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $proficienciesQuery->shouldReceive('get')
        ->andReturn($proficiencies);

    // Set up proficiencies() to return different query builders
    $this->character->shouldReceive('proficiencies')
        ->andReturn($expertise1Query, $expertise6Query, $proficienciesQuery);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1) // Only level 6 choice pending
        ->first()->levelGranted->toBe(6)
        ->first()->quantity->toBe(2)
        ->first()->remaining->toBe(2)
        ->first()->options->toHaveCount(2); // Only 2 skills without expertise
});

it('returns expertise choices for Bard level 3', function () {
    // Mock Bard level 3
    $class = (object) ['id' => 1, 'slug' => 'bard', 'full_slug' => 'phb:bard', 'name' => 'Bard'];
    $characterClass = (object) ['level' => 3, 'characterClass' => $class, 'is_primary' => true];
    $characterClasses = collect([$characterClass]);

    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);

    // Mock proficiencies: 3 skills, 1 tool (Bards should only get skills as options)
    $proficiency1 = (object) ['id' => 1, 'skill_slug' => 'phb:acrobatics', 'proficiency_type_slug' => null, 'expertise' => false, 'skill' => (object) ['slug' => 'acrobatics', 'name' => 'Acrobatics']];
    $proficiency2 = (object) ['id' => 2, 'skill_slug' => 'phb:performance', 'proficiency_type_slug' => null, 'expertise' => false, 'skill' => (object) ['slug' => 'performance', 'name' => 'Performance']];
    $proficiency3 = (object) ['id' => 3, 'skill_slug' => 'phb:persuasion', 'proficiency_type_slug' => null, 'expertise' => false, 'skill' => (object) ['slug' => 'persuasion', 'name' => 'Persuasion']];
    $proficiency4 = (object) ['id' => 4, 'skill_slug' => null, 'proficiency_type_slug' => 'phb:lute', 'expertise' => false, 'proficiencyType' => (object) ['slug' => 'lute', 'name' => 'Lute']];

    $proficiencies = collect([$proficiency1, $proficiency2, $proficiency3, $proficiency4]);

    // Mock existing expertise selections
    $expertiseProficienciesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $expertiseProficienciesQuery->shouldReceive('where')
        ->with('source', 'class')
        ->andReturnSelf();
    $expertiseProficienciesQuery->shouldReceive('where')
        ->with('choice_group', 'expertise_3')
        ->andReturnSelf();
    $expertiseProficienciesQuery->shouldReceive('get')
        ->andReturn(collect()); // No selections yet

    // Mock proficiencies query for getting all proficiencies
    $proficienciesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $proficienciesQuery->shouldReceive('get')
        ->andReturn($proficiencies);

    // Set up proficiencies() to return different query builders
    $this->character->shouldReceive('proficiencies')
        ->andReturn($expertiseProficienciesQuery, $proficienciesQuery);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->type->toBe('expertise')
        ->first()->source->toBe('class')
        ->first()->sourceName->toBe('Bard')
        ->first()->levelGranted->toBe(3)
        ->first()->quantity->toBe(2)
        ->first()->options->toHaveCount(3); // Only 3 skills, NOT the tool
});

it('returns no choices when all expertise selections are complete', function () {
    // Mock Rogue level 6 with all expertise choices made
    $class = (object) ['id' => 5, 'slug' => 'rogue', 'full_slug' => 'phb:rogue', 'name' => 'Rogue'];
    $characterClass = (object) ['level' => 6, 'characterClass' => $class, 'is_primary' => true];
    $characterClasses = collect([$characterClass]);

    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);

    // Mock proficiencies with expertise
    $proficiency1 = (object) ['id' => 1, 'skill_slug' => 'phb:acrobatics', 'proficiency_type_slug' => null, 'expertise' => true];
    $proficiency2 = (object) ['id' => 2, 'skill_slug' => 'phb:stealth', 'proficiency_type_slug' => null, 'expertise' => true];
    $proficiency3 = (object) ['id' => 3, 'skill_slug' => 'phb:perception', 'proficiency_type_slug' => null, 'expertise' => true];
    $proficiency4 = (object) ['id' => 4, 'skill_slug' => 'phb:investigation', 'proficiency_type_slug' => null, 'expertise' => true];

    // Mock both expertise choices complete
    $expertise1Query = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $expertise1Query->shouldReceive('where')
        ->with('source', 'class')
        ->andReturnSelf();
    $expertise1Query->shouldReceive('where')
        ->with('choice_group', 'expertise_1')
        ->andReturnSelf();
    $expertise1Query->shouldReceive('get')
        ->andReturn(collect([$proficiency1, $proficiency2])); // 2 selected

    $expertise6Query = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $expertise6Query->shouldReceive('where')
        ->with('source', 'class')
        ->andReturnSelf();
    $expertise6Query->shouldReceive('where')
        ->with('choice_group', 'expertise_6')
        ->andReturnSelf();
    $expertise6Query->shouldReceive('get')
        ->andReturn(collect([$proficiency3, $proficiency4])); // 2 selected

    // Set up proficiencies() to return different query builders
    $this->character->shouldReceive('proficiencies')
        ->andReturn($expertise1Query, $expertise6Query);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(0);
});

it('resolves expertise choice by updating expertise flag on proficiencies', function () {
    $choice = new PendingChoice(
        id: 'expertise|class|phb:rogue|1|expertise_1',
        type: 'expertise',
        subtype: null,
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 2,
        remaining: 2,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'expertise_1']
    );

    // Mock proficiencies query to find the proficiencies to update by slug
    $proficiency1 = Mockery::mock(CharacterProficiency::class);
    $proficiency1->shouldReceive('getAttribute')->with('skill_slug')->andReturn('phb:acrobatics');
    $proficiency1->shouldReceive('__get')->with('skill_slug')->andReturn('phb:acrobatics');
    $proficiency1->shouldReceive('update')
        ->once()
        ->with(['expertise' => true, 'source' => 'class', 'choice_group' => 'expertise_1'])
        ->andReturn(true);

    $proficiency2 = Mockery::mock(CharacterProficiency::class);
    $proficiency2->shouldReceive('getAttribute')->with('skill_slug')->andReturn('phb:stealth');
    $proficiency2->shouldReceive('__get')->with('skill_slug')->andReturn('phb:stealth');
    $proficiency2->shouldReceive('update')
        ->once()
        ->with(['expertise' => true, 'source' => 'class', 'choice_group' => 'expertise_1'])
        ->andReturn(true);

    $proficienciesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $proficienciesQuery->shouldReceive('where')
        ->andReturnSelf();
    $proficienciesQuery->shouldReceive('get')
        ->andReturn(collect([$proficiency1, $proficiency2]));

    $this->character->shouldReceive('proficiencies')
        ->andReturn($proficienciesQuery);

    $this->character->shouldReceive('load')
        ->once()
        ->with('proficiencies')
        ->andReturnSelf();

    $this->handler->resolve($this->character, $choice, ['selected' => ['phb:acrobatics', 'phb:stealth']]);
});

it('throws exception when selection is empty', function () {
    $choice = new PendingChoice(
        id: 'expertise|class|phb:rogue|1|expertise_1',
        type: 'expertise',
        subtype: null,
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 2,
        remaining: 2,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'expertise_1']
    );

    expect(fn () => $this->handler->resolve($this->character, $choice, ['selected' => []]))
        ->toThrow(InvalidSelectionException::class);
});

it('throws exception when selected proficiency does not exist', function () {
    $choice = new PendingChoice(
        id: 'expertise|class|phb:rogue|1|expertise_1',
        type: 'expertise',
        subtype: null,
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 2,
        remaining: 2,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'expertise_1']
    );

    // Mock proficiencies query returning fewer items than selected
    $proficienciesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $proficienciesQuery->shouldReceive('where')
        ->andReturnSelf();
    $proficienciesQuery->shouldReceive('get')
        ->andReturn(collect([])); // No proficiencies found

    $this->character->shouldReceive('proficiencies')
        ->andReturn($proficienciesQuery);

    expect(fn () => $this->handler->resolve($this->character, $choice, ['selected' => ['phb:acrobatics', 'phb:stealth']]))
        ->toThrow(InvalidSelectionException::class);
});

it('returns true for canUndo', function () {
    $choice = new PendingChoice(
        id: 'expertise|class|phb:rogue|1|expertise_1',
        type: 'expertise',
        subtype: null,
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 2,
        remaining: 0,
        selected: ['phb:acrobatics', 'phb:stealth'],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'expertise_1']
    );

    expect($this->handler->canUndo($this->character, $choice))->toBeTrue();
});

it('undoes choice by clearing expertise flag', function () {
    $choice = new PendingChoice(
        id: 'expertise|class|phb:rogue|1|expertise_1',
        type: 'expertise',
        subtype: null,
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 2,
        remaining: 0,
        selected: ['phb:acrobatics', 'phb:stealth'],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'expertise_1']
    );

    // Mock proficiencies relationship
    $proficienciesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $proficienciesQuery->shouldReceive('where')
        ->with('source', 'class')
        ->andReturnSelf();
    $proficienciesQuery->shouldReceive('where')
        ->with('choice_group', 'expertise_1')
        ->andReturnSelf();
    $proficienciesQuery->shouldReceive('update')
        ->once()
        ->with(['expertise' => false, 'source' => null, 'choice_group' => null])
        ->andReturn(2);

    $this->character->shouldReceive('proficiencies')
        ->once()
        ->andReturn($proficienciesQuery);

    $this->character->shouldReceive('load')
        ->once()
        ->with('proficiencies')
        ->andReturnSelf();

    $this->handler->undo($this->character, $choice);
});

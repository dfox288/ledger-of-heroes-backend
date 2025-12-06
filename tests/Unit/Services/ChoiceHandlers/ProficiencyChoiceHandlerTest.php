<?php

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\Skill;
use App\Services\CharacterProficiencyService;
use App\Services\ChoiceHandlers\ProficiencyChoiceHandler;

beforeEach(function () {
    $this->proficiencyService = Mockery::mock(CharacterProficiencyService::class);
    $this->handler = new ProficiencyChoiceHandler($this->proficiencyService);
    $this->character = Mockery::mock(Character::class);
});

afterEach(function () {
    Mockery::close();
});

it('returns correct type', function () {
    expect($this->handler->getType())->toBe('proficiency');
});

it('transforms service output to PendingChoice objects for skill choices', function () {
    // Mock character attributes
    $primaryClass = (object) ['id' => 5, 'name' => 'Rogue'];
    $this->character->shouldReceive('__get')
        ->with('primary_class')
        ->andReturn($primaryClass);
    $this->character->shouldReceive('getAttribute')
        ->with('primary_class')
        ->andReturn($primaryClass);
    $this->character->shouldReceive('offsetExists')->andReturn(true);

    // Mock proficiency service response for skill choice
    $this->proficiencyService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'class' => [
                'skills' => [
                    'proficiency_type' => 'skill',
                    'proficiency_subcategory' => null,
                    'quantity' => 4,
                    'remaining' => 2,
                    'selected_skills' => [1, 2],
                    'selected_proficiency_types' => [],
                    'options' => [
                        [
                            'type' => 'skill',
                            'skill_id' => 1,
                            'skill' => ['id' => 1, 'name' => 'Acrobatics', 'slug' => 'acrobatics'],
                        ],
                        [
                            'type' => 'skill',
                            'skill_id' => 2,
                            'skill' => ['id' => 2, 'name' => 'Stealth', 'slug' => 'stealth'],
                        ],
                        [
                            'type' => 'skill',
                            'skill_id' => 3,
                            'skill' => ['id' => 3, 'name' => 'Perception', 'slug' => 'perception'],
                        ],
                    ],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->toBeInstanceOf(PendingChoice::class)
        ->first()->type->toBe('proficiency')
        ->first()->subtype->toBe('skill')
        ->first()->source->toBe('class')
        ->first()->sourceName->toBe('Rogue')
        ->first()->quantity->toBe(4)
        ->first()->remaining->toBe(2)
        ->first()->selected->toBe(['1', '2'])
        ->first()->options->toHaveCount(3)
        ->first()->optionsEndpoint->toBeNull();
});

it('transforms service output to PendingChoice objects for proficiency type choices', function () {
    // Mock character attributes
    $background = (object) ['id' => 3, 'name' => 'Guild Artisan'];
    $this->character->shouldReceive('__get')
        ->with('background_id')
        ->andReturn(3);
    $this->character->shouldReceive('getAttribute')
        ->with('background_id')
        ->andReturn(3);
    $this->character->shouldReceive('__get')
        ->with('background')
        ->andReturn($background);
    $this->character->shouldReceive('getAttribute')
        ->with('background')
        ->andReturn($background);
    $this->character->shouldReceive('__get')
        ->with('primary_class')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('primary_class')
        ->andReturn(null);
    $this->character->shouldReceive('__get')
        ->with('race_id')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('race_id')
        ->andReturn(null);
    $this->character->shouldReceive('__get')
        ->with('race')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('race')
        ->andReturn(null);
    $this->character->shouldReceive('offsetExists')->andReturn(true);

    // Mock proficiency service response for proficiency type choice
    $this->proficiencyService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'background' => [
                'artisan_tools' => [
                    'proficiency_type' => 'tool',
                    'proficiency_subcategory' => 'artisan',
                    'quantity' => 1,
                    'remaining' => 1,
                    'selected_skills' => [],
                    'selected_proficiency_types' => [],
                    'options' => [
                        [
                            'type' => 'proficiency_type',
                            'proficiency_type_id' => 10,
                            'proficiency_type' => ['id' => 10, 'name' => "Smith's Tools", 'slug' => 'smiths-tools'],
                        ],
                        [
                            'type' => 'proficiency_type',
                            'proficiency_type_id' => 11,
                            'proficiency_type' => ['id' => 11, 'name' => "Carpenter's Tools", 'slug' => 'carpenters-tools'],
                        ],
                    ],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->toBeInstanceOf(PendingChoice::class)
        ->first()->type->toBe('proficiency')
        ->first()->subtype->toBe('tool')
        ->first()->source->toBe('background')
        ->first()->sourceName->toBe('Guild Artisan')
        ->first()->quantity->toBe(1)
        ->first()->remaining->toBe(1)
        ->first()->selected->toBe([])
        ->first()->options->toHaveCount(2)
        ->first()->optionsEndpoint->toBe('/api/v1/lookups/proficiency-types?category=tool&subcategory=artisan');
});

it('handles multiple choice groups from different sources', function () {
    // Mock character attributes
    $primaryClass = (object) ['id' => 5, 'name' => 'Rogue'];
    $race = (object) ['id' => 2, 'name' => 'Half-Elf'];

    $this->character->shouldReceive('__get')
        ->with('primary_class')
        ->andReturn($primaryClass);
    $this->character->shouldReceive('getAttribute')
        ->with('primary_class')
        ->andReturn($primaryClass);
    $this->character->shouldReceive('__get')
        ->with('race_id')
        ->andReturn(2);
    $this->character->shouldReceive('getAttribute')
        ->with('race_id')
        ->andReturn(2);
    $this->character->shouldReceive('__get')
        ->with('race')
        ->andReturn($race);
    $this->character->shouldReceive('getAttribute')
        ->with('race')
        ->andReturn($race);
    $this->character->shouldReceive('__get')
        ->with('background_id')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('background_id')
        ->andReturn(null);
    $this->character->shouldReceive('__get')
        ->with('background')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('background')
        ->andReturn(null);
    $this->character->shouldReceive('offsetExists')->andReturn(true);

    $this->proficiencyService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'class' => [
                'skills' => [
                    'proficiency_type' => 'skill',
                    'proficiency_subcategory' => null,
                    'quantity' => 4,
                    'remaining' => 4,
                    'selected_skills' => [],
                    'selected_proficiency_types' => [],
                    'options' => [
                        ['type' => 'skill', 'skill_id' => 1, 'skill' => ['id' => 1, 'name' => 'Acrobatics', 'slug' => 'acrobatics']],
                    ],
                ],
            ],
            'race' => [
                'skill_versatility' => [
                    'proficiency_type' => 'skill',
                    'proficiency_subcategory' => null,
                    'quantity' => 2,
                    'remaining' => 2,
                    'selected_skills' => [],
                    'selected_proficiency_types' => [],
                    'options' => [
                        ['type' => 'skill', 'skill_id' => 3, 'skill' => ['id' => 3, 'name' => 'Perception', 'slug' => 'perception']],
                    ],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(2)
        ->sequence(
            fn ($choice) => $choice->source->toBe('class')->sourceName->toBe('Rogue'),
            fn ($choice) => $choice->source->toBe('race')->sourceName->toBe('Half-Elf')
        );
});

it('resolves skill choice by calling makeSkillChoice', function () {
    $choice = new PendingChoice(
        id: 'proficiency:class:5:1:skills',
        type: 'proficiency',
        subtype: 'skill',
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 4,
        remaining: 2,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'skills']
    );

    $skillIds = [1, 2, 3, 4];

    $this->proficiencyService->shouldReceive('makeSkillChoice')
        ->once()
        ->with($this->character, 'class', 'skills', $skillIds);

    $this->handler->resolve($this->character, $choice, ['selected' => $skillIds]);
});

it('resolves proficiency type choice by calling makeProficiencyTypeChoice', function () {
    $choice = new PendingChoice(
        id: 'proficiency:background:3:1:artisan_tools',
        type: 'proficiency',
        subtype: 'tool',
        source: 'background',
        sourceName: 'Guild Artisan',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [],
        optionsEndpoint: '/api/v1/lookups/proficiency-types?category=tool&subcategory=artisan',
        metadata: ['choice_group' => 'artisan_tools', 'proficiency_subcategory' => 'artisan']
    );

    $profTypeIds = [10];

    $this->proficiencyService->shouldReceive('makeProficiencyTypeChoice')
        ->once()
        ->with($this->character, 'background', 'artisan_tools', $profTypeIds);

    $this->handler->resolve($this->character, $choice, ['selected' => $profTypeIds]);
});

it('resolves skill choice by converting slugs to IDs', function () {
    $choice = new PendingChoice(
        id: 'proficiency:class:5:1:skills',
        type: 'proficiency',
        subtype: 'skill',
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 2,
        remaining: 2,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'skills']
    );

    $this->proficiencyService->shouldReceive('makeSkillChoice')
        ->once()
        ->with($this->character, 'class', 'skills', Mockery::on(function ($arg) {
            // Accept either IDs directly or slugs that will be resolved
            return is_array($arg) && count($arg) === 2;
        }));

    $this->handler->resolve($this->character, $choice, ['selected' => [1, 2]]);
})->skip('Requires database for Skill model queries');

it('resolves proficiency type choice by converting slugs to IDs', function () {
    $choice = new PendingChoice(
        id: 'proficiency:background:3:1:artisan_tools',
        type: 'proficiency',
        subtype: 'tool',
        source: 'background',
        sourceName: 'Guild Artisan',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [],
        optionsEndpoint: '/api/v1/lookups/proficiency-types?category=tool&subcategory=artisan',
        metadata: ['choice_group' => 'artisan_tools', 'proficiency_subcategory' => 'artisan']
    );

    $this->proficiencyService->shouldReceive('makeProficiencyTypeChoice')
        ->once()
        ->with($this->character, 'background', 'artisan_tools', Mockery::on(function ($arg) {
            // Accept either IDs directly or slugs that will be resolved
            return is_array($arg) && count($arg) === 1;
        }));

    $this->handler->resolve($this->character, $choice, ['selected' => [10]]);
})->skip('Requires database for ProficiencyType model queries');

it('throws exception when selection is empty', function () {
    $choice = new PendingChoice(
        id: 'proficiency:class:5:1:skills',
        type: 'proficiency',
        subtype: 'skill',
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 4,
        remaining: 4,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'skills']
    );

    expect(fn () => $this->handler->resolve($this->character, $choice, ['selected' => []]))
        ->toThrow(InvalidSelectionException::class);
});

it('returns true for canUndo', function () {
    $choice = new PendingChoice(
        id: 'proficiency:class:5:1:skills',
        type: 'proficiency',
        subtype: 'skill',
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 4,
        remaining: 0,
        selected: [1, 2, 3, 4],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'skills']
    );

    expect($this->handler->canUndo($this->character, $choice))->toBeTrue();
});

it('undoes choice by clearing proficiencies', function () {
    $choice = new PendingChoice(
        id: 'proficiency:class:5:1:skills',
        type: 'proficiency',
        subtype: 'skill',
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 4,
        remaining: 0,
        selected: [1, 2, 3, 4],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'skills']
    );

    // Mock proficiencies relationship - use stdClass mock to avoid type hinting issues
    $proficienciesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $proficienciesQuery->shouldReceive('where')
        ->with('source', 'class')
        ->andReturnSelf();
    $proficienciesQuery->shouldReceive('where')
        ->with('choice_group', 'skills')
        ->andReturnSelf();
    $proficienciesQuery->shouldReceive('delete')
        ->once()
        ->andReturn(4);

    $this->character->shouldReceive('proficiencies')
        ->once()
        ->andReturn($proficienciesQuery);

    $this->character->shouldReceive('load')
        ->once()
        ->with('proficiencies')
        ->andReturnSelf();

    $this->handler->undo($this->character, $choice);
});

it('skips choices with invalid source ID', function () {
    // Mock character with no primary class
    $this->character->shouldReceive('__get')
        ->with('primary_class')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('primary_class')
        ->andReturn(null);
    $this->character->shouldReceive('__get')
        ->with('race_id')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('race_id')
        ->andReturn(null);
    $this->character->shouldReceive('__get')
        ->with('race')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('race')
        ->andReturn(null);
    $this->character->shouldReceive('__get')
        ->with('background_id')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('background_id')
        ->andReturn(null);
    $this->character->shouldReceive('__get')
        ->with('background')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('background')
        ->andReturn(null);
    $this->character->shouldReceive('offsetExists')->andReturn(true);

    $this->proficiencyService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'class' => [
                'skills' => [
                    'proficiency_type' => 'skill',
                    'proficiency_subcategory' => null,
                    'quantity' => 4,
                    'remaining' => 4,
                    'selected_skills' => [],
                    'selected_proficiency_types' => [],
                    'options' => [],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(0);
});

<?php

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
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
    // Mock character attributes with full_slug
    $primaryClass = (object) ['id' => 5, 'full_slug' => 'phb:rogue', 'name' => 'Rogue'];
    $this->character->shouldReceive('__get')
        ->with('primary_class')
        ->andReturn($primaryClass);
    $this->character->shouldReceive('getAttribute')
        ->with('primary_class')
        ->andReturn($primaryClass);
    $this->character->shouldReceive('offsetExists')->andReturn(true);

    // Mock proficiency service response for skill choice with full_slug
    $this->proficiencyService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'class' => [
                'skills' => [
                    'proficiency_type' => 'skill',
                    'proficiency_subcategory' => null,
                    'quantity' => 4,
                    'remaining' => 2,
                    'selected_skill_slugs' => ['phb:acrobatics', 'phb:stealth'],
                    'selected_proficiency_type_slugs' => [],
                    'options' => [
                        [
                            'type' => 'skill',
                            'skill_slug' => 'phb:acrobatics',
                            'skill' => ['full_slug' => 'phb:acrobatics', 'name' => 'Acrobatics', 'slug' => 'acrobatics'],
                        ],
                        [
                            'type' => 'skill',
                            'skill_slug' => 'phb:stealth',
                            'skill' => ['full_slug' => 'phb:stealth', 'name' => 'Stealth', 'slug' => 'stealth'],
                        ],
                        [
                            'type' => 'skill',
                            'skill_slug' => 'phb:perception',
                            'skill' => ['full_slug' => 'phb:perception', 'name' => 'Perception', 'slug' => 'perception'],
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
        ->first()->selected->toBe(['phb:acrobatics', 'phb:stealth'])
        ->first()->options->toHaveCount(3)
        ->first()->optionsEndpoint->toBeNull();
});

it('transforms service output to PendingChoice objects for proficiency type choices', function () {
    // Mock character attributes with full_slug
    $background = (object) ['id' => 3, 'full_slug' => 'phb:guild-artisan', 'name' => 'Guild Artisan'];
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
                    'selected_skill_slugs' => [],
                    'selected_proficiency_type_slugs' => [],
                    'options' => [
                        [
                            'type' => 'proficiency_type',
                            'proficiency_type_slug' => 'phb:smiths-tools',
                            'proficiency_type' => ['full_slug' => 'phb:smiths-tools', 'name' => "Smith's Tools", 'slug' => 'smiths-tools'],
                        ],
                        [
                            'type' => 'proficiency_type',
                            'proficiency_type_slug' => 'phb:carpenters-tools',
                            'proficiency_type' => ['full_slug' => 'phb:carpenters-tools', 'name' => "Carpenter's Tools", 'slug' => 'carpenters-tools'],
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
    // Mock character attributes with full_slug
    $primaryClass = (object) ['id' => 5, 'full_slug' => 'phb:rogue', 'name' => 'Rogue'];
    $race = (object) ['id' => 2, 'full_slug' => 'phb:half-elf', 'name' => 'Half-Elf'];

    $this->character->shouldReceive('__get')
        ->with('primary_class')
        ->andReturn($primaryClass);
    $this->character->shouldReceive('getAttribute')
        ->with('primary_class')
        ->andReturn($primaryClass);
    $this->character->shouldReceive('__get')
        ->with('race')
        ->andReturn($race);
    $this->character->shouldReceive('getAttribute')
        ->with('race')
        ->andReturn($race);
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
                    'selected_skill_slugs' => [],
                    'selected_proficiency_type_slugs' => [],
                    'options' => [
                        ['type' => 'skill', 'skill_slug' => 'phb:acrobatics', 'skill' => ['full_slug' => 'phb:acrobatics', 'name' => 'Acrobatics', 'slug' => 'acrobatics']],
                    ],
                ],
            ],
            'race' => [
                'skill_versatility' => [
                    'proficiency_type' => 'skill',
                    'proficiency_subcategory' => null,
                    'quantity' => 2,
                    'remaining' => 2,
                    'selected_skill_slugs' => [],
                    'selected_proficiency_type_slugs' => [],
                    'options' => [
                        ['type' => 'skill', 'skill_slug' => 'phb:perception', 'skill' => ['full_slug' => 'phb:perception', 'name' => 'Perception', 'slug' => 'perception']],
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
        id: 'proficiency|class|phb:rogue|1|skills',
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

    $skillSlugs = ['phb:acrobatics', 'phb:stealth', 'phb:perception', 'phb:insight'];

    $this->proficiencyService->shouldReceive('makeSkillChoice')
        ->once()
        ->with($this->character, 'class', 'skills', $skillSlugs);

    $this->handler->resolve($this->character, $choice, ['selected' => $skillSlugs]);
});

it('resolves proficiency type choice by calling makeProficiencyTypeChoice', function () {
    $choice = new PendingChoice(
        id: 'proficiency|background|phb:guild-artisan|1|artisan_tools',
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

    $profTypeSlugs = ['phb:smiths-tools'];

    $this->proficiencyService->shouldReceive('makeProficiencyTypeChoice')
        ->once()
        ->with($this->character, 'background', 'artisan_tools', $profTypeSlugs);

    $this->handler->resolve($this->character, $choice, ['selected' => $profTypeSlugs]);
});

it('throws exception when selection is empty', function () {
    $choice = new PendingChoice(
        id: 'proficiency|class|phb:rogue|1|skills',
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
        id: 'proficiency|class|phb:rogue|1|skills',
        type: 'proficiency',
        subtype: 'skill',
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 4,
        remaining: 0,
        selected: ['phb:acrobatics', 'phb:stealth', 'phb:perception', 'phb:insight'],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'skills']
    );

    expect($this->handler->canUndo($this->character, $choice))->toBeTrue();
});

it('undoes choice by clearing proficiencies', function () {
    $choice = new PendingChoice(
        id: 'proficiency|class|phb:rogue|1|skills',
        type: 'proficiency',
        subtype: 'skill',
        source: 'class',
        sourceName: 'Rogue',
        levelGranted: 1,
        required: true,
        quantity: 4,
        remaining: 0,
        selected: ['phb:acrobatics', 'phb:stealth', 'phb:perception', 'phb:insight'],
        options: [],
        optionsEndpoint: null,
        metadata: ['choice_group' => 'skills']
    );

    // Mock proficiencies relationship
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

it('skips choices with invalid source slug', function () {
    // Mock character with no primary class
    $this->character->shouldReceive('__get')
        ->with('primary_class')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('primary_class')
        ->andReturn(null);
    $this->character->shouldReceive('__get')
        ->with('race')
        ->andReturn(null);
    $this->character->shouldReceive('getAttribute')
        ->with('race')
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
                    'selected_skill_slugs' => [],
                    'selected_proficiency_type_slugs' => [],
                    'options' => [],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(0);
});

it('transforms service output for subclass_feature source', function () {
    // Mock character with cleric class and nature domain subclass
    $feature = Mockery::mock(\stdClass::class);
    $feature->feature_name = 'Acolyte of Nature (Nature Domain)';

    $subclass = Mockery::mock(\stdClass::class);
    $subclass->id = 15;
    $subclass->full_slug = 'phb:cleric-nature-domain';
    $subclass->name = 'Nature Domain';
    $subclass->shouldReceive('getFeatureByProficiencyChoiceGroup')
        ->with('feature_skill_choice_1')
        ->andReturn($feature);

    $characterClassPivot = Mockery::mock(\stdClass::class);
    $characterClassPivot->subclass = $subclass;

    $characterClasses = collect([$characterClassPivot]);

    $this->character->shouldReceive('__get')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('getAttribute')
        ->with('characterClasses')
        ->andReturn($characterClasses);
    $this->character->shouldReceive('offsetExists')->andReturn(true);

    // Mock proficiency service response for subclass_feature
    $this->proficiencyService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'subclass_feature' => [
                'feature_skill_choice_1' => [
                    'proficiency_type' => 'skill',
                    'proficiency_subcategory' => null,
                    'quantity' => 1,
                    'remaining' => 1,
                    'selected_skill_slugs' => [],
                    'selected_proficiency_type_slugs' => [],
                    'options' => [
                        [
                            'type' => 'skill',
                            'skill_slug' => 'core:animal-handling',
                            'skill' => ['full_slug' => 'core:animal-handling', 'name' => 'Animal Handling', 'slug' => 'animal-handling'],
                        ],
                        [
                            'type' => 'skill',
                            'skill_slug' => 'core:nature',
                            'skill' => ['full_slug' => 'core:nature', 'name' => 'Nature', 'slug' => 'nature'],
                        ],
                        [
                            'type' => 'skill',
                            'skill_slug' => 'core:survival',
                            'skill' => ['full_slug' => 'core:survival', 'name' => 'Survival', 'slug' => 'survival'],
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
        ->first()->source->toBe('subclass_feature')
        ->first()->sourceName->toContain('Acolyte of Nature')
        ->first()->quantity->toBe(1)
        ->first()->remaining->toBe(1)
        ->first()->selected->toBe([])
        ->first()->options->toHaveCount(3);

    // Verify options contain the expected skills
    $firstChoice = $choices->first();
    $optionSlugs = collect($firstChoice->options)->pluck('full_slug')->all();
    expect($optionSlugs)->toContain('core:animal-handling', 'core:nature', 'core:survival');
});

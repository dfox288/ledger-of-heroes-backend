<?php

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\Language;
use App\Services\CharacterLanguageService;
use App\Services\ChoiceHandlers\LanguageChoiceHandler;

beforeEach(function () {
    $this->languageService = Mockery::mock(CharacterLanguageService::class);
    $this->handler = new LanguageChoiceHandler($this->languageService);
    $this->character = Mockery::mock(Character::class);
});

afterEach(function () {
    Mockery::close();
});

it('returns correct type', function () {
    expect($this->handler->getType())->toBe('language');
});

it('transforms service output to PendingChoice objects for race language choice', function () {
    // Mock character attributes with full_slug
    $this->character->shouldReceive('getAttribute')->with('race')->andReturn((object) [
        'full_slug' => 'phb:high-elf',
        'name' => 'High Elf',
    ]);
    $this->character->shouldReceive('getAttribute')->with('background')->andReturn(null);

    // Mock language service response with full_slug
    $this->languageService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'race' => [
                'known' => [
                    ['full_slug' => 'phb:elvish', 'name' => 'Elvish', 'slug' => 'elvish', 'script' => 'Elvish'],
                ],
                'choices' => [
                    'quantity' => 1,
                    'remaining' => 1,
                    'selected' => [],
                    'options' => [
                        ['full_slug' => 'phb:common', 'name' => 'Common', 'slug' => 'common', 'script' => 'Common'],
                        ['full_slug' => 'phb:dwarvish', 'name' => 'Dwarvish', 'slug' => 'dwarvish', 'script' => 'Dwarvish'],
                    ],
                ],
            ],
            'background' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
            'feat' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->toBeInstanceOf(PendingChoice::class)
        ->first()->type->toBe('language')
        ->first()->subtype->toBeNull()
        ->first()->source->toBe('race')
        ->first()->sourceName->toBe('High Elf')
        ->first()->levelGranted->toBe(1)
        ->first()->required->toBeTrue()
        ->first()->quantity->toBe(1)
        ->first()->remaining->toBe(1)
        ->first()->selected->toBe([])
        ->first()->options->toHaveCount(2)
        ->first()->optionsEndpoint->toBeNull()
        ->first()->metadata->toHaveKey('known_languages');
});

it('skips sources with no choices', function () {
    $this->character->shouldReceive('getAttribute')->with('race')->andReturn(null);
    $this->character->shouldReceive('getAttribute')->with('background')->andReturn(null);

    $this->languageService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'race' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
            'background' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
            'feat' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(0);
});

it('includes selected languages in the choice', function () {
    $this->character->shouldReceive('getAttribute')->with('race')->andReturn((object) [
        'full_slug' => 'phb:human',
        'name' => 'Human',
    ]);
    $this->character->shouldReceive('getAttribute')->with('background')->andReturn(null);

    $this->languageService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'race' => [
                'known' => [
                    ['full_slug' => 'phb:common', 'name' => 'Common', 'slug' => 'common', 'script' => 'Common'],
                ],
                'choices' => [
                    'quantity' => 1,
                    'remaining' => 0,
                    'selected' => ['phb:dwarvish'],
                    'options' => [],
                ],
            ],
            'background' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
            'feat' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->selected->toBe(['phb:dwarvish'])
        ->first()->remaining->toBe(0);
});

it('handles multiple sources with choices', function () {
    $this->character->shouldReceive('getAttribute')->with('race')->andReturn((object) [
        'full_slug' => 'phb:half-elf',
        'name' => 'Half-Elf',
    ]);
    $this->character->shouldReceive('getAttribute')->with('background')->andReturn((object) [
        'full_slug' => 'phb:sage',
        'name' => 'Sage',
    ]);

    $this->languageService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'race' => [
                'known' => [],
                'choices' => [
                    'quantity' => 2,
                    'remaining' => 2,
                    'selected' => [],
                    'options' => [
                        ['full_slug' => 'phb:common', 'name' => 'Common', 'slug' => 'common', 'script' => 'Common'],
                    ],
                ],
            ],
            'background' => [
                'known' => [],
                'choices' => [
                    'quantity' => 1,
                    'remaining' => 1,
                    'selected' => [],
                    'options' => [
                        ['full_slug' => 'phb:dwarvish', 'name' => 'Dwarvish', 'slug' => 'dwarvish', 'script' => 'Dwarvish'],
                    ],
                ],
            ],
            'feat' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(2);
    expect($choices->pluck('source')->toArray())->toBe(['race', 'background']);
});

it('handles feat source correctly', function () {
    $this->character->shouldReceive('getAttribute')->with('race')->andReturn(null);
    $this->character->shouldReceive('getAttribute')->with('background')->andReturn(null);

    $this->languageService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'race' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
            'background' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
            'feat' => [
                'known' => [],
                'choices' => [
                    'quantity' => 1,
                    'remaining' => 1,
                    'selected' => [],
                    'options' => [
                        ['full_slug' => 'phb:dwarvish', 'name' => 'Dwarvish', 'slug' => 'dwarvish', 'script' => 'Dwarvish'],
                    ],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(1);

    $featChoice = $choices->first();
    expect($featChoice->source)->toBe('feat');
    expect($featChoice->sourceName)->toBe('Feat');
    expect($featChoice->id)->toBe('language|feat|feat|1|language_choice');
});

it('resolves choices by calling the language service', function () {
    $choice = new PendingChoice(
        id: 'language|race|phb:high-elf|1|language_choice',
        type: 'language',
        subtype: null,
        source: 'race',
        sourceName: 'High Elf',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [['full_slug' => 'phb:common', 'name' => 'Common', 'slug' => 'common']],
        optionsEndpoint: null,
        metadata: [],
    );

    // Now expects slugs to be passed
    $this->languageService
        ->shouldReceive('makeChoice')
        ->with($this->character, 'race', ['phb:common'])
        ->once();

    $this->handler->resolve($this->character, $choice, ['selected' => ['phb:common']]);
});

it('throws exception when selection is empty', function () {
    $choice = new PendingChoice(
        id: 'language|race|phb:high-elf|1|language_choice',
        type: 'language',
        subtype: null,
        source: 'race',
        sourceName: 'High Elf',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: [],
    );

    expect(fn () => $this->handler->resolve($this->character, $choice, ['selected' => []]))
        ->toThrow(InvalidSelectionException::class);
});

it('can undo language choices', function () {
    $choice = new PendingChoice(
        id: 'language|race|phb:high-elf|1|language_choice',
        type: 'language',
        subtype: null,
        source: 'race',
        sourceName: 'High Elf',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 0,
        selected: ['phb:common'],
        options: [],
        optionsEndpoint: null,
        metadata: [],
    );

    expect($this->handler->canUndo($this->character, $choice))->toBeTrue();
});

it('generates correct choice IDs for different sources', function () {
    $this->character->shouldReceive('getAttribute')->with('race')->andReturn((object) [
        'full_slug' => 'phb:elf',
        'name' => 'Elf',
    ]);
    $this->character->shouldReceive('getAttribute')->with('background')->andReturn((object) [
        'full_slug' => 'phb:sage',
        'name' => 'Sage',
    ]);

    $this->languageService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'race' => [
                'known' => [],
                'choices' => [
                    'quantity' => 1,
                    'remaining' => 1,
                    'selected' => [],
                    'options' => [
                        ['full_slug' => 'phb:common', 'name' => 'Common', 'slug' => 'common', 'script' => 'Common'],
                    ],
                ],
            ],
            'background' => [
                'known' => [],
                'choices' => [
                    'quantity' => 1,
                    'remaining' => 1,
                    'selected' => [],
                    'options' => [
                        ['full_slug' => 'phb:dwarvish', 'name' => 'Dwarvish', 'slug' => 'dwarvish', 'script' => 'Dwarvish'],
                    ],
                ],
            ],
            'feat' => [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(2);

    $raceChoice = $choices->first(fn ($c) => $c->source === 'race');
    $backgroundChoice = $choices->first(fn ($c) => $c->source === 'background');

    expect($raceChoice->id)->toBe('language|race|phb:elf|1|language_choice');
    expect($backgroundChoice->id)->toBe('language|background|phb:sage|1|language_choice');
});

it('resolves slugs via language service', function () {
    $choice = new PendingChoice(
        id: 'language|race|phb:high-elf|1|language_choice',
        type: 'language',
        subtype: null,
        source: 'race',
        sourceName: 'High Elf',
        levelGranted: 1,
        required: true,
        quantity: 2,
        remaining: 2,
        selected: [],
        options: [],
        optionsEndpoint: null,
        metadata: [],
    );

    // Selection contains slugs
    $this->languageService
        ->shouldReceive('makeChoice')
        ->with($this->character, 'race', ['phb:common', 'phb:dwarvish'])
        ->once();

    $this->handler->resolve($this->character, $choice, ['selected' => ['phb:common', 'phb:dwarvish']]);
});

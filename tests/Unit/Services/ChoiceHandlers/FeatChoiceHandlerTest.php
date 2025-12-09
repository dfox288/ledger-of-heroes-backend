<?php

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Services\ChoiceHandlers\FeatChoiceHandler;
use App\Services\FeatChoiceService;

beforeEach(function () {
    $this->featService = Mockery::mock(FeatChoiceService::class);
    $this->handler = new FeatChoiceHandler($this->featService);
    $this->character = Mockery::mock(Character::class);
});

afterEach(function () {
    Mockery::close();
});

it('returns correct type', function () {
    expect($this->handler->getType())->toBe('feat');
});

it('transforms service output to PendingChoice objects for race bonus feat', function () {
    // Mock character with race
    $race = (object) [
        'full_slug' => 'phb:variant-human',
        'name' => 'Variant Human',
    ];
    $this->character->shouldReceive('getAttribute')->with('race')->andReturn($race);
    $this->character->shouldReceive('offsetExists')->with('race')->andReturn(true);
    $this->character->shouldReceive('getAttribute')->with('background')->andReturn(null);
    $this->character->shouldReceive('offsetExists')->with('background')->andReturn(false);

    // Mock feat service response
    $this->featService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'race' => [
                'quantity' => 1,
                'remaining' => 1,
                'selected' => [],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->toBeInstanceOf(PendingChoice::class)
        ->first()->type->toBe('feat')
        ->first()->subtype->toBeNull()
        ->first()->source->toBe('race')
        ->first()->sourceName->toBe('Variant Human')
        ->first()->levelGranted->toBe(1)
        ->first()->required->toBeTrue()
        ->first()->quantity->toBe(1)
        ->first()->remaining->toBe(1)
        ->first()->selected->toBe([])
        ->first()->options->toBeNull()
        ->first()->optionsEndpoint->toBe('/api/v1/feats');
});

it('includes completed choices with remaining zero', function () {
    $this->character->shouldReceive('getAttribute')->with('race')->andReturn((object) [
        'full_slug' => 'phb:variant-human',
        'name' => 'Variant Human',
    ]);
    $this->character->shouldReceive('offsetExists')->with('race')->andReturn(true);
    $this->character->shouldReceive('getAttribute')->with('background')->andReturn(null);
    $this->character->shouldReceive('offsetExists')->with('background')->andReturn(false);

    // Already selected a feat - should still appear with remaining: 0
    $this->featService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'race' => [
                'quantity' => 1,
                'remaining' => 0,
                'selected' => ['phb:alert'],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    // Regression test for #400: completed choices must remain visible
    expect($choices)
        ->toHaveCount(1)
        ->first()->remaining->toBe(0)
        ->first()->selected->toBe(['phb:alert']);
});

it('returns empty collection when no bonus feats available', function () {
    $this->character->shouldReceive('getAttribute')->with('race')->andReturn((object) [
        'full_slug' => 'phb:high-elf',
        'name' => 'High Elf',
    ]);
    $this->character->shouldReceive('getAttribute')->with('background')->andReturn(null);

    $this->featService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toHaveCount(0);
});

it('resolves choice by calling feat service', function () {
    $choice = new PendingChoice(
        id: 'feat|race|phb:variant-human|1|bonus_feat',
        type: 'feat',
        subtype: null,
        source: 'race',
        sourceName: 'Variant Human',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: null,
        optionsEndpoint: '/api/v1/feats',
        metadata: [],
    );

    $this->featService->shouldReceive('makeChoice')
        ->once()
        ->with($this->character, 'race', 'phb:alert')
        ->andReturn([
            'feat' => ['full_slug' => 'phb:alert', 'slug' => 'alert', 'name' => 'Alert'],
            'source' => 'race',
            'proficiencies_gained' => [],
            'spells_gained' => [],
            'hp_bonus' => 0,
            'ability_increases' => [],
        ]);

    $this->handler->resolve($this->character, $choice, ['feat_slug' => 'phb:alert']);
});

it('resolves choice with selected array format', function () {
    $choice = new PendingChoice(
        id: 'feat|race|phb:variant-human|1|bonus_feat',
        type: 'feat',
        subtype: null,
        source: 'race',
        sourceName: 'Variant Human',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: null,
        optionsEndpoint: '/api/v1/feats',
        metadata: [],
    );

    $this->featService->shouldReceive('makeChoice')
        ->once()
        ->with($this->character, 'race', 'phb:lucky')
        ->andReturn([
            'feat' => ['full_slug' => 'phb:lucky', 'slug' => 'lucky', 'name' => 'Lucky'],
            'source' => 'race',
            'proficiencies_gained' => [],
            'spells_gained' => [],
            'hp_bonus' => 0,
            'ability_increases' => [],
        ]);

    $this->handler->resolve($this->character, $choice, ['selected' => ['phb:lucky']]);
});

it('throws exception when no feat slug provided', function () {
    $choice = new PendingChoice(
        id: 'feat|race|phb:variant-human|1|bonus_feat',
        type: 'feat',
        subtype: null,
        source: 'race',
        sourceName: 'Variant Human',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: null,
        optionsEndpoint: '/api/v1/feats',
        metadata: [],
    );

    $this->handler->resolve($this->character, $choice, []);
})->throws(InvalidSelectionException::class);

it('can undo choices', function () {
    $choice = new PendingChoice(
        id: 'feat|race|phb:variant-human|1|bonus_feat',
        type: 'feat',
        subtype: null,
        source: 'race',
        sourceName: 'Variant Human',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 1,
        selected: [],
        options: null,
        optionsEndpoint: '/api/v1/feats',
        metadata: [],
    );
    expect($this->handler->canUndo($this->character, $choice))->toBeTrue();
});

it('undoes choice by calling feat service', function () {
    $choice = new PendingChoice(
        id: 'feat|race|phb:variant-human|1|bonus_feat',
        type: 'feat',
        subtype: null,
        source: 'race',
        sourceName: 'Variant Human',
        levelGranted: 1,
        required: true,
        quantity: 1,
        remaining: 0,
        selected: ['phb:alert'],
        options: null,
        optionsEndpoint: '/api/v1/feats',
        metadata: [],
    );

    $this->featService->shouldReceive('undoChoice')
        ->once()
        ->with($this->character, 'race', 'phb:alert');

    $this->handler->undo($this->character, $choice);
});

it('handles background bonus feat source', function () {
    $background = (object) [
        'full_slug' => 'phb:soldier',
        'name' => 'Soldier',
    ];
    $this->character->shouldReceive('getAttribute')->with('race')->andReturn(null);
    $this->character->shouldReceive('offsetExists')->with('race')->andReturn(false);
    $this->character->shouldReceive('getAttribute')->with('background')->andReturn($background);
    $this->character->shouldReceive('offsetExists')->with('background')->andReturn(true);

    $this->featService->shouldReceive('getPendingChoices')
        ->with($this->character)
        ->andReturn([
            'background' => [
                'quantity' => 1,
                'remaining' => 1,
                'selected' => [],
            ],
        ]);

    $choices = $this->handler->getChoices($this->character);

    expect($choices)
        ->toHaveCount(1)
        ->first()->source->toBe('background')
        ->first()->sourceName->toBe('Soldier');
});

it('handles background without modifiers relationship gracefully', function () {
    // Background model doesn't have HasModifiers trait, so modifiers() doesn't exist
    // The service should handle this gracefully without crashing
    $this->featService
        ->shouldReceive('getPendingChoices')
        ->once()
        ->with($this->character)
        ->andReturn([]); // Background without modifiers returns empty

    $choices = $this->handler->getChoices($this->character);

    expect($choices)->toBeEmpty();
});

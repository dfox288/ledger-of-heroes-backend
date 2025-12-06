<?php

declare(strict_types=1);

use App\DTOs\PendingChoice;
use App\Exceptions\ChoiceNotFoundException;
use App\Models\Character;
use App\Services\CharacterChoiceService;
use App\Services\ChoiceHandlers\ChoiceTypeHandler;
use Illuminate\Support\Collection;

describe('CharacterChoiceService', function () {
    beforeEach(function () {
        $this->service = new CharacterChoiceService;
        $this->character = Mockery::mock(Character::class);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('registers handler and adds to registry', function () {
        $handler = new class implements ChoiceTypeHandler
        {
            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect();
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $this->service->registerHandler($handler);

        expect($this->service->getRegisteredTypes())->toContain('proficiency');
    });

    it('returns registered types', function () {
        $handler1 = new class implements ChoiceTypeHandler
        {
            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect();
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $handler2 = new class implements ChoiceTypeHandler
        {
            public function getType(): string
            {
                return 'language';
            }

            public function getChoices(Character $character): Collection
            {
                return collect();
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $this->service->registerHandler($handler1);
        $this->service->registerHandler($handler2);

        expect($this->service->getRegisteredTypes())
            ->toBeArray()
            ->toHaveCount(2)
            ->toContain('proficiency')
            ->toContain('language');
    });

    it('aggregates pending choices from all handlers', function () {
        $choice1 = new PendingChoice(
            id: 'proficiency:class:rogue:1:skills',
            type: 'proficiency',
            subtype: 'skill',
            source: 'class',
            sourceName: 'Rogue',
            levelGranted: 1,
            required: true,
            quantity: 4,
            remaining: 2,
            selected: ['stealth', 'sleight-of-hand'],
            options: ['acrobatics', 'athletics'],
            optionsEndpoint: null
        );

        $choice2 = new PendingChoice(
            id: 'language:race:high-elf:1:bonus',
            type: 'language',
            subtype: null,
            source: 'race',
            sourceName: 'High Elf',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/lookups/languages'
        );

        $handler1 = new class($choice1) implements ChoiceTypeHandler
        {
            public function __construct(private PendingChoice $choice) {}

            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $handler2 = new class($choice2) implements ChoiceTypeHandler
        {
            public function __construct(private PendingChoice $choice) {}

            public function getType(): string
            {
                return 'language';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $this->service->registerHandler($handler1);
        $this->service->registerHandler($handler2);

        $choices = $this->service->getPendingChoices($this->character);

        expect($choices)
            ->toBeInstanceOf(Collection::class)
            ->toHaveCount(2);

        expect($choices->pluck('type')->toArray())
            ->toContain('proficiency')
            ->toContain('language');
    });

    it('filters pending choices by type when specified', function () {
        $choice1 = new PendingChoice(
            id: 'proficiency:class:rogue:1:skills',
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
            optionsEndpoint: null
        );

        $choice2 = new PendingChoice(
            id: 'language:race:high-elf:1:bonus',
            type: 'language',
            subtype: null,
            source: 'race',
            sourceName: 'High Elf',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/lookups/languages'
        );

        $handler1 = new class($choice1) implements ChoiceTypeHandler
        {
            public function __construct(private PendingChoice $choice) {}

            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $handler2 = new class($choice2) implements ChoiceTypeHandler
        {
            public function __construct(private PendingChoice $choice) {}

            public function getType(): string
            {
                return 'language';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $this->service->registerHandler($handler1);
        $this->service->registerHandler($handler2);

        $choices = $this->service->getPendingChoices($this->character, 'proficiency');

        expect($choices)
            ->toHaveCount(1);

        expect($choices->first()->type)->toBe('proficiency');
    });

    it('throws ChoiceNotFoundException for unknown choice type', function () {
        expect(fn () => $this->service->getChoice($this->character, 'unknown:class:fighter:1:skills'))
            ->toThrow(ChoiceNotFoundException::class, 'Unknown choice type: unknown');
    });

    it('throws ChoiceNotFoundException when choice not found in handler', function () {
        $handler = new class implements ChoiceTypeHandler
        {
            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect();
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $this->service->registerHandler($handler);

        expect(fn () => $this->service->getChoice($this->character, 'proficiency:class:rogue:1:skills'))
            ->toThrow(ChoiceNotFoundException::class);
    });

    it('returns correct summary structure', function () {
        $requiredChoice = new PendingChoice(
            id: 'proficiency:class:rogue:1:skills',
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
            optionsEndpoint: null
        );

        $optionalChoice = new PendingChoice(
            id: 'language:race:high-elf:1:bonus',
            type: 'language',
            subtype: null,
            source: 'race',
            sourceName: 'High Elf',
            levelGranted: 1,
            required: false,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/lookups/languages'
        );

        $completedChoice = new PendingChoice(
            id: 'proficiency:background:criminal:1:tools',
            type: 'proficiency',
            subtype: 'tool',
            source: 'background',
            sourceName: 'Criminal',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: ['thieves-tools'],
            options: [],
            optionsEndpoint: null
        );

        $handler1 = new class($requiredChoice, $completedChoice) implements ChoiceTypeHandler
        {
            public function __construct(
                private PendingChoice $choice1,
                private PendingChoice $choice2
            ) {}

            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice1, $this->choice2]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $handler2 = new class($optionalChoice) implements ChoiceTypeHandler
        {
            public function __construct(private PendingChoice $choice) {}

            public function getType(): string
            {
                return 'language';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $this->service->registerHandler($handler1);
        $this->service->registerHandler($handler2);

        $summary = $this->service->getSummary($this->character);

        expect($summary)
            ->toBeArray()
            ->toHaveKey('total_pending')
            ->toHaveKey('required_pending')
            ->toHaveKey('optional_pending')
            ->toHaveKey('by_type')
            ->toHaveKey('by_source');

        expect($summary['total_pending'])->toBe(2);
        expect($summary['required_pending'])->toBe(1);
        expect($summary['optional_pending'])->toBe(1);
        expect($summary['by_type'])->toBe([
            'proficiency' => 1,
            'language' => 1,
        ]);
        expect($summary['by_source'])->toBe([
            'class' => 1,
            'race' => 1,
        ]);
    });

    it('gets a specific choice by ID', function () {
        $choice = new PendingChoice(
            id: 'proficiency:class:rogue:1:skills',
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
            optionsEndpoint: null
        );

        $handler = new class($choice) implements ChoiceTypeHandler
        {
            public function __construct(private PendingChoice $choice) {}

            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $this->service->registerHandler($handler);

        $retrieved = $this->service->getChoice($this->character, 'proficiency:class:rogue:1:skills');

        expect($retrieved)
            ->toBeInstanceOf(PendingChoice::class)
            ->and($retrieved->id)->toBe('proficiency:class:rogue:1:skills')
            ->and($retrieved->type)->toBe('proficiency');
    });

    it('resolves a choice using the correct handler', function () {
        $choice = new PendingChoice(
            id: 'proficiency:class:rogue:1:skills',
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
            optionsEndpoint: null
        );

        $handler = new class($choice) implements ChoiceTypeHandler
        {
            public bool $resolved = false;

            public function __construct(
                private PendingChoice $choice
            ) {}

            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void
            {
                $this->resolved = true;
            }

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $this->service->registerHandler($handler);
        $this->service->resolveChoice($this->character, 'proficiency:class:rogue:1:skills', ['acrobatics']);

        expect($handler->resolved)->toBeTrue();
    });

    it('checks if a choice can be undone', function () {
        $choice = new PendingChoice(
            id: 'proficiency:class:rogue:1:skills',
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
            optionsEndpoint: null
        );

        $handler = new class($choice) implements ChoiceTypeHandler
        {
            public function __construct(private PendingChoice $choice) {}

            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return true;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $this->service->registerHandler($handler);

        expect($this->service->canUndoChoice($this->character, 'proficiency:class:rogue:1:skills'))
            ->toBeTrue();
    });

    it('undoes a choice when allowed', function () {
        $choice = new PendingChoice(
            id: 'proficiency:class:rogue:1:skills',
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
            optionsEndpoint: null
        );

        $handler = new class($choice) implements ChoiceTypeHandler
        {
            public bool $undone = false;

            public function __construct(
                private PendingChoice $choice
            ) {}

            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return true;
            }

            public function undo(Character $character, PendingChoice $choice): void
            {
                $this->undone = true;
            }
        };

        $this->service->registerHandler($handler);
        $this->service->undoChoice($this->character, 'proficiency:class:rogue:1:skills');

        expect($handler->undone)->toBeTrue();
    });

    it('throws ChoiceNotUndoableException when undo not allowed', function () {
        $choice = new PendingChoice(
            id: 'proficiency:class:rogue:1:skills',
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
            optionsEndpoint: null
        );

        $handler = new class($choice) implements ChoiceTypeHandler
        {
            public function __construct(private PendingChoice $choice) {}

            public function getType(): string
            {
                return 'proficiency';
            }

            public function getChoices(Character $character): Collection
            {
                return collect([$this->choice]);
            }

            public function resolve(Character $character, PendingChoice $choice, array $selection): void {}

            public function canUndo(Character $character, PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(Character $character, PendingChoice $choice): void {}
        };

        $this->service->registerHandler($handler);

        expect(fn () => $this->service->undoChoice($this->character, 'proficiency:class:rogue:1:skills'))
            ->toThrow(\App\Exceptions\ChoiceNotUndoableException::class);
    });
});

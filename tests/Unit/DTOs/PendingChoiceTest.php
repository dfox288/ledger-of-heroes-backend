<?php

declare(strict_types=1);

use App\DTOs\PendingChoice;

describe('PendingChoice', function () {
    it('returns true from isComplete when remaining is 0', function () {
        $choice = new PendingChoice(
            id: 'proficiency:class:rogue:1:skills',
            type: 'proficiency',
            subtype: 'skill',
            source: 'class',
            sourceName: 'Rogue',
            levelGranted: 1,
            required: true,
            quantity: 4,
            remaining: 0,
            selected: ['acrobatics', 'stealth', 'perception', 'investigation'],
            options: ['acrobatics', 'athletics', 'deception', 'insight'],
            optionsEndpoint: null,
            metadata: []
        );

        expect($choice->isComplete())->toBe(true);
    });

    it('returns false from isComplete when remaining is greater than 0', function () {
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
            selected: ['acrobatics', 'stealth'],
            options: ['acrobatics', 'athletics', 'deception', 'insight'],
            optionsEndpoint: null,
            metadata: []
        );

        expect($choice->isComplete())->toBe(false);
    });

    it('converts to array with snake_case keys', function () {
        $choice = new PendingChoice(
            id: 'language:race:high-elf:1:extra',
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
            optionsEndpoint: '/api/v1/lookups/languages',
            metadata: ['restriction' => 'no_exotic']
        );

        $array = $choice->toArray();

        expect($array)->toBe([
            'id' => 'language:race:high-elf:1:extra',
            'type' => 'language',
            'subtype' => null,
            'source' => 'race',
            'source_name' => 'High Elf',
            'level_granted' => 1,
            'required' => true,
            'quantity' => 1,
            'remaining' => 1,
            'selected' => [],
            'options' => null,
            'options_endpoint' => '/api/v1/lookups/languages',
            'metadata' => ['restriction' => 'no_exotic'],
        ]);
    });

    it('handles optional metadata parameter with empty array default', function () {
        $choice = new PendingChoice(
            id: 'spell:class:wizard:1:cantrips',
            type: 'spell',
            subtype: 'cantrip',
            source: 'class',
            sourceName: 'Wizard',
            levelGranted: 1,
            required: true,
            quantity: 3,
            remaining: 3,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/spells?filter=level=0 AND class_slugs IN [wizard]',
        );

        expect($choice->metadata)->toBe([]);
        expect($choice->toArray()['metadata'])->toBe([]);
    });
});

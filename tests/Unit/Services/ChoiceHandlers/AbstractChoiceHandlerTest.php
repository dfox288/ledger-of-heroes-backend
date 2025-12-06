<?php

namespace Tests\Unit\Services\ChoiceHandlers;

use App\Services\ChoiceHandlers\AbstractChoiceHandler;

describe('AbstractChoiceHandler', function () {
    beforeEach(function () {
        // Create a concrete implementation for testing
        $this->handler = new class extends AbstractChoiceHandler
        {
            public function getType(): string
            {
                return 'test';
            }

            public function getChoices(\App\Models\Character $character): \Illuminate\Support\Collection
            {
                return collect();
            }

            public function resolve(\App\Models\Character $character, \App\DTOs\PendingChoice $choice, array $selection): void
            {
                // No-op for testing
            }

            public function canUndo(\App\Models\Character $character, \App\DTOs\PendingChoice $choice): bool
            {
                return false;
            }

            public function undo(\App\Models\Character $character, \App\DTOs\PendingChoice $choice): void
            {
                // No-op for testing
            }

            // Expose protected methods for testing
            public function testGenerateChoiceId(
                string $type,
                string $source,
                int $sourceId,
                int $level,
                string $group
            ): string {
                return $this->generateChoiceId($type, $source, $sourceId, $level, $group);
            }

            public function testParseChoiceId(string $choiceId): array
            {
                return $this->parseChoiceId($choiceId);
            }
        };
    });

    describe('generateChoiceId', function () {
        it('produces correct format with all components', function () {
            $choiceId = $this->handler->testGenerateChoiceId(
                'proficiency',
                'class',
                1,
                1,
                'skills'
            );

            expect($choiceId)->toBe('proficiency:class:1:1:skills');
        });

        it('handles different types correctly', function () {
            $choiceId = $this->handler->testGenerateChoiceId(
                'spell',
                'race',
                42,
                3,
                'cantrips'
            );

            expect($choiceId)->toBe('spell:race:42:3:cantrips');
        });

        it('handles zero values correctly', function () {
            $choiceId = $this->handler->testGenerateChoiceId(
                'language',
                'background',
                0,
                0,
                ''
            );

            expect($choiceId)->toBe('language:background:0:0:');
        });

        it('creates unique IDs for different parameters', function () {
            $id1 = $this->handler->testGenerateChoiceId('proficiency', 'class', 1, 1, 'skills');
            $id2 = $this->handler->testGenerateChoiceId('proficiency', 'class', 1, 1, 'tools');
            $id3 = $this->handler->testGenerateChoiceId('proficiency', 'class', 1, 2, 'skills');

            expect($id1)->not->toBe($id2);
            expect($id1)->not->toBe($id3);
            expect($id2)->not->toBe($id3);
        });
    });

    describe('parseChoiceId', function () {
        it('correctly parses a valid choice ID', function () {
            $parsed = $this->handler->testParseChoiceId('proficiency:class:1:1:skills');

            expect($parsed)->toBe([
                'type' => 'proficiency',
                'source' => 'class',
                'sourceId' => 1,
                'level' => 1,
                'group' => 'skills',
            ]);
        });

        it('correctly parses numeric values', function () {
            $parsed = $this->handler->testParseChoiceId('spell:race:42:3:cantrips');

            expect($parsed)->toMatchArray([
                'sourceId' => 42,
                'level' => 3,
            ])
                ->and($parsed['sourceId'])->toBeInt()
                ->and($parsed['level'])->toBeInt();
        });

        it('handles empty group correctly', function () {
            $parsed = $this->handler->testParseChoiceId('language:background:5:0:');

            expect($parsed)->toMatchArray([
                'type' => 'language',
                'source' => 'background',
                'sourceId' => 5,
                'level' => 0,
                'group' => '',
            ]);
        });

        it('throws exception for incomplete choice IDs', function () {
            expect(fn () => $this->handler->testParseChoiceId('proficiency:class'))
                ->toThrow(\App\Exceptions\InvalidChoiceException::class);
        });

        it('throws exception for empty string', function () {
            expect(fn () => $this->handler->testParseChoiceId(''))
                ->toThrow(\App\Exceptions\InvalidChoiceException::class);
        });

        it('throws exception for choice ID with wrong segment count', function () {
            // Too few segments
            expect(fn () => $this->handler->testParseChoiceId('a:b:c'))
                ->toThrow(\App\Exceptions\InvalidChoiceException::class);

            // Too many segments
            expect(fn () => $this->handler->testParseChoiceId('a:b:c:d:e:f'))
                ->toThrow(\App\Exceptions\InvalidChoiceException::class);
        });
    });

    describe('round-trip conversion', function () {
        it('correctly round-trips through generate and parse', function () {
            $original = [
                'type' => 'proficiency',
                'source' => 'class',
                'sourceId' => 1,
                'level' => 1,
                'group' => 'skills',
            ];

            $choiceId = $this->handler->testGenerateChoiceId(
                $original['type'],
                $original['source'],
                $original['sourceId'],
                $original['level'],
                $original['group']
            );

            $parsed = $this->handler->testParseChoiceId($choiceId);

            expect($parsed)->toBe($original);
        });

        it('maintains data integrity with various inputs', function () {
            $testCases = [
                ['spell', 'race', 99, 20, 'level-9-spells'],
                ['expertise', 'class', 3, 6, 'bard-expertise'],
                ['fighting_style', 'class', 1, 1, 'fighter-style'],
            ];

            foreach ($testCases as [$type, $source, $sourceId, $level, $group]) {
                $choiceId = $this->handler->testGenerateChoiceId($type, $source, $sourceId, $level, $group);
                $parsed = $this->handler->testParseChoiceId($choiceId);

                expect($parsed)->toBe([
                    'type' => $type,
                    'source' => $source,
                    'sourceId' => $sourceId,
                    'level' => $level,
                    'group' => $group,
                ]);
            }
        });
    });
});

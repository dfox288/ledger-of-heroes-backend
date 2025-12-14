<?php

declare(strict_types=1);

use App\Services\LevelUpFlowTesting\LevelUpStateSnapshot;

describe('LevelUpStateSnapshot', function () {
    describe('deriveLevelUpFields', function () {
        it('extracts total_level from character data', function () {
            $snapshot = new LevelUpStateSnapshot;

            $result = $snapshot->deriveLevelUpFields([
                'character' => [
                    'data' => [
                        'total_level' => 5,
                        'max_hit_points' => 45,
                        'classes' => [],
                        'ability_scores' => [],
                    ],
                ],
                'features' => ['data' => []],
                'pending_choices' => ['data' => ['choices' => []]],
            ]);

            expect($result['total_level'])->toBe(5);
        });

        it('extracts max_hp from character data', function () {
            $snapshot = new LevelUpStateSnapshot;

            $result = $snapshot->deriveLevelUpFields([
                'character' => [
                    'data' => [
                        'total_level' => 3,
                        'max_hit_points' => 28,
                        'classes' => [],
                        'ability_scores' => [],
                    ],
                ],
                'features' => ['data' => []],
                'pending_choices' => ['data' => ['choices' => []]],
            ]);

            expect($result['max_hp'])->toBe(28);
        });

        it('extracts class_levels from character classes', function () {
            $snapshot = new LevelUpStateSnapshot;

            $result = $snapshot->deriveLevelUpFields([
                'character' => [
                    'data' => [
                        'total_level' => 7,
                        'max_hit_points' => 55,
                        'classes' => [
                            ['class' => ['slug' => 'phb:fighter'], 'level' => 5],
                            ['class' => ['slug' => 'phb:rogue'], 'level' => 2],
                        ],
                        'ability_scores' => [],
                    ],
                ],
                'features' => ['data' => []],
                'pending_choices' => ['data' => ['choices' => []]],
            ]);

            expect($result['class_levels'])->toBe([
                'phb:fighter' => 5,
                'phb:rogue' => 2,
            ]);
        });

        it('counts required pending choices', function () {
            $snapshot = new LevelUpStateSnapshot;

            $result = $snapshot->deriveLevelUpFields([
                'character' => [
                    'data' => [
                        'total_level' => 4,
                        'max_hit_points' => 35,
                        'classes' => [],
                        'ability_scores' => [],
                    ],
                ],
                'features' => ['data' => []],
                'pending_choices' => [
                    'data' => [
                        'choices' => [
                            ['type' => 'hp', 'required' => true, 'remaining' => 1],
                            ['type' => 'asi', 'required' => true, 'remaining' => 1],
                            ['type' => 'spell', 'required' => false, 'remaining' => 2],
                        ],
                    ],
                ],
            ]);

            expect($result['required_pending_count'])->toBe(2);
        });

        it('extracts ability_score_totals', function () {
            $snapshot = new LevelUpStateSnapshot;

            $result = $snapshot->deriveLevelUpFields([
                'character' => [
                    'data' => [
                        'total_level' => 4,
                        'max_hit_points' => 35,
                        'classes' => [],
                        'ability_scores' => [
                            'STR' => 16,
                            'DEX' => 14,
                            'CON' => 15,
                            'INT' => 10,
                            'WIS' => 12,
                            'CHA' => 8,
                        ],
                    ],
                ],
                'features' => ['data' => []],
                'pending_choices' => ['data' => ['choices' => []]],
            ]);

            expect($result['ability_score_totals'])->toBe([
                'STR' => 16,
                'DEX' => 14,
                'CON' => 15,
                'INT' => 10,
                'WIS' => 12,
                'CHA' => 8,
            ]);
        });

        it('extracts feat_slugs from features', function () {
            $snapshot = new LevelUpStateSnapshot;

            $result = $snapshot->deriveLevelUpFields([
                'character' => [
                    'data' => [
                        'total_level' => 4,
                        'max_hit_points' => 35,
                        'classes' => [],
                        'ability_scores' => [],
                    ],
                ],
                'features' => [
                    'data' => [
                        ['slug' => 'phb:great-weapon-master', 'source' => 'feat'],
                        ['slug' => 'phb:second-wind', 'source' => 'class:phb:fighter'],
                        ['slug' => 'phb:sentinel', 'source' => 'feat'],
                    ],
                ],
                'pending_choices' => ['data' => ['choices' => []]],
            ]);

            expect($result['feat_slugs'])->toBe(['phb:great-weapon-master', 'phb:sentinel']);
        });

        it('handles missing data gracefully', function () {
            $snapshot = new LevelUpStateSnapshot;

            $result = $snapshot->deriveLevelUpFields([
                'character' => ['data' => []],
                'features' => ['data' => []],
                'pending_choices' => ['data' => ['choices' => []]],
            ]);

            expect($result['total_level'])->toBe(0);
            expect($result['max_hp'])->toBe(0);
            expect($result['class_levels'])->toBe([]);
            expect($result['required_pending_count'])->toBe(0);
            expect($result['ability_score_totals'])->toBe([]);
            expect($result['feat_slugs'])->toBe([]);
        });

        it('extracts subclass information', function () {
            $snapshot = new LevelUpStateSnapshot;

            $result = $snapshot->deriveLevelUpFields([
                'character' => [
                    'data' => [
                        'total_level' => 3,
                        'max_hit_points' => 28,
                        'classes' => [
                            [
                                'class' => ['slug' => 'phb:fighter'],
                                'level' => 3,
                                'subclass' => ['slug' => 'phb:champion'],
                            ],
                        ],
                        'ability_scores' => [],
                    ],
                ],
                'features' => ['data' => []],
                'pending_choices' => ['data' => ['choices' => []]],
            ]);

            expect($result['subclasses'])->toBe(['phb:fighter' => 'phb:champion']);
        });
    });
});

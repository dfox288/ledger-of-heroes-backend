<?php

declare(strict_types=1);

use App\Services\LevelUpFlowTesting\LevelUpValidator;

describe('LevelUpValidator', function () {
    describe('validateLevelUp', function () {
        it('passes when level incremented correctly', function () {
            $validator = new LevelUpValidator;

            $before = createSnapshot(totalLevel: 3, maxHp: 25, classLevels: ['phb:fighter' => 3]);
            $after = createSnapshot(totalLevel: 4, maxHp: 30, classLevels: ['phb:fighter' => 4]);

            $result = $validator->validateLevelUp($before, $after, 'phb:fighter', 4);

            expect($result->passed)->toBeTrue();
        });

        it('fails when total level not incremented', function () {
            $validator = new LevelUpValidator;

            $before = createSnapshot(totalLevel: 3, maxHp: 25, classLevels: ['phb:fighter' => 3]);
            $after = createSnapshot(totalLevel: 3, maxHp: 30, classLevels: ['phb:fighter' => 4]);

            $result = $validator->validateLevelUp($before, $after, 'phb:fighter', 4);

            expect($result->passed)->toBeFalse();
            expect($result->errors)->toContain('Total level did not increment: expected 4, got 3');
        });

        it('fails when class level not incremented', function () {
            $validator = new LevelUpValidator;

            $before = createSnapshot(totalLevel: 3, maxHp: 25, classLevels: ['phb:fighter' => 3]);
            $after = createSnapshot(totalLevel: 4, maxHp: 30, classLevels: ['phb:fighter' => 3]);

            $result = $validator->validateLevelUp($before, $after, 'phb:fighter', 4);

            expect($result->passed)->toBeFalse();
            expect($result->errors)->toContain('Class level did not increment for phb:fighter: expected 4, got 3');
        });

        it('fails when HP not increased', function () {
            $validator = new LevelUpValidator;

            $before = createSnapshot(totalLevel: 3, maxHp: 25, classLevels: ['phb:fighter' => 3]);
            $after = createSnapshot(totalLevel: 4, maxHp: 25, classLevels: ['phb:fighter' => 4]);

            $result = $validator->validateLevelUp($before, $after, 'phb:fighter', 4);

            expect($result->passed)->toBeFalse();
            expect($result->errors)->toContain('HP did not increase: was 25, now 25');
        });

        it('passes with HP choice pending', function () {
            $validator = new LevelUpValidator;

            $before = createSnapshot(totalLevel: 3, maxHp: 25, classLevels: ['phb:fighter' => 3]);
            $after = createSnapshot(
                totalLevel: 4,
                maxHp: 25,
                classLevels: ['phb:fighter' => 4],
                pendingChoices: [['type' => 'hp', 'required' => true, 'remaining' => 1]]
            );

            $result = $validator->validateLevelUp($before, $after, 'phb:fighter', 4);

            expect($result->passed)->toBeTrue();
            expect($result->warnings)->toContain('HP choice pending - HP not yet increased');
        });

        it('validates multiclass level up correctly', function () {
            $validator = new LevelUpValidator;

            $before = createSnapshot(
                totalLevel: 5,
                maxHp: 40,
                classLevels: ['phb:fighter' => 3, 'phb:rogue' => 2]
            );
            $after = createSnapshot(
                totalLevel: 6,
                maxHp: 47,
                classLevels: ['phb:fighter' => 3, 'phb:rogue' => 3]
            );

            $result = $validator->validateLevelUp($before, $after, 'phb:rogue', 6);

            expect($result->passed)->toBeTrue();
        });
    });

    describe('validateNoOrphanedChoices', function () {
        it('passes when no required choices remain', function () {
            $validator = new LevelUpValidator;

            $snapshot = createSnapshot(
                totalLevel: 4,
                maxHp: 30,
                classLevels: ['phb:fighter' => 4],
                pendingChoices: []
            );

            $result = $validator->validateNoOrphanedChoices($snapshot);

            expect($result->passed)->toBeTrue();
        });

        it('fails when required choices remain', function () {
            $validator = new LevelUpValidator;

            $snapshot = createSnapshot(
                totalLevel: 4,
                maxHp: 30,
                classLevels: ['phb:fighter' => 4],
                pendingChoices: [
                    ['type' => 'asi', 'required' => true, 'remaining' => 1],
                ]
            );

            $result = $validator->validateNoOrphanedChoices($snapshot);

            expect($result->passed)->toBeFalse();
            expect($result->errors)->toContain('Unresolved required choices: asi');
        });

        it('ignores optional choices', function () {
            $validator = new LevelUpValidator;

            $snapshot = createSnapshot(
                totalLevel: 4,
                maxHp: 30,
                classLevels: ['phb:fighter' => 4],
                pendingChoices: [
                    ['type' => 'spell', 'required' => false, 'remaining' => 2],
                ]
            );

            $result = $validator->validateNoOrphanedChoices($snapshot);

            expect($result->passed)->toBeTrue();
        });
    });
});

/**
 * Helper function to create snapshot arrays for testing.
 */
function createSnapshot(
    int $totalLevel,
    int $maxHp,
    array $classLevels,
    array $pendingChoices = [],
    array $abilityScores = [],
    array $features = [],
): array {
    $classes = [];
    foreach ($classLevels as $slug => $level) {
        $classes[] = [
            'class' => ['slug' => $slug],
            'level' => $level,
        ];
    }

    return [
        'character' => [
            'data' => [
                'total_level' => $totalLevel,
                'max_hit_points' => $maxHp,
                'classes' => $classes,
                'ability_scores' => $abilityScores,
            ],
        ],
        'features' => ['data' => $features],
        'pending_choices' => ['data' => ['choices' => $pendingChoices]],
        'level_up_derived' => [
            'total_level' => $totalLevel,
            'max_hp' => $maxHp,
            'class_levels' => $classLevels,
            'required_pending_count' => count(array_filter(
                $pendingChoices,
                fn ($c) => ($c['required'] ?? false) && ($c['remaining'] ?? 0) > 0
            )),
            'ability_score_totals' => $abilityScores,
            'feat_slugs' => [],
        ],
    ];
}

<?php

declare(strict_types=1);

use App\Services\LevelUpFlowTesting\LevelUpFlowExecutor;
use App\Services\WizardFlowTesting\CharacterRandomizer;

/**
 * Unit tests for LevelUpFlowExecutor.
 *
 * Note: Full integration tests are performed via the artisan command
 * `php artisan test:level-up-flow` which tests against real imported data.
 */
describe('LevelUpFlowExecutor', function () {
    it('can be instantiated', function () {
        $executor = new LevelUpFlowExecutor;

        expect($executor)->toBeInstanceOf(LevelUpFlowExecutor::class);
    });

    it('has execute method with correct signature', function () {
        $executor = new LevelUpFlowExecutor;
        $reflection = new ReflectionClass($executor);

        expect($reflection->hasMethod('execute'))->toBeTrue();

        $method = $reflection->getMethod('execute');
        $params = $method->getParameters();

        expect($params[0]->getName())->toBe('characterId');
        expect($params[1]->getName())->toBe('targetLevel');
        expect($params[2]->getName())->toBe('randomizer');
    });
});

describe('LevelUpFlowExecutor::selectAsiChoice', function () {
    it('returns correct format for ASI choice with ability score increase', function () {
        $executor = new LevelUpFlowExecutor;
        $randomizer = new CharacterRandomizer(42);

        // Mock options as returned by available-feats endpoint (empty = no feats available)
        // plus metadata containing ability scores
        $abilityScores = [
            ['code' => 'STR', 'name' => 'Strength', 'current_value' => 10],
            ['code' => 'DEX', 'name' => 'Dexterity', 'current_value' => 14],
            ['code' => 'CON', 'name' => 'Constitution', 'current_value' => 12],
            ['code' => 'INT', 'name' => 'Intelligence', 'current_value' => 8],
            ['code' => 'WIS', 'name' => 'Wisdom', 'current_value' => 15],
            ['code' => 'CHA', 'name' => 'Charisma', 'current_value' => 13],
        ];

        // Use reflection to call private method
        $reflection = new ReflectionClass($executor);
        $method = $reflection->getMethod('selectAsiChoice');
        $method->setAccessible(true);

        // Test with available feats (options) and ability scores (metadata)
        $options = []; // No feats available - should default to ASI
        $metadata = ['ability_scores' => $abilityScores, 'asi_points' => 2];

        $result = $method->invoke($executor, $options, $metadata, $randomizer);

        // Should return an array with 'type' and 'increases' keys
        expect($result)->toBeArray();
        expect($result)->toHaveKey('type');
        expect($result['type'])->toBe('asi');
        expect($result)->toHaveKey('increases');
        expect($result['increases'])->toBeArray();

        // Sum of increases should equal 2 (the asi_points)
        $totalIncrease = array_sum($result['increases']);
        expect($totalIncrease)->toBe(2);
    });

    it('can choose a feat when feats are available', function () {
        $executor = new LevelUpFlowExecutor;
        $randomizer = new CharacterRandomizer(99); // Different seed that might pick feat

        // Mock feat options
        $options = [
            ['slug' => 'phb:alert', 'name' => 'Alert'],
            ['slug' => 'phb:tough', 'name' => 'Tough'],
        ];
        $metadata = [
            'ability_scores' => [
                ['code' => 'STR', 'name' => 'Strength', 'current_value' => 10],
            ],
            'asi_points' => 2,
            'choice_options' => ['asi', 'feat'],
        ];

        $reflection = new ReflectionClass($executor);
        $method = $reflection->getMethod('selectAsiChoice');
        $method->setAccessible(true);

        $result = $method->invoke($executor, $options, $metadata, $randomizer);

        // Should return either ASI or feat format
        expect($result)->toBeArray();
        expect($result)->toHaveKey('type');
        expect($result['type'])->toBeIn(['asi', 'feat']);

        if ($result['type'] === 'feat') {
            expect($result)->toHaveKey('feat_slug');
            expect($result['feat_slug'])->toBeIn(['phb:alert', 'phb:tough']);
        } else {
            expect($result)->toHaveKey('increases');
        }
    });
});

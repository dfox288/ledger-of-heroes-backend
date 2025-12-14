<?php

declare(strict_types=1);

use App\Services\LevelUpFlowTesting\LevelUpFlowExecutor;

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

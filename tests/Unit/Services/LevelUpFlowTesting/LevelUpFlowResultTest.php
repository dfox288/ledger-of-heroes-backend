<?php

declare(strict_types=1);

use App\Services\LevelUpFlowTesting\LevelUpFlowResult;
use App\Services\LevelUpFlowTesting\LevelUpStepResult;

describe('LevelUpStepResult', function () {
    it('creates a successful step result', function () {
        $result = LevelUpStepResult::success(
            level: 2,
            classSlug: 'phb:fighter',
            hpGained: 7,
            featuresGained: ['phb:action-surge']
        );

        expect($result->passed)->toBeTrue();
        expect($result->level)->toBe(2);
        expect($result->classSlug)->toBe('phb:fighter');
        expect($result->hpGained)->toBe(7);
        expect($result->featuresGained)->toBe(['phb:action-surge']);
    });

    it('creates a failed step result', function () {
        $result = LevelUpStepResult::failure(
            level: 3,
            classSlug: 'phb:fighter',
            errors: ['HP did not increase'],
            pattern: 'hp_not_increased'
        );

        expect($result->passed)->toBeFalse();
        expect($result->level)->toBe(3);
        expect($result->errors)->toBe(['HP did not increase']);
        expect($result->pattern)->toBe('hp_not_increased');
    });

    it('converts to array', function () {
        $result = LevelUpStepResult::success(
            level: 4,
            classSlug: 'phb:fighter',
            hpGained: 8
        );

        $array = $result->toArray();

        expect($array['level'])->toBe(4);
        expect($array['class_slug'])->toBe('phb:fighter');
        expect($array['passed'])->toBeTrue();
        expect($array['hp_gained'])->toBe(8);
    });
});

describe('LevelUpFlowResult', function () {
    it('creates with character info', function () {
        $result = new LevelUpFlowResult(
            iteration: 1,
            seed: 12345,
            characterId: 42,
            publicId: 'test-char-1'
        );

        expect($result->getIteration())->toBe(1);
        expect($result->getSeed())->toBe(12345);
        expect($result->getCharacterId())->toBe(42);
        expect($result->getPublicId())->toBe('test-char-1');
    });

    it('tracks level-up steps', function () {
        $result = new LevelUpFlowResult(1, 12345, 42, 'test-char-1');

        $step = LevelUpStepResult::success(level: 2, classSlug: 'phb:fighter', hpGained: 7);
        $result->addStep($step);

        expect($result->getSteps())->toHaveCount(1);
        expect($result->getSteps()[0]->level)->toBe(2);
    });

    it('reports pass when all steps pass', function () {
        $result = new LevelUpFlowResult(1, 12345, 42, 'test-char-1');

        $result->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));
        $result->addStep(LevelUpStepResult::success(3, 'phb:fighter', 8));
        $result->addStep(LevelUpStepResult::success(4, 'phb:fighter', 6));

        expect($result->isPassed())->toBeTrue();
        expect($result->getStatus())->toBe('PASS');
    });

    it('reports fail when any step fails', function () {
        $result = new LevelUpFlowResult(1, 12345, 42, 'test-char-1');

        $result->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));
        $result->addStep(LevelUpStepResult::failure(3, 'phb:fighter', ['HP not increased']));
        $result->addStep(LevelUpStepResult::success(4, 'phb:fighter', 6));

        expect($result->isPassed())->toBeFalse();
        expect($result->getStatus())->toBe('FAIL');
        expect($result->getFailures())->toHaveCount(1);
    });

    it('reports error status when exception occurs', function () {
        $result = new LevelUpFlowResult(1, 12345, 42, 'test-char-1');

        $result->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));
        $result->setError(3, new RuntimeException('Connection failed'));

        expect($result->hasError())->toBeTrue();
        expect($result->getStatus())->toBe('ERROR');
    });

    it('calculates final level', function () {
        $result = new LevelUpFlowResult(1, 12345, 42, 'test-char-1');

        $result->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));
        $result->addStep(LevelUpStepResult::success(3, 'phb:fighter', 8));
        $result->addStep(LevelUpStepResult::success(4, 'phb:fighter', 6));

        expect($result->getFinalLevel())->toBe(4);
    });

    it('calculates total HP gained', function () {
        $result = new LevelUpFlowResult(1, 12345, 42, 'test-char-1');

        $result->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));
        $result->addStep(LevelUpStepResult::success(3, 'phb:fighter', 8));
        $result->addStep(LevelUpStepResult::success(4, 'phb:fighter', 6));

        expect($result->getTotalHpGained())->toBe(21);
    });

    it('generates summary string', function () {
        $result = new LevelUpFlowResult(1, 12345, 42, 'test-char-1');

        $result->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));
        $result->addStep(LevelUpStepResult::success(3, 'phb:fighter', 8));

        $summary = $result->getSummary();

        expect($summary)->toContain('test-char-1');
        expect($summary)->toContain('PASS');
        expect($summary)->toContain('level 3');
    });

    it('converts to array for reporting', function () {
        $result = new LevelUpFlowResult(1, 12345, 42, 'test-char-1');
        $result->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));

        $array = $result->toArray();

        expect($array['iteration'])->toBe(1);
        expect($array['seed'])->toBe(12345);
        expect($array['character_id'])->toBe(42);
        expect($array['public_id'])->toBe('test-char-1');
        expect($array['status'])->toBe('PASS');
        expect($array['final_level'])->toBe(2);
        expect($array['steps'])->toHaveCount(1);
    });
});

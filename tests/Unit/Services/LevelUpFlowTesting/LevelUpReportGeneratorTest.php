<?php

declare(strict_types=1);

use App\Services\LevelUpFlowTesting\LevelUpFlowResult;
use App\Services\LevelUpFlowTesting\LevelUpReportGenerator;
use App\Services\LevelUpFlowTesting\LevelUpStepResult;

describe('LevelUpReportGenerator', function () {
    describe('generate', function () {
        it('creates report with correct structure', function () {
            $generator = new LevelUpReportGenerator;

            $result1 = new LevelUpFlowResult(1, 12345, 1, 'test-char-1');
            $result1->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));

            $report = $generator->generate([$result1], 12345, ['target_level' => 20]);

            expect($report)->toHaveKeys([
                'run_id',
                'timestamp',
                'seed',
                'options',
                'iterations',
                'results',
                'summary',
            ]);
            expect($report['seed'])->toBe(12345);
            expect($report['options']['target_level'])->toBe(20);
        });

        it('calculates summary correctly for passing results', function () {
            $generator = new LevelUpReportGenerator;

            $result1 = new LevelUpFlowResult(1, 12345, 1, 'test-char-1');
            $result1->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));
            $result1->addStep(LevelUpStepResult::success(3, 'phb:fighter', 8));

            $result2 = new LevelUpFlowResult(2, 12346, 2, 'test-char-2');
            $result2->addStep(LevelUpStepResult::success(2, 'phb:rogue', 6));

            $report = $generator->generate([$result1, $result2], 12345);

            expect($report['summary']['total'])->toBe(2);
            expect($report['summary']['passed'])->toBe(2);
            expect($report['summary']['failed'])->toBe(0);
            expect($report['summary']['pass_rate'])->toBe(100.0);
        });

        it('tracks failures correctly', function () {
            $generator = new LevelUpReportGenerator;

            $result1 = new LevelUpFlowResult(1, 12345, 1, 'test-char-1');
            $result1->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));
            $result1->addStep(LevelUpStepResult::failure(3, 'phb:fighter', ['HP not increased'], 'hp_not_increased'));

            $report = $generator->generate([$result1], 12345);

            expect($report['summary']['passed'])->toBe(0);
            expect($report['summary']['failed'])->toBe(1);
            expect($report['summary']['failure_patterns'])->toHaveKey('hp_not_increased');
        });

        it('tracks errors correctly', function () {
            $generator = new LevelUpReportGenerator;

            $result1 = new LevelUpFlowResult(1, 12345, 1, 'test-char-1');
            $result1->setError(5, new RuntimeException('Connection failed'));

            $report = $generator->generate([$result1], 12345);

            expect($report['summary']['errors'])->toBe(1);
            expect($report['summary']['passed'])->toBe(0);
        });

        it('tracks level stats', function () {
            $generator = new LevelUpReportGenerator;

            $result1 = new LevelUpFlowResult(1, 12345, 1, 'test-char-1');
            $result1->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));
            $result1->addStep(LevelUpStepResult::success(3, 'phb:fighter', 8));
            $result1->addStep(LevelUpStepResult::success(4, 'phb:fighter', 6));

            $result2 = new LevelUpFlowResult(2, 12346, 2, 'test-char-2');
            $result2->addStep(LevelUpStepResult::success(2, 'phb:rogue', 5));
            $result2->addStep(LevelUpStepResult::success(3, 'phb:rogue', 6));

            $report = $generator->generate([$result1, $result2], 12345);

            expect($report['summary']['level_stats'])->toHaveKeys(['max_reached', 'avg_reached', 'total_levels_gained']);
            expect($report['summary']['level_stats']['max_reached'])->toBe(4);
        });
    });

    describe('consoleSummary', function () {
        it('generates readable console output', function () {
            $generator = new LevelUpReportGenerator;

            $result1 = new LevelUpFlowResult(1, 12345, 1, 'test-char-1');
            $result1->addStep(LevelUpStepResult::success(2, 'phb:fighter', 7));

            $report = $generator->generate([$result1], 12345);
            $lines = $generator->consoleSummary($report);

            expect($lines)->toBeArray();
            expect(implode("\n", $lines))->toContain('Run ID:');
            expect(implode("\n", $lines))->toContain('Passed:');
        });
    });
});

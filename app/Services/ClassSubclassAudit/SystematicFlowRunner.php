<?php

declare(strict_types=1);

namespace App\Services\ClassSubclassAudit;

use App\Models\CharacterClass;
use App\Services\LevelUpFlowTesting\LevelUpFlowExecutor;
use App\Services\WizardFlowTesting\CharacterRandomizer;
use App\Services\WizardFlowTesting\FlowExecutor;
use App\Services\WizardFlowTesting\FlowGenerator;
use Illuminate\Support\Collection;

/**
 * Runs wizard flow + level-up for every class/subclass combination systematically.
 *
 * Unlike the random chaos testing, this iterates through ALL combinations
 * to find specific class/subclass pairs that have issues.
 */
class SystematicFlowRunner
{
    private FlowGenerator $flowGenerator;

    private FlowExecutor $flowExecutor;

    private LevelUpFlowExecutor $levelUpExecutor;

    public function __construct()
    {
        $this->flowGenerator = new FlowGenerator;
        $this->flowExecutor = new FlowExecutor;
        $this->levelUpExecutor = new LevelUpFlowExecutor;
    }

    /**
     * Get all base classes to test (excluding sidekicks).
     *
     * @return Collection<CharacterClass>
     */
    public function getBaseClasses(): Collection
    {
        return CharacterClass::whereNull('parent_class_id')
            ->whereNotIn('slug', [
                'tce:expert-sidekick',
                'tce:spellcaster-sidekick',
                'tce:warrior-sidekick',
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all subclasses for a base class.
     *
     * @return Collection<CharacterClass>
     */
    public function getSubclasses(CharacterClass $baseClass): Collection
    {
        return CharacterClass::where('parent_class_id', $baseClass->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Run wizard flow for a specific class/subclass combination.
     *
     * @return array{passed: bool, character_id: int|null, public_id: string|null, errors: array}
     */
    public function runWizardFlow(
        CharacterClass $class,
        ?CharacterClass $subclass,
        int $seed
    ): array {
        $randomizer = new CharacterRandomizer($seed);

        // Generate linear flow with forced class/subclass
        $flow = $this->flowGenerator->linear();

        // Apply force_class and force_subclass to appropriate steps
        foreach ($flow as &$step) {
            if ($step['action'] === 'set_class') {
                $step['force_class'] = $class->slug;
            }
            if ($step['action'] === 'set_subclass' && $subclass) {
                $step['force_subclass'] = $subclass->slug;
            }
        }

        $result = $this->flowExecutor->execute($flow, $randomizer);

        // Collect error messages
        $errors = [];
        if ($result->getError()) {
            $errors[] = $result->getError()['message'];
        }
        foreach ($result->getFailures() as $failure) {
            $errors = array_merge($errors, $failure['errors'] ?? []);
        }

        return [
            'passed' => $result->isPassed(),
            'character_id' => $result->getCharacterId(),
            'public_id' => $result->getPublicId(),
            'errors' => $errors,
            'failures' => $result->getFailures(),
        ];
    }

    /**
     * Run level-up flow for an existing character.
     *
     * @return array{passed: bool, final_level: int, errors: array}
     */
    public function runLevelUpFlow(
        int $characterId,
        int $targetLevel,
        int $seed
    ): array {
        $randomizer = new CharacterRandomizer($seed);

        $result = $this->levelUpExecutor->execute(
            characterId: $characterId,
            targetLevel: $targetLevel,
            randomizer: $randomizer,
            iteration: 1,
            mode: 'linear'
        );

        // Collect error messages
        $errors = [];
        if ($result->hasError()) {
            $error = $result->toArray()['error'] ?? null;
            if ($error) {
                $errors[] = $error['message'];
            }
        }
        foreach ($result->getFailures() as $failure) {
            $errors = array_merge($errors, $failure->errors ?? []);
        }

        return [
            'passed' => $result->isPassed(),
            'final_level' => $result->getFinalLevel(),
            'errors' => $errors,
            'failures' => $result->getFailures(),
        ];
    }

    /**
     * Run complete test for a class/subclass combination.
     *
     * @return array{
     *   class: string,
     *   subclass: string|null,
     *   wizard_passed: bool,
     *   levelup_passed: bool,
     *   final_level: int,
     *   character_id: int|null,
     *   public_id: string|null,
     *   errors: array
     * }
     */
    public function runFullTest(
        CharacterClass $class,
        ?CharacterClass $subclass,
        int $targetLevel,
        int $seed
    ): array {
        $result = [
            'class' => $class->slug,
            'subclass' => $subclass?->slug,
            'wizard_passed' => false,
            'levelup_passed' => false,
            'final_level' => 0,
            'character_id' => null,
            'public_id' => null,
            'errors' => [],
        ];

        // Run wizard flow
        $wizardResult = $this->runWizardFlow($class, $subclass, $seed);
        $result['wizard_passed'] = $wizardResult['passed'];
        $result['character_id'] = $wizardResult['character_id'];
        $result['public_id'] = $wizardResult['public_id'];

        if (! $wizardResult['passed']) {
            $result['errors'] = array_merge(
                $result['errors'],
                array_map(fn ($e) => "Wizard: {$e}", $wizardResult['errors'])
            );

            return $result;
        }

        // Character created successfully, now level up
        if ($targetLevel > 1 && $wizardResult['character_id']) {
            $levelUpResult = $this->runLevelUpFlow(
                $wizardResult['character_id'],
                $targetLevel,
                $seed + 1000
            );

            $result['levelup_passed'] = $levelUpResult['passed'];
            $result['final_level'] = $levelUpResult['final_level'];

            if (! $levelUpResult['passed']) {
                $result['errors'] = array_merge(
                    $result['errors'],
                    array_map(fn ($e) => "LevelUp: {$e}", $levelUpResult['errors'])
                );
            }
        } else {
            $result['levelup_passed'] = true;
            $result['final_level'] = 1;
        }

        return $result;
    }

    /**
     * Run tests for all subclasses of a single base class.
     *
     * @return array<array>
     */
    public function runClassTests(
        CharacterClass $baseClass,
        int $targetLevel,
        int $baseSeed
    ): array {
        $results = [];
        $subclasses = $this->getSubclasses($baseClass);

        // Test base class without subclass if subclass_level > 1
        if ($baseClass->subclass_level !== 1) {
            $results[] = $this->runFullTest($baseClass, null, $targetLevel, $baseSeed);
        }

        // Test each subclass
        foreach ($subclasses as $index => $subclass) {
            $results[] = $this->runFullTest(
                $baseClass,
                $subclass,
                $targetLevel,
                $baseSeed + $index + 1
            );
        }

        return $results;
    }

    /**
     * Run tests for ALL classes and subclasses.
     *
     * @return array{
     *   results: array,
     *   summary: array{total: int, passed: int, failed: int}
     * }
     */
    public function runAllTests(int $targetLevel, int $baseSeed): array
    {
        $allResults = [];
        $baseClasses = $this->getBaseClasses();

        foreach ($baseClasses as $classIndex => $baseClass) {
            $classResults = $this->runClassTests(
                $baseClass,
                $targetLevel,
                $baseSeed + ($classIndex * 100)
            );

            $allResults = array_merge($allResults, $classResults);
        }

        $passed = count(array_filter($allResults, fn ($r) => $r['wizard_passed'] && $r['levelup_passed']));

        return [
            'results' => $allResults,
            'summary' => [
                'total' => count($allResults),
                'passed' => $passed,
                'failed' => count($allResults) - $passed,
            ],
        ];
    }
}

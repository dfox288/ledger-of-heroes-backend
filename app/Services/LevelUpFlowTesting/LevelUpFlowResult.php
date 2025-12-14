<?php

declare(strict_types=1);

namespace App\Services\LevelUpFlowTesting;

/**
 * Result of a complete level-up flow (1→target level).
 */
class LevelUpFlowResult
{
    /** @var LevelUpStepResult[] */
    private array $steps = [];

    private ?array $error = null;

    public function __construct(
        private readonly int $iteration,
        private readonly int $seed,
        private readonly int $characterId,
        private readonly string $publicId,
    ) {}

    public function getIteration(): int
    {
        return $this->iteration;
    }

    public function getSeed(): int
    {
        return $this->seed;
    }

    public function getCharacterId(): int
    {
        return $this->characterId;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    /**
     * Add a level-up step result.
     */
    public function addStep(LevelUpStepResult $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * Set an error that stopped execution.
     */
    public function setError(int $atLevel, \Throwable $exception): void
    {
        $this->error = [
            'at_level' => $atLevel,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    /**
     * @return LevelUpStepResult[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Get failed steps only.
     *
     * @return LevelUpStepResult[]
     */
    public function getFailures(): array
    {
        return array_filter($this->steps, fn ($s) => ! $s->passed);
    }

    public function isPassed(): bool
    {
        return empty($this->getFailures()) && $this->error === null;
    }

    public function hasFailed(): bool
    {
        return ! empty($this->getFailures());
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getStatus(): string
    {
        if ($this->error !== null) {
            return 'ERROR';
        }

        if ($this->hasFailed()) {
            return 'FAIL';
        }

        return 'PASS';
    }

    /**
     * Get the final level reached.
     */
    public function getFinalLevel(): int
    {
        if (empty($this->steps)) {
            return 1;
        }

        return max(array_map(fn ($s) => $s->level, $this->steps));
    }

    /**
     * Get total HP gained across all levels.
     */
    public function getTotalHpGained(): int
    {
        return array_sum(array_map(fn ($s) => $s->hpGained, $this->steps));
    }

    /**
     * Get a concise summary string.
     */
    public function getSummary(): string
    {
        $status = $this->getStatus();
        $finalLevel = $this->getFinalLevel();
        $stepCount = count($this->steps);

        $summary = "[{$status}] {$this->publicId} - {$stepCount} level-ups to level {$finalLevel}";

        if ($this->hasFailed()) {
            $failureCount = count($this->getFailures());
            $patterns = collect($this->getFailures())
                ->pluck('pattern')
                ->filter()
                ->unique()
                ->implode(', ');
            $summary .= " - {$failureCount} failures";
            if ($patterns) {
                $summary .= ": {$patterns}";
            }
        }

        if ($this->error) {
            $summary .= " - ERROR at level {$this->error['at_level']}: {$this->error['message']}";
        }

        return $summary;
    }

    /**
     * Convert to array for reporting.
     */
    public function toArray(): array
    {
        return [
            'iteration' => $this->iteration,
            'seed' => $this->seed,
            'character_id' => $this->characterId,
            'public_id' => $this->publicId,
            'status' => $this->getStatus(),
            'final_level' => $this->getFinalLevel(),
            'total_hp_gained' => $this->getTotalHpGained(),
            'steps' => array_map(fn ($s) => $s->toArray(), $this->steps),
            'failures' => array_map(fn ($s) => $s->toArray(), $this->getFailures()),
            'error' => $this->error,
        ];
    }
}

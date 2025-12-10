<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

/**
 * Result of executing a complete wizard flow.
 */
class FlowResult
{
    private array $steps = [];

    private array $failures = [];

    private ?array $error = null;

    private ?int $characterId = null;

    private ?string $publicId = null;

    private ?array $finalSnapshot = null;

    public function __construct(
        private readonly int $iteration = 1,
        private readonly int $seed = 0,
    ) {}

    public function setCharacter(int $characterId, string $publicId): void
    {
        $this->characterId = $characterId;
        $this->publicId = $publicId;
    }

    public function addStep(array $step, string $status, ?array $snapshot = null, ?array $response = null): void
    {
        $this->steps[] = [
            'action' => $step['action'],
            'description' => $step['description'] ?? '',
            'status' => $status,
            'target' => $step['target'] ?? null,
            'from' => $step['from'] ?? null,
            'to' => $step['to'] ?? null,
            'response_status' => $response['status'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($snapshot) {
            $this->finalSnapshot = $snapshot;
        }
    }

    public function addFailure(array $step, ValidationResult $validation, array $beforeSnapshot, array $afterSnapshot): void
    {
        $this->failures[] = [
            'step' => $step['action'],
            'description' => $step['description'] ?? '',
            'pattern' => $validation->pattern,
            'errors' => $validation->errors,
            'warnings' => $validation->warnings,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'diff' => StateSnapshot::diff($beforeSnapshot, $afterSnapshot),
        ];
    }

    public function addError(array $step, \Throwable $exception): void
    {
        $this->error = [
            'step' => $step['action'],
            'description' => $step['description'] ?? '',
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    public function isPassed(): bool
    {
        return empty($this->failures) && $this->error === null;
    }

    public function hasFailed(): bool
    {
        return ! empty($this->failures);
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getCharacterId(): ?int
    {
        return $this->characterId;
    }

    public function getPublicId(): ?string
    {
        return $this->publicId;
    }

    public function getFailures(): array
    {
        return $this->failures;
    }

    public function getError(): ?array
    {
        return $this->error;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getStatus(): string
    {
        if ($this->error !== null) {
            return 'ERROR';
        }

        if (! empty($this->failures)) {
            return 'FAIL';
        }

        return 'PASS';
    }

    public function toArray(): array
    {
        return [
            'iteration' => $this->iteration,
            'seed' => $this->seed,
            'character_id' => $this->characterId,
            'public_id' => $this->publicId,
            'status' => $this->getStatus(),
            'steps' => $this->steps,
            'failures' => array_map(function ($failure) {
                // Trim snapshots to avoid huge JSON
                return [
                    'step' => $failure['step'],
                    'description' => $failure['description'],
                    'pattern' => $failure['pattern'],
                    'errors' => $failure['errors'],
                    'warnings' => $failure['warnings'],
                    'diff' => $failure['diff'],
                    // Include full snapshots only for the derived fields
                    'before_derived' => $failure['before_snapshot']['derived'] ?? [],
                    'after_derived' => $failure['after_snapshot']['derived'] ?? [],
                ];
            }, $this->failures),
            'error' => $this->error,
            'final_state' => $this->finalSnapshot['derived'] ?? null,
        ];
    }

    /**
     * Get a concise summary suitable for console output.
     */
    public function getSummary(): string
    {
        $status = $this->getStatus();
        $stepCount = count($this->steps);
        $failureCount = count($this->failures);

        $summary = "[{$status}] {$this->publicId} - {$stepCount} steps";

        if ($failureCount > 0) {
            $patterns = collect($this->failures)
                ->pluck('pattern')
                ->filter()
                ->unique()
                ->implode(', ');
            $summary .= " - {$failureCount} failures: {$patterns}";
        }

        if ($this->error) {
            $summary .= " - ERROR at {$this->error['step']}: {$this->error['message']}";
        }

        return $summary;
    }
}

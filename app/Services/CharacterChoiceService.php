<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PendingChoice;
use App\Exceptions\ChoiceNotFoundException;
use App\Exceptions\ChoiceNotUndoableException;
use App\Models\Character;
use App\Services\ChoiceHandlers\ChoiceTypeHandler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CharacterChoiceService
{
    /** @var array<string, ChoiceTypeHandler> */
    private array $handlers = [];

    /**
     * Register a choice type handler.
     */
    public function registerHandler(ChoiceTypeHandler $handler): void
    {
        $this->handlers[$handler->getType()] = $handler;
    }

    /**
     * Get all pending choices for a character.
     *
     * @return Collection<int, PendingChoice>
     */
    public function getPendingChoices(Character $character, ?string $type = null): Collection
    {
        $choices = collect();

        $handlersToQuery = $type !== null
            ? array_filter([$this->handlers[$type] ?? null])
            : array_values($this->handlers);

        foreach ($handlersToQuery as $handler) {
            $choices = $choices->merge($handler->getChoices($character));
        }

        return $choices->values();
    }

    /**
     * Get a specific choice by ID.
     *
     * @throws ChoiceNotFoundException
     */
    public function getChoice(Character $character, string $choiceId): PendingChoice
    {
        $type = $this->parseChoiceType($choiceId);
        $handler = $this->handlers[$type] ?? null;

        if (! $handler) {
            throw new ChoiceNotFoundException($choiceId, "Unknown choice type: {$type}");
        }

        $choices = $handler->getChoices($character);
        $choice = $choices->first(fn (PendingChoice $c) => $c->id === $choiceId);

        if (! $choice) {
            throw new ChoiceNotFoundException($choiceId);
        }

        return $choice;
    }

    /**
     * Resolve a choice with the given selection.
     *
     * Wrapped in a database transaction to prevent race conditions
     * when concurrent requests try to resolve the same choice.
     *
     * @throws ChoiceNotFoundException
     */
    public function resolveChoice(Character $character, string $choiceId, array $selection): void
    {
        DB::transaction(function () use ($character, $choiceId, $selection) {
            $choice = $this->getChoice($character, $choiceId);
            $handler = $this->handlers[$choice->type];
            $handler->resolve($character, $choice, $selection);
        });
    }

    /**
     * Check if a choice can be undone.
     *
     * @throws ChoiceNotFoundException
     */
    public function canUndoChoice(Character $character, string $choiceId): bool
    {
        $choice = $this->getChoice($character, $choiceId);
        $handler = $this->handlers[$choice->type];

        return $handler->canUndo($character, $choice);
    }

    /**
     * Undo a previously resolved choice.
     *
     * @throws ChoiceNotFoundException
     * @throws ChoiceNotUndoableException
     */
    public function undoChoice(Character $character, string $choiceId): void
    {
        $choice = $this->getChoice($character, $choiceId);
        $handler = $this->handlers[$choice->type];

        if (! $handler->canUndo($character, $choice)) {
            throw new ChoiceNotUndoableException($choiceId);
        }

        $handler->undo($character, $choice);
    }

    /**
     * Get summary of pending choices.
     *
     * @return array{total_pending: int, required_pending: int, optional_pending: int, by_type: array<string, int>, by_source: array<string, int>}
     */
    public function getSummary(Character $character): array
    {
        $choices = $this->getPendingChoices($character);
        $pending = $choices->filter(fn (PendingChoice $c) => $c->remaining > 0);

        return [
            'total_pending' => $pending->count(),
            'required_pending' => $pending->filter(fn (PendingChoice $c) => $c->required)->count(),
            'optional_pending' => $pending->filter(fn (PendingChoice $c) => ! $c->required)->count(),
            'by_type' => $pending->groupBy('type')->map->count()->toArray(),
            'by_source' => $pending->groupBy('source')->map->count()->toArray(),
        ];
    }

    /**
     * Get all registered handler types.
     *
     * @return array<string>
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Parse the choice type from a choice ID.
     *
     * Choice ID format: {type}|{source}|{sourceSlug}|{level}|{group}
     * Uses pipe separator to avoid conflicts with colons in slug values (e.g., 'phb:wizard').
     */
    private function parseChoiceType(string $choiceId): string
    {
        $parts = explode('|', $choiceId);

        return $parts[0] ?? '';
    }
}

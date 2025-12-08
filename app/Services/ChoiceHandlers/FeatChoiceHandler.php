<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\Feat;
use App\Services\FeatChoiceService;
use Illuminate\Support\Collection;

/**
 * Handler for bonus feat choices granted by race or background.
 *
 * This is separate from asi_or_feat which handles the ASI/feat choice at level 4, 8, etc.
 * This handler is for races like Variant Human that grant a free feat at character creation.
 *
 * Feats are detected via modifiers with category 'bonus_feat' on the race/background entity.
 */
class FeatChoiceHandler extends AbstractChoiceHandler
{
    public function __construct(
        private readonly FeatChoiceService $featService
    ) {}

    public function getType(): string
    {
        return 'feat';
    }

    public function getChoices(Character $character): Collection
    {
        $pendingChoices = $this->featService->getPendingChoices($character);
        $choices = collect();

        foreach ($pendingChoices as $source => $choiceData) {
            if ($choiceData['remaining'] <= 0) {
                continue;
            }

            $sourceSlug = $this->getSourceSlug($character, $source);
            if ($sourceSlug === '') {
                continue;
            }

            $sourceName = $this->getSourceName($character, $source);

            // For feats, we don't include inline options (too many)
            // Frontend should use the /api/v1/feats endpoint
            $choice = new PendingChoice(
                id: $this->generateChoiceId('feat', $source, $sourceSlug, 1, 'bonus_feat'),
                type: 'feat',
                subtype: null,
                source: $source,
                sourceName: $sourceName,
                levelGranted: 1,
                required: true, // Bonus feats from race/background are required choices
                quantity: $choiceData['quantity'],
                remaining: $choiceData['remaining'],
                selected: $choiceData['selected'],
                options: null, // Use endpoint instead
                optionsEndpoint: '/api/v1/feats',
                metadata: [
                    'note' => 'Bonus feat granted by '.$sourceName,
                ],
            );

            $choices->push($choice);
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $source = $parsed['source'];

        $featSlug = $selection['feat_slug'] ?? $selection['selected'][0] ?? null;
        if (empty($featSlug)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Feat slug is required');
        }

        $this->featService->makeChoice($character, $source, $featSlug);
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // Feat choices can be undone during character creation
        return true;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $source = $parsed['source'];

        // Get the last selected feat for this source and undo it
        $selected = $choice->selected;
        if (! empty($selected)) {
            $lastFeatSlug = end($selected);
            $this->featService->undoChoice($character, $source, $lastFeatSlug);
        }
    }

    private function getSourceSlug(Character $character, string $source): string
    {
        return match ($source) {
            'race' => $character->race?->full_slug ?? '',
            'background' => $character->background?->full_slug ?? '',
            default => '',
        };
    }

    private function getSourceName(Character $character, string $source): string
    {
        return match ($source) {
            'race' => $character->race?->name ?? 'Unknown Race',
            'background' => $character->background?->name ?? 'Unknown Background',
            default => 'Unknown',
        };
    }
}

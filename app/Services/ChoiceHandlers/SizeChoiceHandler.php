<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\Size;
use Illuminate\Support\Collection;

/**
 * Handler for size choices granted by races like Custom Lineage.
 *
 * Some races allow the player to choose between Small and Medium size.
 * This handler detects races with has_size_choice=true and generates
 * the appropriate pending choice.
 */
class SizeChoiceHandler extends AbstractChoiceHandler
{
    public function getType(): string
    {
        return 'size';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        // Check if race has size choice
        $race = $character->race;
        if (! $race || ! $race->has_size_choice) {
            return $choices;
        }

        // Check if already selected (character has size_id set)
        $isSelected = $character->size_id !== null;

        // Get Small and Medium size options
        $sizes = Size::whereIn('code', ['S', 'M'])->get();
        $options = $sizes->map(fn (Size $size) => [
            'code' => $size->code,
            'name' => $size->name,
            'id' => $size->id,
        ])->values()->all();

        $selected = [];
        if ($isSelected) {
            $chosenSize = $character->sizeChoice;
            if ($chosenSize) {
                $selected = [$chosenSize->code];
            }
        }

        $choice = new PendingChoice(
            id: $this->generateChoiceId('size', 'race', $race->full_slug, 1, 'size_choice'),
            type: 'size',
            subtype: null,
            source: 'race',
            sourceName: $race->name,
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: $isSelected ? 0 : 1,
            selected: $selected,
            options: $options,
            optionsEndpoint: null,
            metadata: [
                'note' => 'You are Small or Medium (your choice).',
            ],
        );

        $choices->push($choice);

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $sizeCode = $selection['selected'][0] ?? $selection['size_code'] ?? null;

        if (empty($sizeCode)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Size code is required');
        }

        // Validate size code
        if (! in_array($sizeCode, ['S', 'M'], true)) {
            throw new InvalidSelectionException($choice->id, $sizeCode, 'Size must be S (Small) or M (Medium)');
        }

        // Find the size
        $size = Size::where('code', $sizeCode)->first();
        if (! $size) {
            throw new InvalidSelectionException($choice->id, $sizeCode, "Size not found: {$sizeCode}");
        }

        // Update character's size
        $character->size_id = $size->id;
        $character->save();
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // Size choice can be undone during character creation
        return true;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        // Clear the size choice
        $character->size_id = null;
        $character->save();
    }
}

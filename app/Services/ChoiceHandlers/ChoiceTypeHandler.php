<?php

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Models\Character;
use Illuminate\Support\Collection;

interface ChoiceTypeHandler
{
    /**
     * Get the choice type this handler manages.
     *
     * @return string One of: proficiency, language, equipment, spell, asi_or_feat, subclass, optional_feature, expertise, fighting_style
     */
    public function getType(): string;

    /**
     * Get all pending choices of this type for a character.
     *
     * @return Collection<int, PendingChoice>
     */
    public function getChoices(Character $character): Collection;

    /**
     * Resolve a choice with the given selection.
     *
     * @param  array  $selection  The user's selection (format varies by type)
     *
     * @throws \App\Exceptions\InvalidChoiceException
     * @throws \App\Exceptions\InvalidSelectionException
     */
    public function resolve(Character $character, PendingChoice $choice, array $selection): void;

    /**
     * Check if a resolved choice can be undone.
     */
    public function canUndo(Character $character, PendingChoice $choice): bool;

    /**
     * Undo a previously resolved choice.
     *
     * @throws \App\Exceptions\ChoiceNotUndoableException
     */
    public function undo(Character $character, PendingChoice $choice): void;
}

<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\ChoiceNotUndoableException;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use Illuminate\Support\Collection;

class HitPointRollChoiceHandler extends AbstractChoiceHandler
{
    public function getType(): string
    {
        return 'hit_points';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        // Get primary class
        $primaryClass = $character->primary_class;
        if (! $primaryClass) {
            return $choices;
        }

        // Get character's class pivot to check level
        $classPivot = $character->characterClasses->firstWhere('is_primary', true);
        if (! $classPivot) {
            return $choices;
        }

        $level = $classPivot->level;

        // No HP choice at level 1 (automatic max HP)
        if ($level <= 1) {
            return $choices;
        }

        // Check if character has pending HP for current level
        // This would be set by level-up system
        // For now, we assume there's a pending choice if:
        // - Character is level 2+ in their primary class
        // - max_hit_points hasn't been set for this level yet
        // In production, this would check a pending_hp_level column or similar

        // For this implementation, we'll assume any level > 1 has a pending HP choice
        // The actual integration with level-up system will determine when to show this
        if ($this->hasPendingHpChoice($character, $level)) {
            $conModifier = $this->calculateConModifier($character->constitution ?? 10);
            $hitDie = $primaryClass->effective_hit_die;
            $average = (int) floor($hitDie / 2) + 1;

            // Calculate min/max results for roll
            $minRoll = max(1, 1 + $conModifier); // Minimum is always 1 HP
            $maxRoll = max(1, $hitDie + $conModifier);

            // Calculate fixed result for average
            $averageResult = max(1, $average + $conModifier);

            $choice = new PendingChoice(
                id: $this->generateChoiceId('hit_points', 'levelup', $character->id, $level, 'hp'),
                type: 'hit_points',
                subtype: null,
                source: 'level_up',
                sourceName: "Level {$level}",
                levelGranted: $level,
                required: true,
                quantity: 1,
                remaining: 1,
                selected: [],
                options: [
                    [
                        'id' => 'roll',
                        'name' => 'Roll',
                        'description' => "Roll 1d{$hitDie}".($conModifier >= 0 ? ' + ' : ' - ').abs($conModifier).' (CON mod)',
                        'min_result' => $minRoll,
                        'max_result' => $maxRoll,
                    ],
                    [
                        'id' => 'average',
                        'name' => 'Average',
                        'description' => "Take {$average}".($conModifier >= 0 ? ' + ' : ' - ').abs($conModifier)." (CON mod) = {$averageResult} HP",
                        'fixed_result' => $averageResult,
                    ],
                ],
                optionsEndpoint: null,
                metadata: [
                    'hit_die' => "d{$hitDie}",
                    'con_modifier' => $conModifier,
                    'class_slug' => $primaryClass->slug,
                ],
            );

            $choices->push($choice);
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $selected = $selection['selected'] ?? null;

        if (! $selected) {
            throw new InvalidSelectionException($choice->id, 'null', 'Selection is required for hit point choice');
        }

        if (! in_array($selected, ['roll', 'average'])) {
            throw new InvalidSelectionException($choice->id, $selected, 'Selection must be "roll" or "average"');
        }

        $conModifier = $choice->metadata['con_modifier'] ?? 0;
        $hitDieString = $choice->metadata['hit_die'] ?? 'd8';
        $hitDie = (int) str_replace('d', '', $hitDieString);

        if ($selected === 'roll') {
            // Server-side roll - NEVER trust client
            $roll = random_int(1, $hitDie);
            $hpGained = max(1, $roll + $conModifier);
        } else {
            // Average
            $average = (int) floor($hitDie / 2) + 1;
            $hpGained = max(1, $average + $conModifier);
        }

        // Update character HP
        $character->max_hit_points = ($character->max_hit_points ?? 0) + $hpGained;
        $character->current_hit_points = ($character->current_hit_points ?? 0) + $hpGained;
        $character->save();
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // Hit point choices are permanent
        return false;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        throw new ChoiceNotUndoableException(
            $choice->id,
            'Hit point choices are permanent'
        );
    }

    /**
     * Calculate ability modifier from ability score
     */
    private function calculateConModifier(int $constitution): int
    {
        return (int) floor(($constitution - 10) / 2);
    }

    /**
     * Check if character has a pending HP choice for the given level
     *
     * For now, this always returns true for levels > 1
     * In production, this would check against tracked HP choices or a pending_hp_level column
     */
    private function hasPendingHpChoice(Character $character, int $level): bool
    {
        // Simple implementation: assume pending if level > 1
        // Real implementation would track which levels have had HP choices resolved
        // This could be via:
        // - A pending_hp_level column on characters table
        // - A separate hp_choices table tracking resolved levels
        // - Comparing expected HP to actual HP based on level

        return $level > 1;
    }
}

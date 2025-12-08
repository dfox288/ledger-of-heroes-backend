<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\ChoiceNotUndoableException;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Services\HitPointService;
use Illuminate\Support\Collection;

class HitPointRollChoiceHandler extends AbstractChoiceHandler
{
    private HitPointService $hitPointService;

    public function __construct(?HitPointService $hitPointService = null)
    {
        $this->hitPointService = $hitPointService ?? app(HitPointService::class);
    }

    /**
     * Level 1 HP is automatic (max hit die + CON modifier), no choice needed.
     */
    private const AUTOMATIC_HP_LEVEL = 1;

    public function getType(): string
    {
        return 'hit_points';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        // Get all pending HP levels
        $pendingLevels = $character->getPendingHpLevels();

        // Filter to only levels 2+ (level 1 is auto-set)
        $pendingLevels = array_filter($pendingLevels, fn ($level) => $level > 1);

        if (empty($pendingLevels)) {
            return $choices;
        }

        // Get primary class for hit die (used for all single-class characters)
        // For multiclass, we'll use the class that granted each level
        $primaryClass = $character->primary_class;
        if (! $primaryClass) {
            return $choices;
        }

        $conModifier = $this->calculateConModifier($character->constitution ?? 10);

        // Generate a choice for each pending level
        foreach ($pendingLevels as $level) {
            // For multiclass support, we'd need to determine which class granted each level
            // For now, use primary class hit die (this works for single-class characters)
            // TODO: Support multiclass by tracking which class each level belongs to
            $hitDie = $primaryClass->effective_hit_die;
            $classSlug = $primaryClass->slug;

            $average = (int) floor($hitDie / 2) + 1;

            // Calculate min/max results for roll
            $minRoll = max(1, 1 + $conModifier); // Minimum is always 1 HP
            $maxRoll = max(1, $hitDie + $conModifier);

            // Calculate fixed result for average
            $averageResult = max(1, $average + $conModifier);

            $choice = new PendingChoice(
                id: $this->generateChoiceId('hit_points', 'levelup', (string) $character->id, $level, 'hp'),
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
                    [
                        'id' => 'manual',
                        'name' => 'Manual Roll',
                        'description' => "Enter your own d{$hitDie} roll result (for physical dice)",
                        'min_roll' => 1,
                        'max_roll' => $hitDie,
                    ],
                ],
                optionsEndpoint: null,
                metadata: [
                    'hit_die' => "d{$hitDie}",
                    'con_modifier' => $conModifier,
                    'class_slug' => $classSlug,
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

        if (! in_array($selected, ['roll', 'average', 'manual'])) {
            throw new InvalidSelectionException($choice->id, $selected, 'Selection must be "roll", "average", or "manual"');
        }

        $conModifier = $choice->metadata['con_modifier'] ?? 0;
        $hitDieString = $choice->metadata['hit_die'] ?? 'd8';
        $hitDie = (int) str_replace('d', '', $hitDieString);

        if ($selected === 'manual') {
            $rollResult = $selection['roll_result'] ?? null;

            if ($rollResult === null) {
                throw new InvalidSelectionException(
                    $choice->id,
                    'manual',
                    'roll_result is required when using manual selection'
                );
            }

            // Strict integer validation - reject floats and non-integer strings
            if (is_int($rollResult)) {
                // Already an integer, good
            } elseif (is_string($rollResult) && ctype_digit($rollResult)) {
                // String containing only digits (e.g., "7"), cast to int
                $rollResult = (int) $rollResult;
            } else {
                // Reject floats (7.5), float strings ("7.5"), and non-numeric values
                throw new InvalidSelectionException(
                    $choice->id,
                    'manual',
                    'roll_result must be an integer'
                );
            }

            if ($rollResult < 1 || $rollResult > $hitDie) {
                throw new InvalidSelectionException(
                    $choice->id,
                    'manual',
                    "roll_result must be between 1 and {$hitDie}"
                );
            }

            $hpGained = max(1, $rollResult + $conModifier);
        } elseif ($selected === 'roll') {
            // Server-side roll - NEVER trust client
            $roll = random_int(1, $hitDie);
            $hpGained = max(1, $roll + $conModifier);
        } else {
            // Average
            $average = (int) floor($hitDie / 2) + 1;
            $hpGained = max(1, $average + $conModifier);
        }

        // Add feat HP bonus (e.g., Tough feat grants +2 HP per level)
        $featHpBonus = $this->hitPointService->getFeatHpBonus($character);
        $hpGained += $featHpBonus;

        // Add race HP bonus (e.g., Hill Dwarf Dwarven Toughness grants +1 HP per level)
        $raceHpBonus = $this->hitPointService->getRaceHpBonus($character);
        $hpGained += $raceHpBonus;

        // Update character HP
        $character->max_hit_points = ($character->max_hit_points ?? 0) + $hpGained;
        $character->current_hit_points = ($character->current_hit_points ?? 0) + $hpGained;

        // Mark this level's HP as resolved
        $resolvedLevels = $character->hp_levels_resolved ?? [];
        $resolvedLevels[] = $choice->levelGranted;
        $character->hp_levels_resolved = array_unique($resolvedLevels);

        $character->save();

        // Mark this level's HP as resolved
        $character->markHpResolvedForLevel($choice->levelGranted);
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
     * Check if character has a pending HP choice for the given level.
     * Level 1 HP is auto-set when first class is added, so no choice needed.
     */
    private function hasPendingHpChoice(Character $character, int $level): bool
    {
        // Level 1 HP is automatic (hit die max + CON)
        if ($level <= self::AUTOMATIC_HP_LEVEL) {
            return false;
        }

        // Check if this level's HP has been resolved (uses model helper)
        return ! $character->hasResolvedHpForLevel($level);
    }
}

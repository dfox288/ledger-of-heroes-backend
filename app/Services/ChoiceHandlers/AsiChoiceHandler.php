<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\ChoiceNotUndoableException;
use App\Exceptions\InvalidSelectionException;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\Feat;
use App\Services\AsiChoiceService;
use Illuminate\Support\Collection;

class AsiChoiceHandler extends AbstractChoiceHandler
{
    public function __construct(
        private readonly AsiChoiceService $asiService
    ) {}

    public function getType(): string
    {
        return 'asi_or_feat';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        // Check if character has any pending ASI choices
        $remaining = $character->asi_choices_remaining ?? 0;

        if ($remaining <= 0) {
            return $choices;
        }

        // Get primary class for source info
        $primaryClass = $character->primary_class;
        if (! $primaryClass) {
            return $choices;
        }

        // Get available feats
        $feats = Feat::orderBy('name')
            ->get()
            ->map(fn ($feat) => [
                'full_slug' => $feat->full_slug,
                'slug' => $feat->slug,
                'name' => $feat->name,
            ])
            ->values()
            ->all();

        // Get ability scores for ASI
        $abilityScores = AbilityScore::orderBy('id')
            ->get()
            ->map(fn ($as) => [
                'code' => $as->code,
                'name' => $as->name,
                'current_value' => $character->{strtolower($as->code)} ?? 10,
            ])
            ->values()
            ->all();

        // Create one choice per remaining ASI
        // ASI levels for standard classes: 4, 8, 12, 16, 19
        $asiLevels = [4, 8, 12, 16, 19];
        $usedCount = $this->countUsedAsiChoices($character);

        for ($i = 0; $i < $remaining; $i++) {
            $asiIndex = $usedCount + $i;
            $level = $asiLevels[$asiIndex] ?? ($asiIndex + 1) * 4;

            $choice = new PendingChoice(
                id: $this->generateChoiceId('asi_or_feat', 'class', $primaryClass->full_slug, $level, 'asi_'.($asiIndex + 1)),
                type: 'asi_or_feat',
                subtype: null,
                source: 'class',
                sourceName: $primaryClass->name,
                levelGranted: $level,
                required: false, // ASI is optional (player can delay)
                quantity: 1,
                remaining: 1,
                selected: [],
                options: null, // Options are complex, use metadata instead
                optionsEndpoint: '/api/v1/feats',
                metadata: [
                    'choice_options' => ['asi', 'feat'],
                    'ability_scores' => $abilityScores,
                    'available_feats_count' => count($feats),
                    'asi_points' => 2, // Standard is +2 to one or +1 to two
                    'max_ability_score' => 20,
                ],
            );

            $choices->push($choice);
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $type = $selection['type'] ?? null;

        if ($type === 'feat') {
            $featSlug = $selection['feat_slug'] ?? null;
            if (! $featSlug) {
                throw new InvalidSelectionException($choice->id, 'null', 'Feat slug is required when type is feat');
            }

            // Load the feat model by slug
            $feat = Feat::where('full_slug', $featSlug)
                ->orWhere('slug', $featSlug)
                ->firstOrFail();
            $this->asiService->applyFeatChoice($character, $feat);
        } elseif ($type === 'asi') {
            $increases = $selection['increases'] ?? [];
            if (empty($increases)) {
                throw new InvalidSelectionException($choice->id, 'empty', 'Ability increases are required when type is asi');
            }
            $this->asiService->applyAbilityIncrease($character, $increases);
        } else {
            throw new InvalidSelectionException($choice->id, $type ?? 'null', 'Type must be "asi" or "feat"');
        }
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // ASI/Feat choices are permanent for now
        // Could be made undoable in the future with more complex logic
        return false;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        throw new ChoiceNotUndoableException(
            $choice->id,
            'ASI and feat choices cannot be undone'
        );
    }

    /**
     * Count how many ASI choices have been used (for determining which ASI level we're on)
     */
    private function countUsedAsiChoices(Character $character): int
    {
        // Total possible ASIs depends on character level
        $level = $character->total_level ?? 1;
        $asiLevels = [4, 8, 12, 16, 19];

        $possibleAsis = count(array_filter($asiLevels, fn ($l) => $l <= $level));
        $remaining = $character->asi_choices_remaining ?? 0;

        return max(0, $possibleAsis - $remaining);
    }
}

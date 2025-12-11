<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\LevelUpResult;
use App\Exceptions\IncompleteCharacterException;
use App\Exceptions\MaxLevelReachedException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterFeature;
use App\Models\ClassFeature;
use Illuminate\Support\Facades\DB;

class LevelUpService
{
    private const MAX_LEVEL = 20;

    private const ASI_LEVELS_STANDARD = [4, 8, 12, 16, 19];

    private const ASI_LEVELS_FIGHTER = [4, 6, 8, 12, 14, 16, 19];

    private const ASI_LEVELS_ROGUE = [4, 8, 10, 12, 16, 19];

    private CharacterStatCalculator $calculator;

    private SpellSlotService $spellSlotService;

    private CharacterChoiceService $choiceService;

    public function __construct(
        ?CharacterStatCalculator $calculator = null,
        ?SpellSlotService $spellSlotService = null,
        ?CharacterChoiceService $choiceService = null
    ) {
        $this->calculator = $calculator ?? new CharacterStatCalculator;
        $this->spellSlotService = $spellSlotService ?? app(SpellSlotService::class);
        $this->choiceService = $choiceService ?? app(CharacterChoiceService::class);
    }

    /**
     * Level up a character by one level.
     *
     * @param  string|null  $classSlug  Optional class to level (for multiclass). Defaults to primary class.
     *
     * @throws MaxLevelReachedException
     * @throws IncompleteCharacterException
     */
    public function levelUp(Character $character, ?string $classSlug = null): LevelUpResult
    {
        $this->validateCanLevelUp($character);

        return DB::transaction(function () use ($character, $classSlug) {
            // Get the class pivot to level up
            $classPivot = $this->getClassPivotToLevel($character, $classSlug);
            $class = $classPivot->characterClass;

            $previousLevel = $character->total_level;

            // Increment level on the class pivot
            $classPivot->increment('level');
            $character->refresh();

            $newLevel = $character->total_level;
            $classLevel = $classPivot->level;

            // Grant class features for the new class level
            $featuresGained = $this->grantClassFeatures($character, $class, $classLevel);

            // Check if this class level grants an ASI
            $asiPending = $this->isAsiLevel($class->slug ?? '', $classLevel);

            if ($asiPending) {
                $character->asi_choices_remaining++;
                $character->save();
            }

            // Recalculate spell slots when leveling up
            $this->spellSlotService->recalculateMaxSlots($character);

            $spellSlots = $this->getSpellSlots($character);

            // Get pending choice summary after level-up
            $pendingChoiceSummary = $this->choiceService->getSummary($character);

            // HP is NOT modified here - it's handled by HitPointRollChoiceHandler
            return new LevelUpResult(
                previousLevel: $previousLevel,
                newLevel: $newLevel,
                hpIncrease: 0,
                newMaxHp: $character->max_hit_points ?? 0,
                featuresGained: $featuresGained,
                spellSlots: $spellSlots,
                asiPending: $asiPending,
                pendingChoiceSummary: $pendingChoiceSummary,
            );
        });
    }

    /**
     * Get the class pivot to level up.
     *
     * If classSlug is provided, finds that class. Otherwise uses primary class.
     */
    private function getClassPivotToLevel(Character $character, ?string $classSlug): CharacterClassPivot
    {
        if ($classSlug !== null) {
            $pivot = $character->characterClasses()->where('class_slug', $classSlug)->first();
            if ($pivot === null) {
                throw new \InvalidArgumentException("Character does not have class: {$classSlug}");
            }

            return $pivot;
        }

        // Default to primary class
        $pivot = $character->characterClasses()->where('is_primary', true)->first();
        if ($pivot === null) {
            throw new \InvalidArgumentException('Character has no primary class');
        }

        return $pivot;
    }

    /**
     * Grant class features for the new level.
     *
     * Uses firstOrCreate to prevent duplicate features if level-up is called multiple times.
     *
     * @return array<array{id: int, name: string, description: string|null}>
     */
    private function grantClassFeatures(Character $character, ?CharacterClass $class, int $classLevel): array
    {
        if ($class === null) {
            return [];
        }

        $features = $class->features()
            ->where('level', $classLevel)
            ->where('is_optional', false)
            ->get();

        $granted = [];
        foreach ($features as $feature) {
            CharacterFeature::firstOrCreate(
                [
                    'character_id' => $character->id,
                    'feature_type' => ClassFeature::class,
                    'feature_id' => $feature->id,
                    'source' => 'class',
                ],
                [
                    'level_acquired' => $classLevel,
                ]
            );

            $granted[] = [
                'id' => $feature->id,
                'name' => $feature->feature_name,
                'description' => $feature->description,
            ];
        }

        return $granted;
    }

    /**
     * Check if the class level grants an Ability Score Improvement.
     *
     * ASI levels vary by class (Fighter and Rogue get extra ASIs).
     */
    private function isAsiLevel(string $classSlug, int $classLevel): bool
    {
        if (empty($classSlug)) {
            return in_array($classLevel, self::ASI_LEVELS_STANDARD);
        }

        $slug = strtolower($classSlug);

        $asiLevels = match ($slug) {
            'fighter' => self::ASI_LEVELS_FIGHTER,
            'rogue' => self::ASI_LEVELS_ROGUE,
            default => self::ASI_LEVELS_STANDARD,
        };

        return in_array($classLevel, $asiLevels);
    }

    /**
     * Get current spell slots for the character.
     *
     * @return array<int, int>
     */
    private function getSpellSlots(Character $character): array
    {
        $primaryClass = $character->primaryClass;
        $classSlug = $primaryClass->slug ?? '';

        return $this->calculator->getSpellSlots($classSlug, $character->total_level);
    }

    /**
     * Validate that the character can level up.
     *
     * @throws MaxLevelReachedException
     * @throws IncompleteCharacterException
     */
    private function validateCanLevelUp(Character $character): void
    {
        if ($character->total_level >= self::MAX_LEVEL) {
            throw new MaxLevelReachedException($character);
        }

        if (! $character->is_complete) {
            throw new IncompleteCharacterException($character);
        }
    }
}

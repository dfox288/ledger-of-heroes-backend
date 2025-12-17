<?php

namespace App\Http\Controllers\Api;

use App\DTOs\XpProgressResult;
use App\Enums\LevelingMode;
use App\Exceptions\IncompleteCharacterException;
use App\Exceptions\MaxLevelReachedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\AddExperienceRequest;
use App\Http\Resources\XpProgressResource;
use App\Models\Character;
use App\Services\ExperiencePointService;
use App\Services\LevelUpService;
use InvalidArgumentException;

class CharacterExperienceController extends Controller
{
    private const MAX_LEVEL = 20;

    public function __construct(
        private ExperiencePointService $xpService,
        private LevelUpService $levelUpService,
    ) {}

    /**
     * Get character XP progress.
     *
     * Returns the character's current XP and calculated progress toward next level.
     *
     * @operationId characters.showXp
     *
     * @tags Characters
     *
     * @response 200 {
     *   "data": {
     *     "experience_points": 10000,
     *     "level": 5,
     *     "next_level_xp": 14000,
     *     "xp_to_next_level": 4000,
     *     "xp_progress_percent": 46.67,
     *     "is_max_level": false
     *   }
     * }
     * @response 200 scenario="Max level" {
     *   "data": {
     *     "experience_points": 400000,
     *     "level": 20,
     *     "next_level_xp": null,
     *     "xp_to_next_level": 0,
     *     "xp_progress_percent": 100,
     *     "is_max_level": true
     *   }
     * }
     */
    public function show(Character $character): XpProgressResource
    {
        $currentXp = $character->experience_points ?? 0;
        $level = $this->xpService->getLevelForXp($currentXp);
        $isMaxLevel = $level >= self::MAX_LEVEL;

        $nextLevelXp = $isMaxLevel
            ? null
            : $this->xpService->getXpForLevel($level + 1);

        return new XpProgressResource(new XpProgressResult(
            experiencePoints: $currentXp,
            level: $level,
            nextLevelXp: $nextLevelXp,
            xpToNextLevel: $this->xpService->getXpToNextLevel($currentXp),
            xpProgressPercent: $this->xpService->getXpProgressPercent($currentXp),
            isMaxLevel: $isMaxLevel,
        ));
    }

    /**
     * Add experience points to a character.
     *
     * Adds the specified amount of XP to the character. If the character belongs
     * to a party with XP-based leveling and auto_level is true, the character
     * will automatically level up when crossing XP thresholds.
     *
     * Note: Auto-level may return false even when XP threshold is crossed if the
     * character has pending choices, is at max level, or has no class assigned.
     *
     * @operationId characters.addXp
     *
     * @tags Characters
     *
     * @bodyParam amount integer required XP to add (min: 0). Example: 500
     * @bodyParam auto_level boolean Trigger level-up if threshold crossed. Example: true
     *
     * @response 200 {
     *   "data": {
     *     "experience_points": 500,
     *     "level": 2,
     *     "next_level_xp": 900,
     *     "xp_to_next_level": 400,
     *     "xp_progress_percent": 33.3,
     *     "is_max_level": false,
     *     "leveled_up": false
     *   }
     * }
     */
    public function addXp(AddExperienceRequest $request, Character $character): XpProgressResource
    {
        $amount = $request->validated('amount');
        $autoLevel = $request->validated('auto_level', false);

        // Add XP atomically to prevent race conditions
        if ($amount > 0) {
            $character->increment('experience_points', $amount);
            $character->refresh();
        }

        $currentXp = $character->experience_points ?? 0;
        $level = $this->xpService->getLevelForXp($currentXp);
        $leveledUp = false;

        // Auto-level if enabled and character is in XP-mode party
        if ($autoLevel && $this->shouldAutoLevel($character)) {
            $leveledUp = $this->tryAutoLevel($character, $level);
        }

        // Handle level 20 case - no next level
        $isMaxLevel = $level >= self::MAX_LEVEL;
        $nextLevelXp = $isMaxLevel
            ? null
            : $this->xpService->getXpForLevel($level + 1);

        return new XpProgressResource(new XpProgressResult(
            experiencePoints: $currentXp,
            level: $level,
            nextLevelXp: $nextLevelXp,
            xpToNextLevel: $this->xpService->getXpToNextLevel($currentXp),
            xpProgressPercent: $this->xpService->getXpProgressPercent($currentXp),
            isMaxLevel: $isMaxLevel,
            leveledUp: $leveledUp,
        ));
    }

    /**
     * Check if character should auto-level based on party settings.
     *
     * Note: If character belongs to multiple parties, uses the first party found.
     * Characters not in any party cannot auto-level.
     */
    private function shouldAutoLevel(Character $character): bool
    {
        // Get the character's first party (if any)
        // Note: If in multiple parties, we use the first one found
        $party = $character->parties()->first();

        if (! $party) {
            return false;
        }

        return $party->leveling_mode === LevelingMode::XP;
    }

    /**
     * Attempt to level up character to match XP level.
     *
     * Returns false if character is already at or above XP level,
     * has pending choices, is at max level, or has no class.
     */
    private function tryAutoLevel(Character $character, int $xpLevel): bool
    {
        $currentLevel = $character->total_level ?? 1;

        if ($xpLevel <= $currentLevel) {
            return false;
        }

        // Level up once (user can trigger endpoint again for multi-level jumps)
        try {
            $this->levelUpService->levelUp($character);

            return true;
        } catch (MaxLevelReachedException|IncompleteCharacterException|InvalidArgumentException) {
            // Expected cases where level-up cannot proceed:
            // - MaxLevelReachedException: Already at level 20
            // - IncompleteCharacterException: Has pending choices
            // - InvalidArgumentException: No class assigned
            return false;
        }
    }
}

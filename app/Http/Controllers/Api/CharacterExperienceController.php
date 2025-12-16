<?php

namespace App\Http\Controllers\Api;

use App\Enums\LevelingMode;
use App\Exceptions\IncompleteCharacterException;
use App\Exceptions\MaxLevelReachedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\AddExperienceRequest;
use App\Models\Character;
use App\Services\ExperiencePointService;
use App\Services\LevelUpService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class CharacterExperienceController extends Controller
{
    private const MAX_LEVEL = 20;

    public function __construct(
        private ExperiencePointService $xpService,
        private LevelUpService $levelUpService,
    ) {}

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
     *     "xp_level": 2,
     *     "next_level_xp": 900,
     *     "xp_to_next_level": 400,
     *     "xp_progress_percent": 33.3,
     *     "leveled_up": false
     *   }
     * }
     */
    public function addXp(AddExperienceRequest $request, Character $character): JsonResponse
    {
        $amount = $request->validated('amount');
        $autoLevel = $request->validated('auto_level', false);

        // Add XP atomically to prevent race conditions
        if ($amount > 0) {
            $character->increment('experience_points', $amount);
            $character->refresh();
        }

        $currentXp = $character->experience_points ?? 0;
        $xpLevel = $this->xpService->getLevelForXp($currentXp);
        $leveledUp = false;

        // Auto-level if enabled and character is in XP-mode party
        if ($autoLevel && $this->shouldAutoLevel($character)) {
            $leveledUp = $this->tryAutoLevel($character, $xpLevel);
        }

        // Handle level 20 case - no next level
        $nextLevelXp = $xpLevel < self::MAX_LEVEL
            ? $this->xpService->getXpForLevel($xpLevel + 1)
            : null;

        return response()->json([
            'data' => [
                'experience_points' => $currentXp,
                'xp_level' => $xpLevel,
                'next_level_xp' => $nextLevelXp,
                'xp_to_next_level' => $this->xpService->getXpToNextLevel($currentXp),
                'xp_progress_percent' => $this->xpService->getXpProgressPercent($currentXp),
                'leveled_up' => $leveledUp,
            ],
        ]);
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

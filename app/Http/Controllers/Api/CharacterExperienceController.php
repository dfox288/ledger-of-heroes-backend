<?php

namespace App\Http\Controllers\Api;

use App\Enums\LevelingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\AddExperienceRequest;
use App\Models\Character;
use App\Services\ExperiencePointService;
use App\Services\LevelUpService;
use Illuminate\Http\JsonResponse;

class CharacterExperienceController extends Controller
{
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

        // Add XP
        $character->experience_points = ($character->experience_points ?? 0) + $amount;
        $character->save();

        $currentXp = $character->experience_points;
        $xpLevel = $this->xpService->getLevelForXp($currentXp);
        $leveledUp = false;

        // Auto-level if enabled and character is in XP-mode party
        if ($autoLevel && $this->shouldAutoLevel($character)) {
            $leveledUp = $this->tryAutoLevel($character, $xpLevel);
        }

        return response()->json([
            'data' => [
                'experience_points' => $currentXp,
                'xp_level' => $xpLevel,
                'next_level_xp' => $this->xpService->getXpForLevel($xpLevel + 1),
                'xp_to_next_level' => $this->xpService->getXpToNextLevel($currentXp),
                'xp_progress_percent' => $this->xpService->getXpProgressPercent($currentXp),
                'leveled_up' => $leveledUp,
            ],
        ]);
    }

    /**
     * Check if character should auto-level based on party settings.
     */
    private function shouldAutoLevel(Character $character): bool
    {
        // Get the character's party (if any)
        $party = $character->parties()->first();

        if (! $party) {
            return false;
        }

        return $party->leveling_mode === LevelingMode::XP;
    }

    /**
     * Attempt to level up character to match XP level.
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
        } catch (\Exception $e) {
            // Character may not be ready for level-up (missing choices, etc.)
            return false;
        }
    }
}

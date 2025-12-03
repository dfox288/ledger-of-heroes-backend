<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Character;
use Exception;
use Illuminate\Http\JsonResponse;

class AbilityScoreCapExceededException extends Exception
{
    public function __construct(
        public readonly Character $character,
        public readonly string $abilityCode,
        public readonly int $currentValue,
        public readonly int $attemptedIncrease,
        string $message = 'Ability score cannot exceed 20.',
    ) {
        parent::__construct($message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'character_id' => $this->character->id,
            'ability' => $this->abilityCode,
            'current_value' => $this->currentValue,
            'attempted_increase' => $this->attemptedIncrease,
            'would_be' => $this->currentValue + $this->attemptedIncrease,
            'maximum' => 20,
        ], 422);
    }
}

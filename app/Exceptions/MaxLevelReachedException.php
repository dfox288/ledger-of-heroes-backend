<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Character;
use Exception;
use Illuminate\Http\JsonResponse;

class MaxLevelReachedException extends Exception
{
    public function __construct(
        public readonly Character $character,
    ) {
        $charInfo = "Character '{$this->character->name}' (ID: {$this->character->id})";

        parent::__construct("{$charInfo} is already at maximum level (20).");
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'character_id' => $this->character->id,
            'current_level' => $this->character->total_level,
        ], 422);
    }
}

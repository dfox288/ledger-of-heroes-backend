<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Character;
use Exception;
use Illuminate\Http\JsonResponse;

class NoAsiChoicesRemainingException extends Exception
{
    public function __construct(
        public readonly Character $character,
        string $message = 'No ASI choices remaining. Level up to gain more.',
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
            'asi_choices_remaining' => $this->character->asi_choices_remaining,
        ], 422);
    }
}

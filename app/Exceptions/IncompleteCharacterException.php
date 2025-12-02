<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Character;
use Exception;
use Illuminate\Http\JsonResponse;

class IncompleteCharacterException extends Exception
{
    public function __construct(
        public readonly Character $character,
        string $message = 'Character must be complete before leveling up.',
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
            'validation_status' => $this->character->validation_status,
        ], 422);
    }
}

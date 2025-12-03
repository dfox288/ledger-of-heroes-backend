<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Character;
use App\Models\Feat;
use Exception;
use Illuminate\Http\JsonResponse;

class FeatAlreadyTakenException extends Exception
{
    public function __construct(
        public readonly Character $character,
        public readonly Feat $feat,
        string $message = 'Character has already taken this feat.',
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
            'feat_id' => $this->feat->id,
            'feat_name' => $this->feat->name,
        ], 422);
    }
}

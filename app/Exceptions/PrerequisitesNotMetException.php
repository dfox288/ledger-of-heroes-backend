<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Character;
use App\Models\Feat;
use Exception;
use Illuminate\Http\JsonResponse;

class PrerequisitesNotMetException extends Exception
{
    /**
     * @param  array<array{type: string, requirement: string, current: string|int|null}>  $unmetPrerequisites
     */
    public function __construct(
        public readonly Character $character,
        public readonly Feat $feat,
        public readonly array $unmetPrerequisites,
        string $message = 'Character does not meet feat prerequisites.',
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
            'unmet_prerequisites' => $this->unmetPrerequisites,
        ], 422);
    }
}

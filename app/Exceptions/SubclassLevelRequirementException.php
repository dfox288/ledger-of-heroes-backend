<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class SubclassLevelRequirementException extends Exception
{
    public function __construct(
        public readonly string $className,
        public readonly int $currentLevel,
        public readonly int $requiredLevel = 3,
    ) {
        parent::__construct(
            "Cannot set subclass for {$className}: character must be at least level {$requiredLevel} in this class (currently level {$currentLevel})"
        );
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'subclass_id' => [$this->getMessage()],
            ],
            'current_level' => $this->currentLevel,
            'required_level' => $this->requiredLevel,
        ], 422);
    }
}

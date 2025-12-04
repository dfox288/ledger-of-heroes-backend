<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InvalidSubclassException extends Exception
{
    public function __construct(
        public readonly string $subclassName,
        public readonly string $className,
    ) {
        parent::__construct("Subclass '{$subclassName}' does not belong to class '{$className}'.");
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
        ], 422);
    }
}

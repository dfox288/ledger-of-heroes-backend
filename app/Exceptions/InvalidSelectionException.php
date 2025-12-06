<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class InvalidSelectionException extends ApiException
{
    public function __construct(
        public readonly string $choiceId,
        public readonly string $selection,
        string $message = 'Invalid selection for choice'
    ) {
        parent::__construct($message);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'choice_id' => $this->choiceId,
            'selection' => $this->selection,
        ], 422);
    }
}

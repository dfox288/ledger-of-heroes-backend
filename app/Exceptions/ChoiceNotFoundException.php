<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class ChoiceNotFoundException extends ApiException
{
    public function __construct(
        public readonly string $choiceId,
        string $message = 'Choice not found'
    ) {
        parent::__construct($message);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'choice_id' => $this->choiceId,
        ], 404);
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientFundsException extends Exception
{
    public function __construct(
        public readonly int $availableCopperValue,
        public readonly int $requiredCopperValue,
        ?string $message = null
    ) {
        $message = $message ?? sprintf(
            'Cannot afford this transaction. Need %d CP but only %d CP available after conversion.',
            $requiredCopperValue,
            $availableCopperValue
        );

        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Insufficient funds',
            'errors' => [
                'currency' => [$this->getMessage()],
            ],
        ], 422);
    }
}

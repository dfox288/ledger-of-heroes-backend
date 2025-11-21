<?php

namespace App\Exceptions\Import;

use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class ImportException extends ApiException
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->getCode() ?: 500);
    }
}

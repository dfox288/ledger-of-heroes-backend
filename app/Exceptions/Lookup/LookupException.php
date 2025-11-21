<?php

namespace App\Exceptions\Lookup;

use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class LookupException extends ApiException
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->getCode() ?: 500);
    }
}

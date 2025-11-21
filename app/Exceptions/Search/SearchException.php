<?php

namespace App\Exceptions\Search;

use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class SearchException extends ApiException
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->getCode() ?: 500);
    }
}

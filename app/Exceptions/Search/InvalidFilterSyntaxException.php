<?php

namespace App\Exceptions\Search;

use Illuminate\Http\JsonResponse;

class InvalidFilterSyntaxException extends SearchException
{
    public function __construct(
        public readonly string $filter,
        public readonly string $meilisearchMessage,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: "Invalid filter syntax: {$meilisearchMessage}",
            code: 422,
            previous: $previous
        );
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => 'Invalid filter syntax',
            'error' => $this->meilisearchMessage,
            'filter' => $this->filter,
            'documentation' => url('/docs/meilisearch-filters'),
        ], 422);
    }
}

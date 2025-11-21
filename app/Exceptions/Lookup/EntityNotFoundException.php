<?php

namespace App\Exceptions\Lookup;

use Illuminate\Http\JsonResponse;

class EntityNotFoundException extends LookupException
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $identifier,
        public readonly string $column = 'id'
    ) {
        parent::__construct(
            message: "{$entityType} not found with {$column}: {$identifier}",
            code: 404
        );
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => "{$this->entityType} not found",
            'identifier' => $this->identifier,
            'search_column' => $this->column,
        ], 404);
    }
}

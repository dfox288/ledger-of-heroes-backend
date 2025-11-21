<?php

namespace App\Exceptions\Import;

use Illuminate\Http\JsonResponse;

class FileNotFoundException extends ImportException
{
    public function __construct(
        public readonly string $filePath
    ) {
        parent::__construct(
            message: "Import file not found: {$filePath}",
            code: 404
        );
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => 'Import file not found',
            'file_path' => $this->filePath,
        ], 404);
    }
}

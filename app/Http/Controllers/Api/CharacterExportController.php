<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\CharacterImportRequest;
use App\Http\Resources\CharacterExportResource;
use App\Http\Resources\CharacterImportResultResource;
use App\Models\Character;
use App\Services\CharacterExportService;
use App\Services\CharacterImportService;
use Illuminate\Http\JsonResponse;

/**
 * Handle character export and import for portable JSON sharing.
 *
 * @tags Character Export/Import
 */
class CharacterExportController extends Controller
{
    public function __construct(
        private CharacterExportService $exportService,
        private CharacterImportService $importService,
    ) {}

    /**
     * Export character as portable JSON.
     *
     * Exports a character in a portable format that can be imported into
     * any instance of the application. Uses slugs instead of database IDs.
     *
     * @operationId exportCharacter
     */
    public function export(Character $character): CharacterExportResource
    {
        $exportData = $this->exportService->export($character);

        return new CharacterExportResource($exportData);
    }

    /**
     * Import character from portable JSON.
     *
     * Creates a new character from exported JSON data. If the public_id
     * conflicts with an existing character, a new unique ID is generated.
     * Dangling references (slugs not found in database) are preserved but
     * reported as warnings.
     *
     * @operationId importCharacter
     *
     * @response 201 array{
     *     data: array{
     *         success: bool,
     *         character: array{
     *             public_id: string,
     *             name: string,
     *             race: string|null
     *         },
     *         warnings: array<string>
     *     }
     * }
     * @response 422 array{
     *     message: string,
     *     errors: array<string, array<string>>
     * }
     */
    public function import(CharacterImportRequest $request): JsonResponse
    {
        $result = $this->importService->import($request->validated());

        return (new CharacterImportResultResource($result))
            ->response()
            ->setStatusCode(201);
    }
}

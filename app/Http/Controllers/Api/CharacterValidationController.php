<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterValidationResource;
use App\Models\Character;
use App\Services\CharacterValidationService;
use Illuminate\Http\JsonResponse;

/**
 * @tags Characters
 */
class CharacterValidationController extends Controller
{
    public function __construct(
        private CharacterValidationService $validationService,
    ) {}

    /**
     * Validate character references
     *
     * Checks if all slug-based references on a character resolve to existing entities.
     * Detects dangling references that may occur when sourcebook data is reimported.
     *
     * **Response:**
     * - `valid`: Whether all references resolve successfully
     * - `dangling_references`: Object mapping reference types to missing slugs
     * - `summary`: Statistics about total vs dangling references
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/brave-wizard-x7k2/validate
     * ```
     *
     * @response 200 {
     *   "data": {
     *     "valid": false,
     *     "dangling_references": {
     *       "race": "phb:high-elf",
     *       "spells": ["phb:wish", "phb:meteor-swarm"]
     *     },
     *     "summary": {
     *       "total_references": 15,
     *       "valid_references": 12,
     *       "dangling_count": 3
     *     }
     *   }
     * }
     */
    public function show(Character $character): CharacterValidationResource
    {
        $result = $this->validationService->validate($character);

        return new CharacterValidationResource($result);
    }

    /**
     * Validate all characters
     *
     * Validates all characters and returns a summary of those with dangling references.
     * Only invalid characters are included in the response to minimize payload size.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/validate-all
     * ```
     *
     * @response 200 {
     *   "data": {
     *     "total": 42,
     *     "valid": 38,
     *     "invalid": 4,
     *     "characters": [
     *       {
     *         "public_id": "brave-wizard-x7k2",
     *         "name": "Gandalf",
     *         "dangling_references": {"race": "phb:high-elf"}
     *       }
     *     ]
     *   }
     * }
     */
    public function index(): JsonResponse
    {
        $result = $this->validationService->validateAll();

        return response()->json([
            'data' => $result,
        ]);
    }
}

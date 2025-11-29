<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SourceIndexRequest;
use App\Http\Resources\SourceResource;
use App\Models\Source;
use Dedoc\Scramble\Attributes\QueryParameter;

class SourceController extends Controller
{
    /**
     * List all D&D 5e sourcebooks
     *
     * Returns a paginated list of official D&D 5th Edition sourcebooks (9 total).
     * Includes core rulebooks, supplements, and adventure modules. Supports searching by name or code abbreviation.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/sources              # All sourcebooks
     * GET /api/v1/lookups/sources?q=handbook   # Search for "Player's Handbook"
     * GET /api/v1/lookups/sources?q=PHB        # Search by code abbreviation
     * GET /api/v1/lookups/sources?per_page=20  # Custom page size
     * ```
     *
     * **D&D 5e Sourcebooks Reference:**
     * - **Core Rulebooks (3):** Player's Handbook (PHB), Dungeon Master's Guide (DMG), Monster Manual (MM)
     * - **Core Supplements (2):** Xanathar's Guide to Everything (XGE), Tasha's Cauldron of Everything (TCE)
     * - **Rulebook Supplements (3):** Sword Coast Adventurer's Guide (SCAG), Volo's Guide to Monsters (VGM), Eberron: Rising From the Last War (ERLW)
     * - **Adventures (1):** The Wild Beyond the Witchlight (TWBTW)
     *
     * **Query Parameters:**
     * - `q` (string): Search by sourcebook name or code (partial match, case-insensitive)
     * - `per_page` (int): Results per page, 1-100 (default: 50)
     * - `page` (int): Page number (default: 1)
     *
     * **Use Cases:**
     * - Building content filters: "Load all available sources for player to filter spells/items"
     * - Source validation: "Check if a source code exists (e.g., is this 'XGE' official?)"
     * - Content discovery: "Browse all available D&D rulebooks and supplements"
     * - Publication reference: "What year was Xanathar's Guide published?"
     *
     * @param  SourceIndexRequest  $request  Validated request with search and pagination parameters
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('q', description: 'Search by sourcebook name or code (e.g., "handbook", "PHB", "xanathar")', example: 'handbook')]
    #[QueryParameter('per_page', description: 'Results per page (1-100, default: 50)', example: '20')]
    public function index(SourceIndexRequest $request)
    {
        $query = Source::query();

        // Add search support
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        // Add pagination support
        $perPage = $request->validated('per_page', 50); // Higher default for lookups
        $entities = $query->paginate($perPage);

        return SourceResource::collection($entities);
    }

    /**
     * Get a single sourcebook
     *
     * Returns detailed information about a specific D&D 5e sourcebook including publication year,
     * publisher, author, website, category, and full description.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/sources/PHB                 # Player's Handbook by code
     * GET /api/v1/lookups/sources/1                   # By database ID
     * ```
     *
     * **Use Cases:**
     * - Displaying source metadata: "Show the user details about where this spell originated"
     * - Source credibility: "Verify official publication year and publisher"
     * - Content attribution: "Link to the original source website and author information"
     *
     * @param  Source  $source  The sourcebook to retrieve (accepts ID or code)
     * @return \App\Http\Resources\SourceResource
     */
    public function show(Source $source)
    {
        return new SourceResource($source);
    }
}

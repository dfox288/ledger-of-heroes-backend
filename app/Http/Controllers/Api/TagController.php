<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Tags\Tag;

/**
 * Tag lookup endpoint.
 *
 * Returns all tags used across entities (spells, items, monsters, etc.)
 * for populating filter dropdowns in the frontend.
 */
class TagController extends Controller
{
    /**
     * List all tags
     *
     * Returns all tags used to categorize D&D 5e entities across the system. Tags enable semantic
     * organization of spells, monsters, items, races, classes, feats, and backgrounds. Use the
     * optional `type` parameter to filter tags by entity type for focused frontend dropdowns.
     *
     * **Tag System Overview:**
     * Tags are polymorphic labels applied to entities for semantic categorization. Each tag has:
     * - **id** (int): Unique tag identifier
     * - **name** (string): Human-readable display name (e.g., "Ritual", "Concentration")
     * - **slug** (string): URL-friendly identifier (e.g., "ritual", "concentration")
     * - **type** (string): Entity category the tag belongs to (spell, monster, item, race, class, feat, background)
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/lookups/tags                    # All tags across all entity types
     * GET /api/v1/lookups/tags?type=spell         # Spell-specific tags (e.g., "Ritual", "Concentration")
     * GET /api/v1/lookups/tags?type=monster       # Monster-specific tags (e.g., "Legendary", "Fiend")
     * GET /api/v1/lookups/tags?type=item          # Item-specific tags (e.g., "Magic", "Artifact")
     * ```
     *
     * **Spell Tag Examples:**
     * - "Ritual" - Can be cast as a ritual without using spell slot
     * - "Concentration" - Requires concentration to maintain the spell
     * - "Healing" - Provides restoration or damage recovery
     * - "Control" - Manipulates enemy movement or positioning
     *
     * **Monster Tag Examples:**
     * - "Legendary" - Has legendary actions
     * - "Lair Actions" - Has lair-based abilities
     * - "Fiend" - Creature type classification
     *
     * **Item Tag Examples:**
     * - "Artifact" - Legendary item of great power
     * - "Magical" - Enchanted or magical item
     * - "Cursed" - Has curse mechanics
     * - "Wondrous" - Miscellaneous magical item
     *
     * **Query Parameters:**
     * - `type` (string): Filter tags by entity type (spell, monster, item, race, class, feat, background)
     *
     * **Use Cases:**
     * - **Frontend Dropdowns:** Populate multi-select filters in spell/item/monster browsers
     * - **Entity Discovery:** "Show me all ritual spells" or "legendary monsters"
     * - **Character Building:** Filter spells by "Concentration" to plan action economy
     * - **Encounter Design:** Find monsters tagged as "Legendary" or "Fiend" for boss encounters
     * - **Loot Tables:** Browse items tagged as "Artifact" or "Magical" for treasure rewards
     *
     * **Data Source:**
     * Tag system powered by Spatie Laravel Tags package. All tags are synced during entity imports
     * and tagged dynamically through the application. Total unique tags: 100+
     */
    #[QueryParameter('type', description: 'Filter tags by entity type: spell, monster, item, race, class, feat, background', example: 'spell')]
    public function index(): AnonymousResourceCollection
    {
        $query = Tag::query()->ordered();

        // Optional type filter
        if (request()->has('type')) {
            $query->where('type', request('type'));
        }

        $tags = $query->get();

        return TagResource::collection($tags);
    }
}

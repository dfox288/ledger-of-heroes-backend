<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
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
     * Returns all tags used in the system, ordered by name. Tags are used to categorize
     * spells (e.g., "Ritual", "Concentration"), items, monsters, and other entities.
     *
     * **Examples:**
     * - `GET /api/v1/lookups/tags` - All tags
     * - `GET /api/v1/lookups/tags?type=spell` - Tags of a specific type
     *
     * **Common Tag Types:**
     * - Spell tags: "Ritual", "Concentration", "Healing", "Damage"
     * - Item tags: "Magic", "Consumable", "Weapon", "Armor"
     * - Monster tags: "Legendary", "Lair Actions", "Shapechanger"
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
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

<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemSearchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ItemIndexRequest;
use App\Http\Requests\ItemShowRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\Cache\EntityCacheService;
use App\Services\ItemSearchService;
use Dedoc\Scramble\Attributes\QueryParameter;
use MeiliSearch\Client;

class ItemController extends Controller
{
    /**
     * List all items
     *
     * Returns a paginated list of 2,232 D&D 5e items including weapons, armor, and magic items. Use `?filter=` for filtering and `?q=` for full-text search.
     *
     * **Common Examples:**
     * ```
     * GET /api/v1/items                                          # All items
     * GET /api/v1/items?filter=is_magic = true                   # Magic items only
     * GET /api/v1/items?filter=type_code = HA                    # Heavy armor
     * GET /api/v1/items?filter=rarity = legendary                # Legendary items
     * GET /api/v1/items?filter=requires_attunement = true        # Attunement items
     * GET /api/v1/items?filter=spell_slugs IN [fireball]         # Items with Fireball
     * GET /api/v1/items?q=staff                                  # Full-text search for "staff"
     * GET /api/v1/items?q=sword&filter=is_magic = true           # Search + filter combined
     * ```
     *
     * **Filterable Fields by Data Type:**
     *
     * **Integer Fields** (Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `TO`):
     * - `id` (int): Item ID
     * - `weight` (float): Weight in pounds
     *   - Examples: `weight <= 1.0`, `weight >= 5`, `weight 1 TO 10`
     * - `cost_cp` (int): Cost in copper pieces (100cp = 1gp)
     *   - Examples: `cost_cp >= 5000` (50+ gold), `cost_cp 100 TO 1000` (1-10gp)
     * - `range_normal` (int): Normal range in feet (ranged weapons)
     *   - Examples: `range_normal >= 80`, `range_normal > 0` (has range)
     * - `range_long` (int): Long range in feet (ranged weapons)
     * - `armor_class` (int): Base armor class (armor only)
     *   - Examples: `armor_class >= 16`, `armor_class 14 TO 18`
     * - `strength_requirement` (int): Minimum strength to wear (heavy armor)
     *   - Examples: `strength_requirement > 0` (has requirement)
     * - `charges_max` (int): Maximum charges (magic items)
     *   - Examples: `charges_max >= 10`, `charges_max 5 TO 20`
     *
     * **String Fields** (Operators: `=`, `!=`):
     * - `slug` (string): URL-friendly identifier
     * - `type_name` (string): Full type name (Heavy Armor, Wand, Staff, etc.)
     * - `type_code` (string): Two-letter type code (HA, WD, ST, RD, SCR, P, etc.)
     *   - Examples: `type_code = HA` (heavy armor), `type_code = WD` (wands)
     * - `rarity` (string): common, uncommon, rare, very_rare, legendary, artifact
     *   - Examples: `rarity = legendary`, `rarity != common`
     * - `damage_dice` (string): Damage dice (1d8, 2d6, etc.)
     * - `versatile_damage` (string): Versatile damage dice
     * - `damage_type` (string): Damage type (Slashing, Piercing, Bludgeoning, Fire, etc.)
     * - `recharge_timing` (string): When charges recharge (dawn, dusk, etc.)
     * - `recharge_formula` (string): Recharge dice formula (1d6+4, etc.)
     *
     * **Boolean Fields** (Operators: `=`, `!=`, `IS NULL`, `EXISTS`):
     * - `requires_attunement` (bool): Requires attunement
     *   - Examples: `requires_attunement = true`, `requires_attunement = false`
     * - `is_magic` (bool): Magic item
     *   - Examples: `is_magic = true`
     * - `stealth_disadvantage` (bool): Imposes disadvantage on Stealth (heavy armor)
     *   - Examples: `stealth_disadvantage = false` (silent armor)
     * - `has_charges` (bool): Item has charges
     *   - Examples: `has_charges = true`
     * - `has_prerequisites` (bool): Has class/race/level prerequisites
     *   - Examples: `has_prerequisites = true`
     *
     * **Array Fields** (Operators: `IN`, `NOT IN`, `IS EMPTY`):
     * - `source_codes` (array): Source book codes (PHB, DMG, XGE, TCoE, etc.)
     *   - Examples: `source_codes IN [PHB, DMG]`, `source_codes NOT IN [UA]`
     * - `spell_slugs` (array): Spell slugs associated with item
     *   - Examples: `spell_slugs IN [fireball]`, `spell_slugs IS EMPTY`
     * - `tag_slugs` (array): Tag slugs
     *   - Examples: `tag_slugs IN [fire]`, `tag_slugs IS EMPTY`
     * - `property_codes` (array): Weapon/armor property codes (F=Finesse, V=Versatile, etc.)
     *   - Examples: `property_codes IN [F]` (finesse weapons)
     * - `modifier_categories` (array): Stat modifier categories
     *   - Examples: `modifier_categories IN [ability_score]`
     * - `proficiency_names` (array): Required proficiency names
     *   - Examples: `proficiency_names IN [martial weapons]`
     * - `saving_throw_abilities` (array): Saving throw ability codes (STR, DEX, CON, INT, WIS, CHA)
     *   - Examples: `saving_throw_abilities IN [DEX]`, `saving_throw_abilities IS EMPTY`
     *
     * **Complex Filter Examples:**
     * - Heavy armor with high AC: `?filter=type_code = HA AND armor_class >= 16`
     * - Ranged weapons: `?filter=range_normal >= 80`
     * - Magic items with charges: `?filter=is_magic = true AND has_charges = true`
     * - Finesse weapons: `?filter=property_codes IN [F] AND type_code = W`
     * - Silent heavy armor: `?filter=type_code = HA AND stealth_disadvantage = false`
     * - Affordable magic items: `?filter=is_magic = true AND cost_cp <= 10000` (100gp or less)
     * - Legendary items requiring attunement: `?filter=rarity = legendary AND requires_attunement = true`
     * - Items with prerequisites: `?filter=has_prerequisites = true`
     *
     * **Use Cases:**
     * - Shopping lists: Filter by cost range and item type
     * - Loot tables: Filter by rarity and magic status
     * - Equipment planning: Filter by weight for encumbrance
     * - Class builds: Filter by weapon properties and proficiencies
     * - Magic item search: Filter by spell effects and attunement
     * - Armor optimization: Filter by AC, stealth, and strength requirement
     *
     * **Operator Reference:**
     * See `docs/MEILISEARCH-FILTER-OPERATORS.md` for comprehensive operator documentation.
     *
     * **Query Parameters:**
     * - `q` (string): Full-text search (searches name, description)
     * - `filter` (string): Meilisearch filter expression
     * - `sort_by` (string): name, weight, cost_cp, armor_class, created_at, updated_at (default: name)
     * - `sort_direction` (string): asc, desc (default: asc)
     * - `per_page` (int): 1-100 (default: 15)
     * - `page` (int): Page number (default: 1)
     *
     * @param  ItemIndexRequest  $request  Validated request with filtering parameters
     * @param  ItemSearchService  $service  Service layer for item queries
     * @param  Client  $meilisearch  Meilisearch client for advanced filtering
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter', description: 'Meilisearch filter expression. Supports all operators by data type: Integer (=,!=,>,>=,<,<=,TO), String (=,!=), Boolean (=,!=,IS NULL,EXISTS), Array (IN,NOT IN,IS EMPTY). Filterable fields: id, slug, type_name, type_code, rarity, requires_attunement, is_magic, weight, cost_cp, source_codes, damage_dice, versatile_damage, damage_type, range_normal, range_long, armor_class, strength_requirement, stealth_disadvantage, charges_max, has_charges, recharge_timing, recharge_formula, spell_slugs, tag_slugs, property_codes, modifier_categories, proficiency_names, saving_throw_abilities, has_prerequisites. See docs/MEILISEARCH-FILTER-OPERATORS.md for details.', example: 'type_code = HA AND armor_class >= 16')]
    public function index(ItemIndexRequest $request, ItemSearchService $service, Client $meilisearch)
    {
        $dto = ItemSearchDTO::fromRequest($request);

        // Use Meilisearch for ANY search query or filter expression
        // This enables filter-only queries without requiring ?q= parameter
        if ($dto->searchQuery !== null || $dto->meilisearchFilter !== null) {
            $items = $service->searchWithMeilisearch($dto, $meilisearch);
        } else {
            // Database query for pure pagination (no search/filter)
            $items = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
        }

        return ItemResource::collection($items);
    }

    /**
     * Get a single item
     *
     * Returns detailed information about a specific item including item type, damage type,
     * properties, abilities, random tables, modifiers, proficiencies, and prerequisites.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(ItemShowRequest $request, Item $item, EntityCacheService $cache, ItemSearchService $service)
    {
        $validated = $request->validated();

        // Default relationships from service
        $defaultRelationships = $service->getShowRelationships();

        // Try cache first
        $cachedItem = $cache->getItem($item->id);

        if ($cachedItem) {
            // If include parameter provided, use it; otherwise load defaults
            $includes = $validated['include'] ?? $defaultRelationships;
            $cachedItem->load($includes);

            return new ItemResource($cachedItem);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;
        $item->load($includes);

        return new ItemResource($item);
    }
}

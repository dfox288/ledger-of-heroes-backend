<?php

namespace App\Http\Resources;

use App\Models\EntityChoice;
use App\Models\Item;
use App\Models\ProficiencyType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for entity choices (equipment, proficiency, spell, language, ability_score).
 *
 * @mixin EntityChoice
 */
class EntityChoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'choice_type' => $this->choice_type,
            'choice_group' => $this->choice_group,
            'choice_option' => $this->choice_option,
            'quantity' => $this->quantity,
            'constraint' => $this->constraint,
            'target_type' => $this->target_type,
            'target_slug' => $this->target_slug,
            'description' => $this->description,
            'level_granted' => $this->level_granted,
            'is_required' => $this->is_required,

            // Spell-specific fields
            'spell_max_level' => $this->when($this->choice_type === 'spell', $this->spell_max_level),
            'spell_list_slug' => $this->when($this->choice_type === 'spell', $this->spell_list_slug),
            'spell_school_slug' => $this->when($this->choice_type === 'spell', $this->spell_school_slug),

            // Proficiency-specific fields
            'proficiency_type' => $this->when($this->choice_type === 'proficiency', $this->proficiency_type),

            // Additional constraints
            'constraints' => $this->constraints,
        ];
    }

    /**
     * Group equipment choices by choice_group for the API response.
     *
     * Returns format matching pending-choices endpoint:
     * - Options grouped by choice_option number
     * - Option numbers converted to letters (1 -> 'a', 2 -> 'b')
     * - Items resolved with name, slug, quantity, is_fixed
     * - Category choices (e.g., "any simple weapon") marked with is_category
     *
     * @param  \Illuminate\Support\Collection<int, EntityChoice>  $choices
     * @return array<int, array{choice_group: string, quantity: int, level_granted: int, is_required: bool, options: array}>
     */
    public static function groupedByChoiceGroup($choices): array
    {
        if ($choices->isEmpty()) {
            return [];
        }

        $grouped = $choices->groupBy('choice_group');

        return $grouped->map(function ($groupChoices, $choiceGroup) {
            $first = $groupChoices->first();

            // Group by choice_option within this choice_group
            $optionsByNumber = $groupChoices->groupBy('choice_option');

            $builtOptions = [];
            foreach ($optionsByNumber as $optionNumber => $optionItems) {
                // Convert option number to letter (1 => 'a', 2 => 'b', etc.)
                $optionLetter = chr(96 + (int) $optionNumber);

                // Build items for this option
                $optionData = self::buildOptionItems($optionItems);

                // Get label from first item's description
                $label = $optionItems->first()?->description ?? '';

                $builtOptions[] = [
                    'option' => $optionLetter,
                    'label' => $label,
                    'items' => $optionData['items'],
                    'is_category' => $optionData['is_category'],
                    'category_item_count' => $optionData['category_item_count'],
                    'select_count' => $optionData['select_count'],
                ];
            }

            return [
                'choice_group' => $choiceGroup,
                'quantity' => 1, // Pick one option per group
                'level_granted' => $first->level_granted,
                'is_required' => $first->is_required ?? true,
                'options' => $builtOptions,
            ];
        })->values()->toArray();
    }

    /**
     * Build items array for an equipment option from EntityChoice records.
     *
     * Handles two types:
     * - Category (target_type='proficiency_type'): "any simple weapon" - returns available items
     * - Specific item (target_type='item'): Returns the specific item
     *
     * @return array{items: array, is_category: bool, category_item_count: int, select_count: int}
     */
    private static function buildOptionItems($optionChoices): array
    {
        $items = [];
        $isCategory = false;
        $categoryItemCount = 0;
        $selectCount = 1;

        foreach ($optionChoices as $entityChoice) {
            $quantity = $entityChoice->constraints['quantity'] ?? 1;

            // Category choice (e.g., "any simple weapon")
            if ($entityChoice->target_type === 'proficiency_type' && $entityChoice->target_slug) {
                $profType = ProficiencyType::where('slug', $entityChoice->target_slug)->first();
                if ($profType) {
                    $categoryItems = self::getItemsForProficiencyType($profType);
                    $isCategory = true;
                    $categoryItemCount = $categoryItems->count();
                    $selectCount = $quantity;

                    foreach ($categoryItems as $item) {
                        $items[] = [
                            'slug' => $item->slug,
                            'name' => $item->name,
                            'quantity' => 1,
                            'is_fixed' => false, // User must select
                        ];
                    }
                }
            } elseif ($entityChoice->target_type === 'item' && $entityChoice->target_slug) {
                // Specific item
                $item = Item::where('slug', $entityChoice->target_slug)->with('contents.item')->first();
                if ($item) {
                    // Pack item - include contents
                    if ($item->contents->isNotEmpty()) {
                        $contents = [];
                        foreach ($item->contents as $content) {
                            if ($content->item) {
                                $contents[] = [
                                    'slug' => $content->item->slug,
                                    'name' => $content->item->name,
                                    'quantity' => $content->quantity ?? 1,
                                ];
                            }
                        }
                        $items[] = [
                            'slug' => $item->slug,
                            'name' => $item->name,
                            'quantity' => $quantity,
                            'is_fixed' => true,
                            'is_pack' => true,
                            'contents' => $contents,
                        ];
                    } else {
                        // Regular item
                        $items[] = [
                            'slug' => $item->slug,
                            'name' => $item->name,
                            'quantity' => $quantity,
                            'is_fixed' => true,
                        ];
                    }
                }
            }
        }

        return [
            'items' => $items,
            'is_category' => $isCategory,
            'category_item_count' => $categoryItemCount,
            'select_count' => $selectCount,
        ];
    }

    /**
     * Get items matching a proficiency type category.
     *
     * Items are linked to proficiency types via the entity_proficiencies table.
     * For example, "Club" item has proficiencies: "Simple Weapons" and "Club".
     * So to find all simple weapons, we find items with a "Simple Weapons" proficiency.
     */
    private static function getItemsForProficiencyType(ProficiencyType $proficiencyType)
    {
        // Find items that have a proficiency relationship to this proficiency type
        // This works for weapon categories (Simple Weapons, Martial Weapons)
        // and instrument categories (Musical Instruments)
        return Item::whereHas('proficiencies', function ($query) use ($proficiencyType) {
            $query->where('proficiency_type_id', $proficiencyType->id);
        })
            ->where('is_magic', false)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }
}

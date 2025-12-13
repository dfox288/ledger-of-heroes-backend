<?php

namespace App\Services\Parsers\Concerns;

use App\Enums\ToolProficiencyCategory;
use SimpleXMLElement;

/**
 * Parses starting equipment from class XML.
 *
 * Handles wealth formulas and structured equipment choices from
 * the "Starting [Class]" feature text.
 *
 * Requires: ConvertsWordNumbers trait (for wordToNumber method)
 */
trait ParsesClassEquipment
{
    /**
     * Parse starting equipment from class XML.
     *
     * Extracts:
     * - Wealth formula (<wealth> tag)
     * - Starting equipment from "Starting [Class]" feature text
     *
     * @return array{wealth: string|null, items: array}
     */
    private function parseEquipment(SimpleXMLElement $element): array
    {
        $equipment = [
            'wealth' => null,
            'items' => [],
        ];

        // Parse wealth formula (e.g., "2d4x10")
        if (isset($element->wealth)) {
            $equipment['wealth'] = (string) $element->wealth;
        }

        // Parse starting equipment from level 1 "Starting [Class]" feature
        foreach ($element->autolevel as $autolevel) {
            if ((int) $autolevel['level'] !== 1) {
                continue;
            }

            foreach ($autolevel->feature as $feature) {
                $featureName = (string) $feature->name;

                // Match "Starting Barbarian", "Starting Fighter", etc.
                if (preg_match('/^Starting\s+\w+$/i', $featureName)) {
                    $text = (string) $feature->text;

                    // Extract only the equipment section
                    // PHB classes use "You begin play with the following equipment"
                    // Tasha's/non-PHB classes use "You start with the following equipment"
                    // Match either variant and extract until "If you forgo"
                    if (preg_match('/You (?:begin play|start) with the following equipment[^•\-]+(.*?)(?=\n\nIf you forgo|$)/s', $text, $match)) {
                        $equipmentText = $match[1];
                        $equipment['items'] = $this->parseEquipmentChoices($equipmentText);
                    } else {
                        // Fallback: use entire text if pattern not found
                        $equipment['items'] = $this->parseEquipmentChoices($text);
                    }
                    break 2; // Found it, exit both loops
                }
            }
        }

        return $equipment;
    }

    /**
     * Parse equipment choice text into structured items.
     *
     * Handles patterns like:
     * - "(a) a greataxe or (b) any martial melee weapon"
     * - "(a) X, (b) Y, or (c) Z" (three-way choice)
     * - "An explorer's pack, and four javelins"
     * - "any two simple weapons of your choice" (Tasha's format)
     * - "your choice of studded leather armor or scale mail" (Tasha's format)
     *
     * @return array<int, array{description: string, is_choice: bool, choice_group: string|null, choice_option: int|null, quantity: int, choice_items: array}>
     */
    private function parseEquipmentChoices(string $text): array
    {
        $items = [];
        $choiceGroupNumber = 1;

        // Extract bullet points (• or - prefix, with optional leading whitespace)
        // Use 'u' flag for UTF-8 support (bullet • is 3-byte UTF-8 character)
        preg_match_all('/[•\-]\s*(.+?)(?=\n\s*[•\-]|\n\n|$)/su', $text, $bullets);

        foreach ($bullets[1] as $bulletText) {
            $bulletText = trim($bulletText);

            // Pattern 1: "any (number)? (category) weapons? of your choice"
            // Examples: "any two simple weapons of your choice", "any simple weapon"
            if (preg_match('/^any\s+(?:(two|three|four|five|one|\d+)\s+)?(simple|martial)(?:\s+(melee|ranged))?\s+weapons?\s*(?:of\s+your\s+choice)?$/i', $bulletText, $matches)) {
                $quantity = 1;
                if (! empty($matches[1])) {
                    $quantity = is_numeric($matches[1]) ? (int) $matches[1] : $this->wordToNumber($matches[1]);
                }

                $category = strtolower($matches[2]);
                if (! empty($matches[3])) {
                    $category .= '_'.strtolower($matches[3]);
                }

                $items[] = [
                    'description' => $bulletText,
                    'is_choice' => true,
                    'choice_group' => "choice_{$choiceGroupNumber}",
                    'choice_option' => null, // Single category choice, no options
                    'quantity' => $quantity,
                    'choice_items' => [
                        ['type' => 'category', 'value' => $category, 'quantity' => $quantity],
                    ],
                ];
                $choiceGroupNumber++;

                continue;
            }

            // Pattern 2: "your choice of X or Y"
            // Examples: "your choice of studded leather armor or scale mail"
            if (preg_match('/^your\s+choice\s+of\s+(.+?)\s+or\s+(.+)$/i', $bulletText, $matches)) {
                $optionA = trim($matches[1]);
                $optionB = trim($matches[2]);

                // Remove leading articles
                $optionA = preg_replace('/^(a|an|the)\s+/i', '', $optionA);
                $optionB = preg_replace('/^(a|an|the)\s+/i', '', $optionB);

                $choiceGroup = "choice_{$choiceGroupNumber}";

                // Parse option A
                $choiceItemsA = $this->parseCompoundItem($optionA);
                $quantityA = array_sum(array_column($choiceItemsA, 'quantity')) ?: 1;
                $items[] = [
                    'description' => $optionA,
                    'is_choice' => true,
                    'choice_group' => $choiceGroup,
                    'choice_option' => 1,
                    'quantity' => $quantityA,
                    'choice_items' => $choiceItemsA,
                ];

                // Parse option B
                $choiceItemsB = $this->parseCompoundItem($optionB);
                $quantityB = array_sum(array_column($choiceItemsB, 'quantity')) ?: 1;
                $items[] = [
                    'description' => $optionB,
                    'is_choice' => true,
                    'choice_group' => $choiceGroup,
                    'choice_option' => 2,
                    'quantity' => $quantityB,
                    'choice_items' => $choiceItemsB,
                ];

                $choiceGroupNumber++;

                continue;
            }

            // Check if this is a choice: "(a) X or (b) Y" or "(a) X, (b) Y, or (c) Z"
            // Pattern matches (a) followed by text (allowing nested parentheses) until next choice or end
            if (preg_match('/\([a-z]\)/i', $bulletText)) {
                // Has choice markers, extract all options
                // Lookahead matches: " or (x)", ", (x)", or end of string
                if (preg_match_all('/\(([a-z])\)\s*(.+?)(?=\s+(?:,\s*)?or\s+\([a-z]\)|\s*,\s*\([a-z]\)|$)/i', $bulletText, $choices)) {
                    $optionNumber = 1;
                    foreach ($choices[2] as $choiceText) {
                        // Clean up trailing ", or" and whitespace
                        $choiceText = preg_replace('/\s*,?\s*or\s*$/i', '', $choiceText);
                        $choiceText = preg_replace('/\s*,\s*$/i', '', $choiceText);
                        $choiceText = trim($choiceText);

                        if (empty($choiceText)) {
                            continue;
                        }

                        // Parse compound items (e.g., "a martial weapon and a shield")
                        $choiceItems = $this->parseCompoundItem($choiceText);

                        // Calculate total quantity from choice_items for backwards compat
                        $totalQuantity = array_sum(array_column($choiceItems, 'quantity'));

                        $items[] = [
                            'description' => trim($choiceText),
                            'is_choice' => true,
                            'choice_group' => "choice_{$choiceGroupNumber}",
                            'choice_option' => $optionNumber++,
                            'quantity' => $totalQuantity ?: 1,
                            'choice_items' => $choiceItems,
                        ];
                    }
                    $choiceGroupNumber++;
                }
            } else {
                // Simple item (no choice) - may have multiple items separated by comma or "and"
                // Handle cases like: "two dagger and four javelins" or "Leather armor, dagger, and rope"

                // Split by ", and " (Oxford comma) or ", " or " and "
                $parts = preg_split('/,\s+(?:and\s+)?|\s+and\s+/i', $bulletText);

                foreach ($parts as $part) {
                    $part = trim($part);
                    if (empty($part)) {
                        continue;
                    }

                    // Parse as compound item (handles quantity extraction too)
                    $choiceItems = $this->parseCompoundItem($part);

                    // Calculate total quantity from choice_items
                    $totalQuantity = array_sum(array_column($choiceItems, 'quantity'));

                    $items[] = [
                        'description' => $part,
                        'is_choice' => false,
                        'choice_group' => null,
                        'choice_option' => null,
                        'quantity' => $totalQuantity ?: 1,
                        'choice_items' => $choiceItems,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Parse a compound item description into structured choice_items.
     *
     * Handles patterns like:
     * - "a martial weapon and a shield" → 2 items
     * - "two martial weapons" → 1 item with quantity=2
     * - "shortbow and quiver of arrows (20)" → 2 items
     * - "any simple weapon" → 1 category item
     *
     * @return array<int, array{type: string, value: string, quantity: int}>
     */
    private function parseCompoundItem(string $text): array
    {
        $items = [];

        // Split on ", and " (Oxford comma) or " and " for compound items
        // This handles: "leather armor, longbow, and arrows (20)"
        // Result: ["leather armor, longbow", "arrows (20)"]
        $parts = preg_split('/,?\s+and\s+/i', $text);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            // Check if this part contains comma-separated items (e.g., "leather armor, longbow")
            // Only split if commas exist AND there's no parentheses (to avoid splitting "quiver of arrows (20)")
            if (str_contains($part, ',') && ! preg_match('/\([^)]+\)/', $part)) {
                $subParts = explode(',', $part);
                foreach ($subParts as $subPart) {
                    $subPart = trim($subPart);
                    if (! empty($subPart)) {
                        $parsedItem = $this->parseSingleItem($subPart);
                        if ($parsedItem !== null) {
                            $items[] = $parsedItem;
                        }
                    }
                }
            } else {
                // Single item (may have quantity prefix or parenthetical quantity)
                $parsedItem = $this->parseSingleItem($part);
                if ($parsedItem !== null) {
                    $items[] = $parsedItem;
                }
            }
        }

        return $items;
    }

    /**
     * Parse a single item string into a structured array.
     *
     * Handles:
     * - Quantity prefixes: "two daggers", "20 arrows"
     * - Parenthetical quantities: "arrows (20)", "quiver of arrows (20)"
     * - Category references: "any martial weapon", "simple melee weapon"
     * - Armor categories: "light armor", "heavy armour"
     * - Musical instruments: "any musical instrument"
     * - Specific items: "longbow", "leather armor"
     *
     * @return array{type: string, value: string, quantity: int}|null
     */
    private function parseSingleItem(string $part): ?array
    {
        $part = trim($part);
        if (empty($part)) {
            return null;
        }

        // Extract quantity from start (two, three, etc.)
        $quantity = 1;
        if (preg_match('/^(two|three|four|five|six|seven|eight|nine|ten|twenty)\s+/i', $part, $m)) {
            $quantity = $this->wordToNumber($m[1]);
            $part = preg_replace('/^(two|three|four|five|six|seven|eight|nine|ten|twenty)\s+/i', '', $part);
        }

        // Extract numeric quantity from start (20 arrows)
        if (preg_match('/^(\d+)\s+/i', $part, $m)) {
            $quantity = (int) $m[1];
            $part = preg_replace('/^\d+\s+/', '', $part);
        }

        // Remove leading articles (a, an, the)
        $part = preg_replace('/^(a|an|the)\s+/i', '', $part);

        // Check for category references (martial/simple weapons)
        // Pattern: "any martial weapon", "martial weapon", "martial melee weapon", "simple ranged weapon"
        if (preg_match('/^(?:any\s+)?(martial|simple)\s+(?:(melee|ranged)\s+)?weapons?$/i', $part, $m)) {
            $category = strtolower($m[1]);
            if (! empty($m[2])) {
                $category .= '_'.strtolower($m[2]);
            }

            return ['type' => 'category', 'value' => $category, 'quantity' => $quantity];
        }

        // Check for armor categories
        if (preg_match('/^(?:any\s+)?(light|medium|heavy)\s+armou?r$/i', $part, $m)) {
            return ['type' => 'category', 'value' => strtolower($m[1]).'_armor', 'quantity' => $quantity];
        }

        // Check for musical instrument category
        // Patterns: "any musical instrument", "any other musical instrument", "musical instrument of your choice", etc.
        if (preg_match('/^(?:any\s+)?(?:other\s+)?musical\s+instruments?(?:\s+of\s+your\s+choice)?$/i', $part) ||
            preg_match('/^(?:one\s+)?musical\s+instruments?$/i', $part)) {
            return ['type' => 'category', 'value' => ToolProficiencyCategory::MUSICAL_INSTRUMENT->value, 'quantity' => $quantity];
        }

        // Handle "quiver of arrows (20)" or "arrows (20)" pattern
        if (preg_match('/(?:quiver\s+of\s+)?(\w+)\s*\((\d+)\)/i', $part, $m)) {
            return ['type' => 'item', 'value' => strtolower($m[1]), 'quantity' => (int) $m[2]];
        }

        // Specific item - clean up for matching
        // Remove parenthetical notes like "(holy symbol)" but keep item name
        $itemName = preg_replace('/\s*\([^)]+\)\s*$/', '', $part);
        $itemName = trim($itemName);

        if (! empty($itemName)) {
            return ['type' => 'item', 'value' => $itemName, 'quantity' => $quantity];
        }

        return null;
    }
}

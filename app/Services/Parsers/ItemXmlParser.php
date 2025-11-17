<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class ItemXmlParser
{
    public function parseItemElement(SimpleXMLElement $itemElement): array
    {
        $data = [
            'name' => (string) $itemElement->name,
            'type_code' => (string) $itemElement->type,
            'weight_lbs' => !empty($itemElement->weight) ? (float) $itemElement->weight : null,
            'value_gp' => !empty($itemElement->value) ? (float) $itemElement->value : null,
            'description' => trim((string) $itemElement->text),
        ];

        // Parse rarity from detail field
        $data['rarity_code'] = !empty($itemElement->detail) ? strtolower((string) $itemElement->detail) : 'common';

        // Parse properties (e.g., "F,V" -> ['F', 'V'])
        $data['properties'] = [];
        if (!empty($itemElement->property)) {
            $data['properties'] = array_map('trim', explode(',', (string) $itemElement->property));
        }

        // Parse damage information
        $data['damage_dice'] = !empty($itemElement->dmg1) ? (string) $itemElement->dmg1 : null;
        $data['damage_dice_versatile'] = !empty($itemElement->dmg2) ? (string) $itemElement->dmg2 : null;
        $data['damage_type_code'] = !empty($itemElement->dmgType) ? (string) $itemElement->dmgType : null;

        // Parse range
        $data['range'] = !empty($itemElement->range) ? (string) $itemElement->range : null;

        // Extract source info
        $sourceInfo = $this->extractSourceInfo($data['description']);
        $data['source_code'] = $sourceInfo['code'];
        $data['source_page'] = $sourceInfo['page'];

        return $data;
    }

    private function extractSourceInfo(string $description): array
    {
        $result = ['code' => 'PHB', 'page' => null];

        // Match "Source: Player's Handbook (2014) p. 149"
        if (preg_match('/Source:\s*(.+?)\s*\(?\d{4}\)?\s*p\.\s*(\d+)/i', $description, $matches)) {
            $bookMap = [
                "Player's Handbook" => 'PHB',
                "Dungeon Master's Guide" => 'DMG',
                'Monster Manual' => 'MM',
                "Xanathar's Guide to Everything" => 'XGE',
                "Tasha's Cauldron of Everything" => 'TCE',
            ];
            $bookName = trim($matches[1]);
            $result['code'] = $bookMap[$bookName] ?? 'PHB';
            $result['page'] = (int) $matches[2];
        }

        return $result;
    }
}

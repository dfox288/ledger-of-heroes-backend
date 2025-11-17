<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class FeatXmlParser
{
    public function parseFeatElement(SimpleXMLElement $featElement): array
    {
        $data = [
            'name' => (string) $featElement->name,
            'description' => trim((string) $featElement->text),
        ];

        // Parse modifiers
        $data['modifiers'] = [];
        foreach ($featElement->modifier as $modifierElement) {
            $category = (string) $modifierElement['category'];
            $value = trim((string) $modifierElement);

            if ($category === 'ability score') {
                // Parse "charisma +1" or "strength +1"
                if (preg_match('/(\w+)\s*([+\-]\d+)/', $value, $matches)) {
                    $data['modifiers'][] = [
                        'modifier_type' => 'ability_score',
                        'target' => strtolower($matches[1]),
                        'value' => $matches[2],
                    ];
                }
            } elseif ($category === 'bonus') {
                // Parse "initiative +5"
                if (preg_match('/(\w+)\s*([+\-]\d+)/', $value, $matches)) {
                    $data['modifiers'][] = [
                        'modifier_type' => 'bonus',
                        'target' => strtolower($matches[1]),
                        'value' => $matches[2],
                    ];
                }
            }
        }

        // Extract source info
        $sourceInfo = $this->extractSourceInfo($data['description']);
        $data['source_code'] = $sourceInfo['code'];
        $data['source_page'] = $sourceInfo['page'];

        return $data;
    }

    private function extractSourceInfo(string $description): array
    {
        $result = ['code' => 'PHB', 'page' => null];

        if (preg_match('/Source:\s*(.+?)\s*\(?\d{4}\)?\s*p\.\s*(\d+)/i', $description, $matches)) {
            $bookMap = [
                "Player's Handbook" => 'PHB',
                "Dungeon Master's Guide" => 'DMG',
                "Xanathar's Guide to Everything" => 'XGE',
            ];
            $bookName = trim($matches[1]);
            $result['code'] = $bookMap[$bookName] ?? 'PHB';
            $result['page'] = (int) $matches[2];
        }

        return $result;
    }
}

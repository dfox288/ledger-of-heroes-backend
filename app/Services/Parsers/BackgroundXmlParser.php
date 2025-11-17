<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class BackgroundXmlParser
{
    public function parseBackgroundElement(SimpleXMLElement $backgroundElement): array
    {
        $data = [
            'name' => (string) $backgroundElement->name,
        ];

        // Parse proficiencies (skills)
        $data['proficiencies'] = [];
        if (!empty($backgroundElement->proficiency)) {
            $skills = array_map('trim', explode(',', (string) $backgroundElement->proficiency));
            foreach ($skills as $skill) {
                $data['proficiencies'][] = [
                    'proficiency_type' => 'skill',
                    'name' => $skill,
                ];
            }
        }

        // Parse traits
        $data['traits'] = [];
        foreach ($backgroundElement->trait as $traitElement) {
            $data['traits'][] = [
                'name' => (string) $traitElement->name,
                'category' => null,
                'description' => trim((string) $traitElement->text),
            ];
        }

        // Extract source info
        $sourceInfo = ['code' => 'PHB', 'page' => null];
        if (!empty($data['traits']) && !empty($data['traits'][0]['description'])) {
            $sourceInfo = $this->extractSourceInfo($data['traits'][0]['description']);
        }
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

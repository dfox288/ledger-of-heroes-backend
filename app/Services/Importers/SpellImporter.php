<?php

namespace App\Services\Importers;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\Source;
use App\Services\Parsers\SpellXmlParser;

class SpellImporter
{
    public function import(array $spellData): Spell
    {
        // Lookup spell school by code
        $spellSchool = SpellSchool::where('code', $spellData['school'])->firstOrFail();

        // Lookup source by code
        $source = Source::where('code', $spellData['source_code'])->firstOrFail();

        // Create or update spell
        $spell = Spell::updateOrCreate(
            ['name' => $spellData['name']],
            [
                'level' => $spellData['level'],
                'spell_school_id' => $spellSchool->id,
                'casting_time' => $spellData['casting_time'],
                'range' => $spellData['range'],
                'components' => $spellData['components'],
                'material_components' => $spellData['material_components'],
                'duration' => $spellData['duration'],
                'needs_concentration' => $spellData['needs_concentration'],
                'is_ritual' => $spellData['is_ritual'],
                'description' => $spellData['description'],
                'higher_levels' => $spellData['higher_levels'],
                'source_id' => $source->id,
                'source_pages' => $spellData['source_pages'],
            ]
        );

        // Delete existing effects (for re-imports)
        $spell->effects()->delete();

        // Import spell effects
        if (isset($spellData['effects'])) {
            foreach ($spellData['effects'] as $effectData) {
                $spell->effects()->create($effectData);
            }
        }

        // TODO: Import class associations in later tasks

        return $spell;
    }

    public function importFromFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $xmlContent = file_get_contents($filePath);
        $parser = new SpellXmlParser();
        $spells = $parser->parse($xmlContent);

        $count = 0;
        foreach ($spells as $spellData) {
            $this->import($spellData);
            $count++;
        }

        return $count;
    }
}

<?php

namespace App\Services\Importers;

use App\Models\CharacterClass;
use App\Models\EntitySource;
use App\Models\Source;
use App\Models\Spell;
use App\Models\SpellSchool;
use App\Services\Parsers\SpellXmlParser;

class SpellImporter
{
    public function import(array $spellData): Spell
    {
        // Lookup spell school by code
        $spellSchool = SpellSchool::where('code', $spellData['school'])->firstOrFail();

        // Create or update spell (no longer storing source_id/source_pages directly)
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

        // Import sources - clear old sources and create new ones
        if (isset($spellData['sources']) && is_array($spellData['sources'])) {
            $this->importSources($spell, $spellData['sources']);
        }

        // Import class associations
        if (isset($spellData['classes']) && is_array($spellData['classes'])) {
            $this->importClassAssociations($spell, $spellData['classes']);
        }

        return $spell;
    }

    /**
     * Import sources for a spell.
     * Creates entity_sources junction records for each source.
     *
     * @param  array  $sources  Array of ['code' => 'PHB', 'pages' => '241']
     */
    private function importSources(Spell $spell, array $sources): void
    {
        // Clear existing sources
        $spell->sources()->delete();

        // Create new source associations
        foreach ($sources as $sourceData) {
            $source = Source::where('code', $sourceData['code'])->first();

            if ($source) {
                EntitySource::create([
                    'reference_type' => Spell::class,
                    'reference_id' => $spell->id,
                    'source_id' => $source->id,
                    'pages' => $sourceData['pages'] ?? null,
                ]);
            }
        }
    }

    /**
     * Import class associations for a spell.
     * Extracts base class names and creates class_spells junction records.
     *
     * @param  array  $classNames  Array of class names (may include subclasses in parentheses)
     */
    private function importClassAssociations(Spell $spell, array $classNames): void
    {
        $classIds = [];

        foreach ($classNames as $className) {
            // Extract base class name (strip subclass in parentheses)
            // "Fighter (Eldritch Knight)" → "Fighter"
            // "Druid (Moon)" → "Druid"
            $baseClassName = preg_replace('/\s*\([^)]+\)/', '', $className);
            $baseClassName = trim($baseClassName);

            // Find matching class in database
            $class = CharacterClass::where('name', $baseClassName)
                ->whereNull('parent_class_id') // Only match base classes
                ->first();

            if ($class) {
                $classIds[] = $class->id;
            }
        }

        // Sync class associations (removes old associations, adds new ones)
        $spell->classes()->sync($classIds);
    }

    public function importFromFile(string $filePath): int
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $xmlContent = file_get_contents($filePath);
        $parser = new SpellXmlParser;
        $spells = $parser->parse($xmlContent);

        $count = 0;
        foreach ($spells as $spellData) {
            $this->import($spellData);
            $count++;
        }

        return $count;
    }
}

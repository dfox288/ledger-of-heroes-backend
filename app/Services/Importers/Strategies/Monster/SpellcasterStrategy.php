<?php

namespace App\Services\Importers\Strategies\Monster;

use App\Models\Monster;
use App\Models\Spell;

class SpellcasterStrategy extends AbstractMonsterStrategy
{
    /** @var array<string, Spell|null> Cache of spell lookups */
    private array $spellCache = [];

    public function appliesTo(array $monsterData): bool
    {
        return isset($monsterData['spells']) && ! empty($monsterData['spells']);
    }

    /**
     * @param  Monster  $entity
     */
    public function afterCreate(\Illuminate\Database\Eloquent\Model $entity, array $data): void
    {
        if (empty($data['spells'])) {
            return;
        }

        $this->syncSpells($entity, $data['spells']);
    }

    public function extractMetadata(array $monsterData): array
    {
        $metadata = parent::extractMetadata($monsterData);
        $metadata['has_spells'] = ! empty($monsterData['spells']);
        $metadata['has_spell_slots'] = ! empty($monsterData['slots']);

        // Include spell matching metrics at root level for easy access
        $metadata['spells_matched'] = $this->metrics['spells_matched'] ?? 0;
        $metadata['spells_not_found'] = $this->metrics['spells_not_found'] ?? 0;
        $metadata['spell_references_found'] = $this->metrics['spell_references_found'] ?? 0;

        return $metadata;
    }

    /**
     * Sync monster spells to entity_spells table.
     *
     * @param  string  $spellsString  Comma-separated spell names
     */
    private function syncSpells(Monster $monster, string $spellsString): void
    {
        $spellNames = $this->parseSpellNames($spellsString);

        if (empty($spellNames)) {
            return;
        }

        $this->setMetric('spell_references_found', count($spellNames));

        foreach ($spellNames as $spellName) {
            $spell = $this->findSpell($spellName);

            if ($spell) {
                // Sync to entity_spells pivot table
                $monster->spells()->attach($spell->id);
                $this->incrementMetric('spells_matched');
            } else {
                $this->incrementMetric('spells_not_found');
                $this->addWarning("Spell not found in database: {$spellName}");
            }
        }
    }

    /**
     * Parse comma-separated spell names.
     *
     * @return array<int, string> Normalized spell names
     */
    private function parseSpellNames(string $spellsString): array
    {
        $names = explode(',', $spellsString);

        return array_map(
            fn ($name) => $this->normalizeSpellName($name),
            $names
        );
    }

    /**
     * Normalize spell name to Title Case.
     */
    private function normalizeSpellName(string $name): string
    {
        $name = trim($name);

        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Find spell by name (case-insensitive) with caching.
     */
    private function findSpell(string $name): ?Spell
    {
        $cacheKey = mb_strtolower($name);

        if (! isset($this->spellCache[$cacheKey])) {
            $this->spellCache[$cacheKey] = Spell::whereRaw('LOWER(name) = ?', [$cacheKey])->first();
        }

        return $this->spellCache[$cacheKey];
    }
}

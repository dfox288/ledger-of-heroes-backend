<?php

namespace App\Services\Importers\Concerns;

use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use App\Services\Parsers\Concerns\ParsesSubclassSpellTables;
use Illuminate\Support\Facades\Log;

/**
 * Trait for importing subclass spell associations (domain, circle, expanded spells).
 *
 * Creates EntitySpell records linking ClassFeature → Spell with level_requirement.
 */
trait ImportsSubclassSpells
{
    use ParsesSubclassSpellTables;

    /**
     * Import subclass spells from a feature's description text.
     *
     * Parses spell tables and creates entity_spells records for each spell.
     *
     * @param  ClassFeature  $feature  The subclass feature (e.g., "Divine Domain: Life Domain")
     * @param  string  $description  The feature description containing the spell table
     */
    public function importSubclassSpells(ClassFeature $feature, string $description): void
    {
        // Clear existing spell associations for this feature
        EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->delete();

        // Parse spell table from description
        $spellData = $this->parseSubclassSpellTable($description);

        if ($spellData === null) {
            return;
        }

        foreach ($spellData as $levelData) {
            $classLevel = $levelData['level'];

            foreach ($levelData['spells'] as $spellName) {
                $this->createSpellAssociation($feature, $spellName, $classLevel);
            }
        }
    }

    /**
     * Create a spell association for a feature.
     */
    private function createSpellAssociation(ClassFeature $feature, string $spellName, int $classLevel): void
    {
        // Look up spell by name (case-insensitive)
        $spell = Spell::whereRaw('LOWER(name) = ?', [strtolower($spellName)])->first();

        if (! $spell) {
            Log::warning("Subclass spell not found: {$spellName} (for feature: {$feature->feature_name})");

            return;
        }

        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $spell->id,
            'level_requirement' => $classLevel,
            'is_cantrip' => $spell->level === 0,
        ]);
    }
}
